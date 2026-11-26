<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/attendance-base.php';

attendance_guard(['student', 'rep']);

$studentId = $viewerId;
$currentUrl = $attendanceBase . '/student/event-payment.php';

/*
   Pag-submit ng bayad: cash (ia-approve ng block rep) or Online Payment
   (i-verify ng block rep o admin)
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    attendance_check_csrf();

    $registrationId = (int) ($_POST['registration_id'] ?? 0);
    $method = (string) ($_POST['method'] ?? 'cash');
    $reference = trim((string) ($_POST['reference'] ?? ''));

    try {
        $paymentStatement = $pdo->prepare(
            'SELECT p.paymentID, p.amountDue, p.amountPaid, p.paymentStatus,
                    e.eventTitle, e.eventDate, e.eventStatus, e.paymentStartDate, e.paymentDeadline
             FROM tbleventpayment p
             JOIN tbleventregistration r ON r.registrationID = p.registrationID
             JOIN tblevent e ON e.eventID = r.eventID
             WHERE r.registrationID = ? AND r.studentID = ?'
        );
        $paymentStatement->execute([$registrationId, $studentId]);
        $payment = $paymentStatement->fetch();

        if ($payment === false) {
            attendance_flash('error', 'That payment record could not be found.');
        } elseif ((string) $payment['eventStatus'] === 'Cancelled') {
            attendance_flash('error', 'That event was cancelled, so no payment is needed. A paid fee is marked as Refunded.');
        } elseif (!in_array((string) $payment['paymentStatus'], ['Unpaid', 'Declined', 'Overdue'], true)) {
            attendance_flash('error', 'That payment is already waiting for approval or already paid.');
        } elseif (attendance_payment_window($payment) === 'not_open') {
            attendance_flash('error', attendance_payment_note($payment));
        } elseif (!in_array($method, ['cash', 'gcash'], true)) {
            attendance_flash('error', 'Please choose a valid payment method.');
        } elseif ($method === 'gcash' && !preg_match('/^\d{6,20}$/', $reference)) {
            attendance_flash('error', 'The Online Payment reference number must be 6 to 20 digits. Numbers only, no letters.');
        } else {
            $balance = (float) $payment['amountDue'] - (float) $payment['amountPaid'];
            if ($balance <= 0) {
                attendance_flash('error', 'There is nothing left to pay for that event.');
            } else {
                $latePrefix = attendance_payment_window($payment) === 'closed' ? 'Late payment - ' : '';

                if ($method === 'cash') {
                    $status = 'Pending Cash';
                    $remarks = $latePrefix . 'Cash payment - hand the amount to your block representative for approval.';
                } else {
                    $status = 'For Verification';
                    $remarks = $latePrefix . 'Online Payment reference: ' . mb_substr($reference, 0, 80);
                }

                $update = $pdo->prepare(
                    'UPDATE tbleventpayment
                     SET paymentStatus = ?, paymentRemarks = ?, paymentDate = NULL,
                         processedByAdminID = NULL, processedByStudentID = NULL
                     WHERE paymentID = ?'
                );
                $update->execute([$status, mb_substr($remarks, 0, 255), (int) $payment['paymentID']]);

                if ($method === 'cash') {
                    attendance_flash('success', 'Cash payment recorded for ' . $payment['eventTitle'] . '. Please pay your block representative so they can approve it.');
                } else {
                    attendance_flash('success', 'Online Payment submitted for ' . $payment['eventTitle'] . '. Your block representative or the administrator will verify your reference number.');
                }
            }
        }
    } catch (PDOException $exception) {
        attendance_flash('error', 'The payment could not be saved. Please try again.');
    }

    attendance_redirect($currentUrl);
}

/* Data na kailangan ng page */
$paymentsStatement = $pdo->prepare(
    'SELECT p.*, r.registrationID, r.registrationStatus, r.eventID,
            e.eventTitle, e.eventDate, e.eventStartTime, e.eventStatus, e.participationFee,
            e.paymentStartDate, e.paymentDeadline,
            processorAdmin.adminFirstName AS processorAdminFirstName,
            processorAdmin.adminLastName AS processorAdminLastName,
            processorStudent.studentFirstName AS processorStudentFirstName,
            processorStudent.studentLastName AS processorStudentLastName,
            receiverAdmin.adminFirstName AS receivedByAdminFirstName,
            receiverAdmin.adminLastName AS receivedByAdminLastName
     FROM tbleventpayment p
     JOIN tbleventregistration r ON r.registrationID = p.registrationID
     JOIN tblevent e ON e.eventID = r.eventID
     LEFT JOIN tbladmin processorAdmin ON processorAdmin.adminID = p.processedByAdminID
     LEFT JOIN tblstudent processorStudent ON processorStudent.studentID = p.processedByStudentID
     LEFT JOIN tbladmin receiverAdmin ON receiverAdmin.adminID = p.receivedByAdminID
     WHERE r.studentID = ? AND e.archivedAt IS NULL
     ORDER BY e.eventDate DESC, e.eventStartTime DESC'
);
$paymentsStatement->execute([$studentId]);
$payments = $paymentsStatement->fetchAll();

$totalDue = 0.00;
$totalPaid = 0.00;
$openPayments = [];

foreach ($payments as $payment) {
    // cancelled event: refunded na or wala nang babayaran, hindi na isasama sa totals
    if ((string) $payment['eventStatus'] === 'Cancelled') {
        continue;
    }

    $totalDue += (float) $payment['amountDue'];
    $totalPaid += (float) $payment['amountPaid'];

    if (in_array((string) $payment['paymentStatus'], ['Unpaid', 'Declined', 'Overdue'], true)
        && (string) $payment['eventStatus'] !== 'Cancelled') {
        $openPayments[] = $payment;
    }
}

$outstanding = max(0, $totalDue - $totalPaid);

attendance_page_start('payment');
?>

<div class="stack">
    <div class="highlightGrid">
        <section class="highlight light">
            <div>
                <div class="eyebrow">Total amount due</div>
                <strong><?= attendance_money($totalDue) ?></strong>
                <small class="muted">All registered paid events</small>
            </div>
        </section>
        <section class="highlight light">
            <div>
                <div class="eyebrow">Total paid</div>
                <strong><?= attendance_money($totalPaid) ?></strong>
                <small class="muted">Approved payments</small>
            </div>
        </section>
        <section class="highlight light">
            <div>
                <div class="eyebrow">Outstanding balance</div>
                <strong><?= attendance_money($outstanding) ?></strong>
                <small class="muted"><?= count($openPayments) ?> payment<?= count($openPayments) === 1 ? '' : 's' ?> open</small>
            </div>
            <?php if (count($openPayments) > 0): ?>
                <button class="btn" type="button" data-modal-open="paymentModal">Pay now</button>
            <?php endif; ?>
        </section>
    </div>

    <div class="layout">
        <section class="card">
            <div class="cardHead">
                <img src="<?= h($attendanceAssets) ?>/card.png" alt="">
                <h3>Event payments</h3>
                <span class="spacer"></span>
                <?php if ($attendanceRole === 'rep'): ?>
                    <a class="pillLink" href="<?= h($attendanceBase) ?>/rep/event-payment.php">
                        Back to block payments
                        <img src="<?= h($attendanceAssets) ?>/chevron-right.png" alt="">
                    </a>
                <?php endif; ?>
                <span class="pillLink">My payments</span>
            </div>

            <?php if (count($payments) === 0): ?>
                <div class="emptyState">
                    <img src="<?= h($attendanceAssets) ?>/wallet.png" alt="">
                    <strong>No payment records yet</strong>
                    <p>Event fees are created automatically when you register for a paid event.</p>
                </div>
            <?php else: ?>
                <div class="tableWrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Amount due</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Deadline</th>
                                <th>Status</th>
                                <th>Remarks</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <?php
                                $balance = (float) $payment['amountDue'] - (float) $payment['amountPaid'];
                                $canPay = in_array((string) $payment['paymentStatus'], ['Unpaid', 'Declined', 'Overdue'], true)
                                    && (string) $payment['eventStatus'] !== 'Cancelled'
                                    && $balance > 0
                                    && attendance_payment_window($payment) !== 'not_open';
                                $paymentStatus = attendance_payment_status($payment);
                                ?>
                                <tr>
                                    <td class="strong">
                                        <?= h($payment['eventTitle']) ?>
                                        <span class="sub"><?= h(attendance_long_date($payment['eventDate'])) ?></span>
                                    </td>
                                    <td class="nowrap"><?= attendance_money((float) $payment['amountDue']) ?></td>
                                    <td class="nowrap"><?= attendance_money((float) $payment['amountPaid']) ?></td>
                                    <td class="nowrap"><?= attendance_money(max(0, $balance)) ?></td>
                                    <td class="nowrap">
                                        <?php if (!empty($payment['paymentDeadline'])): ?>
                                            <?= h(attendance_long_date((string) $payment['paymentDeadline'])) ?>
                                            <?php if ($paymentStatus === 'Late Payment'): ?>
                                                <span class="sub">Deadline passed</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="small muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= attendance_badge($paymentStatus) ?>
                                        <span class="sub"><?= h(attendance_payment_processor($payment)) ?></span>
                                    </td>
                                    <td class="small muted"><?= h((string) ($payment['paymentRemarks'] ?? '-')) ?></td>
                                    <td>
                                        <?php if ($canPay): ?>
                                            <button class="btn tiny" type="button" data-modal-open="paymentModal">Pay</button>
                                        <?php elseif ((string) $payment['paymentStatus'] === 'Paid'): ?>
                                            <span class="small muted nowrap"><?= h(attendance_datetime($payment['paymentDate'])) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php foreach ($payments as $paymentNote): ?>
                <?php if (attendance_payment_window($paymentNote) === 'not_open'): ?>
                    <p class="hint" style="padding:0 1rem 0.7rem;">
                        <?= h($paymentNote['eventTitle']) ?>: <?= h(attendance_payment_note($paymentNote)) ?>
                    </p>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>

        <div class="stack">
            <section class="card">
                <div class="infoBox">
                    <h4><img src="<?= h($attendanceAssets) ?>/info.png" alt=""> How event payments work</h4>
                    <ul>
                        <li>Every event is paid one at a time. Open an event to choose your payment method.</li>
                        <li>Online Payment (GCash) is recorded once you submit the reference number, then verified by your block representative or the administrator.</li>
                        <li>Cash is handed to your block representative and waits for their approval.</li>
                        <li>Payment opens on the payment opens date set by the administrator.</li>
                        <li>An unpaid fee past its deadline shows as Late Payment, and you can still settle it.</li>
                        <li>If the event is cancelled, your payment goes to Refund Pending first and becomes Refunded once the admin or block rep approves it. You will not pay anything.</li>
                    </ul>
                </div>
            </section>

            <section class="card">
                <div class="infoBox">
                    <h4><img src="<?= h($attendanceAssets) ?>/ticket.png" alt=""> Need to register first?</h4>
                    <p>A payment record is created automatically for every registration to a paid event.</p>
                    <a class="btn ghost" href="<?= h($attendanceBase) ?>/student/campus-event.php">Open event registration</a>
                </div>
            </section>
        </div>
    </div>
</div>

<?php if (count($openPayments) > 0): ?>
    <div class="modal" id="paymentModal" hidden>
        <div class="modalBackdrop"></div>
        <div class="modalPanel">
            <div class="modalHead">
                <div>
                    <h3>Pay event fee</h3>
                    <p>Choose the event and how you want to pay.</p>
                </div>
                <button class="closeBtn" type="button" data-modal-close aria-label="Close">
                    <img src="<?= h($attendanceAssets) ?>/close.png" alt="">
                </button>
            </div>
            <div class="modalBody">
                <form method="post" data-payment-form>
                    <?= attendance_csrf_field() ?>

                    <div class="field">
                        <span>Event</span>
                        <select name="registration_id" required>
                            <?php foreach ($openPayments as $payment): ?>
                                <option value="<?= (int) $payment['registrationID'] ?>"
                                        data-due="<?= h(attendance_money(max(0, (float) $payment['amountDue'] - (float) $payment['amountPaid']))) ?>">
                                    <?= h($payment['eventTitle']) ?> &mdash; <?= h(attendance_long_date($payment['eventDate'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field mt1">
                        <span>Amount to pay</span>
                        <div class="value" data-due-field></div>
                    </div>

                    <div class="field mt1">
                        <span>Payment method</span>
                        <div class="radioOptions">
                            <label class="radioOption">
                                <input type="radio" name="method" value="cash" checked>
                                <span>
                                    Cash
                                    <small>Hand it to your block representative for approval.</small>
                                </span>
                            </label>
                            <label class="radioOption">
                                <input type="radio" name="method" value="gcash">
                                <span>
                                    Online Payment
                                    <small>Pay with GCash, then enter the reference number. Your block rep or the administrator verifies it.</small>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="field mt1" data-reference-field hidden>
                        <span>Online Payment reference number</span>
                        <input type="text" name="reference" inputmode="numeric" pattern="[0-9]{6,20}" maxlength="20" placeholder="Digits only, e.g. 1234567890123">
                        <small class="muted">GCash reference number &mdash; numbers only, 6 to 20 digits, no letters or spaces.</small>
                    </div>

                    <p class="hint">
                        Cash payments are approved by your block representative. Online Payments are checked by your block rep or the administrator before the status becomes Paid.
                    </p>

                    <div class="formActions">
                        <button class="btn" type="submit">Submit payment</button>
                        <button class="btn gray" type="button" data-modal-close>Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php attendance_page_end(); ?>
