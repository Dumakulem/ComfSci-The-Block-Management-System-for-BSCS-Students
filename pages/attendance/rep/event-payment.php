<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/attendance-base.php';

attendance_guard('rep');

$blockId = (int) $studentRecord['blockID'];
$currentUrl = $attendanceBase . '/rep/event-payment.php';

$eventId = (int) ($_GET['event'] ?? 0);
$statusFilter = (string) ($_GET['status'] ?? 'all');
if (!in_array($statusFilter, ['all', 'Unpaid', 'Late Payment', 'Pending Cash', 'For Verification', 'Paid', 'Refund Pending', 'Refunded'], true)) {
    $statusFilter = 'all';
}
$search = trim((string) ($_GET['q'] ?? ''));

$filterQuery = '?event=' . $eventId . '&status=' . urlencode($statusFilter) . '&q=' . urlencode($search);

/*
   Dito ina-approve ng rep ang cash at vine-verify ang Online Payment
   ng mga students sa block nila
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    attendance_check_csrf();

    $paymentId = (int) ($_POST['payment_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    try {
        $check = $pdo->prepare(
            'SELECT p.paymentID, p.paymentStatus, e.eventStatus, e.paymentDeadline, s.studentID
             FROM tbleventpayment p
             JOIN tbleventregistration r ON r.registrationID = p.registrationID
             JOIN tblevent e ON e.eventID = r.eventID
             JOIN tblstudent s ON s.studentID = r.studentID
             WHERE p.paymentID = ? AND s.blockID = ?'
        );
        $check->execute([$paymentId, $blockId]);
        $payment = $check->fetch();

        if ($payment === false) {
            attendance_flash('error', 'That payment does not belong to your block.');
        } elseif ((int) $payment['studentID'] === $viewerId) {
            attendance_flash('error', 'You cannot review your own payment. An administrator must process it.');
        } elseif ((string) $payment['eventStatus'] === 'Cancelled' && $action !== 'refund') {
            // cancelled na event = sarado na lahat ng payments, refund approval lang ang pwede
            attendance_flash('error', 'That event is cancelled, so its payments are already closed.');
        } elseif (!in_array($action, ['approve_cash', 'verify', 'decline', 'refund'], true)) {
            attendance_flash('error', 'Unknown action.');
        } else {
            $currentStatus = (string) $payment['paymentStatus'];
            $lateNote = attendance_payment_deadline($payment) > 0 && time() > attendance_payment_deadline($payment)
                ? ' | Late payment received after the deadline'
                : '';

            if ($action === 'refund') {
                if ($currentStatus !== 'Refund Pending') {
                    attendance_flash('error', 'Only refunds waiting for approval can be approved.');
                } else {
                    $update = $pdo->prepare(
                        "UPDATE tbleventpayment
                         SET paymentStatus = 'Refunded',
                             paymentDate = NOW(),
                             processedByAdminID = NULL, processedByStudentID = ?,
                             paymentRemarks = CONCAT(LEFT(COALESCE(paymentRemarks, ''), 150), ' | Refund approved by block representative')
                         WHERE paymentID = ? AND paymentStatus = 'Refund Pending'"
                    );
                    $update->execute([$viewerId, $paymentId]);
                    attendance_flash('success', 'Refund approved. The student is now marked as refunded.');
                }
            } elseif ($action === 'approve_cash') {
                if ($currentStatus !== 'Pending Cash') {
                    attendance_flash('error', 'Only cash payments waiting for approval can be approved here.');
                } else {
                    $update = $pdo->prepare(
                        "UPDATE tbleventpayment
                         SET amountPaid = amountDue,
                             paymentStatus = 'Paid',
                             paymentDate = NOW(),
                             processedByAdminID = NULL, processedByStudentID = ?,
                             paymentRemarks = CONCAT(LEFT(COALESCE(paymentRemarks, ''), 100), ' | Cash received by block rep', ?)
                         WHERE paymentID = ? AND paymentStatus = 'Pending Cash'"
                    );
                    $update->execute([$viewerId, $lateNote, $paymentId]);
                    attendance_flash('success', 'Cash payment approved. The student is now marked as paid.');
                }
            } elseif ($action === 'verify') {
                if ($currentStatus !== 'For Verification') {
                    attendance_flash('error', 'Only online payments waiting for verification can be verified.');
                } else {
                    $update = $pdo->prepare(
                        "UPDATE tbleventpayment
                         SET amountPaid = amountDue,
                             paymentStatus = 'Paid',
                             paymentDate = NOW(),
                             processedByAdminID = NULL, processedByStudentID = ?,
                             paymentRemarks = CONCAT(LEFT(COALESCE(paymentRemarks, ''), 90), ' | Online payment verified by block representative', ?)
                         WHERE paymentID = ? AND paymentStatus = 'For Verification'"
                    );
                    $update->execute([$viewerId, $lateNote, $paymentId]);
                    attendance_flash('success', 'Online payment verified. The student is now marked as paid.');
                }
            } else {
                if (!in_array($currentStatus, ['Pending Cash', 'For Verification'], true)) {
                    attendance_flash('error', 'Only submitted payments can be declined.');
                } else {
                    $update = $pdo->prepare(
                        "UPDATE tbleventpayment
                         SET paymentStatus = 'Declined',
                             processedByAdminID = NULL, processedByStudentID = ?,
                             paymentRemarks = CONCAT(LEFT(COALESCE(paymentRemarks, ''), 120), ' | Declined by block rep')
                         WHERE paymentID = ?"
                    );
                    $update->execute([$viewerId, $paymentId]);
                    attendance_flash('success', 'Payment declined. The student can submit it again.');
                }
            }
        }
    } catch (PDOException $exception) {
        attendance_flash('error', 'The payment could not be updated. Please try again.');
    }

    attendance_redirect($currentUrl . $filterQuery . '&page=' . max(1, (int) ($_GET['page'] ?? 1)));
}

/* Data na kailangan ng page */
$events = $pdo->query(
    'SELECT eventID, eventTitle, eventDate FROM tblevent WHERE archivedAt IS NULL ORDER BY eventDate DESC, eventStartTime DESC'
)->fetchAll();

$sql = "SELECT p.*, r.registrationID, r.registrationStatus,
               s.studentID, s.studentFirstName, s.studentLastName, s.studentMiddleName, s.studentExtensionName,
               b.classBlock,
               e.eventID, e.eventTitle, e.eventDate, e.participationFee,
               e.paymentStartDate, e.paymentDeadline,
               processorAdmin.adminFirstName AS processorAdminFirstName,
               processorAdmin.adminLastName AS processorAdminLastName,
               processorStudent.studentFirstName AS processorStudentFirstName,
               processorStudent.studentLastName AS processorStudentLastName,
               receiverAdmin.adminFirstName AS receivedByAdminFirstName,
               receiverAdmin.adminLastName AS receivedByAdminLastName
        FROM tbleventpayment p
        JOIN tbleventregistration r ON r.registrationID = p.registrationID
        JOIN tblstudent s ON s.studentID = r.studentID
        JOIN tblevent e ON e.eventID = r.eventID
        LEFT JOIN tblblock b ON b.blockID = s.blockID
        LEFT JOIN tbladmin processorAdmin ON processorAdmin.adminID = p.processedByAdminID
        LEFT JOIN tblstudent processorStudent ON processorStudent.studentID = p.processedByStudentID
        LEFT JOIN tbladmin receiverAdmin ON receiverAdmin.adminID = p.receivedByAdminID
        WHERE s.blockID = ? AND e.archivedAt IS NULL";

$params = [$blockId];

if ($eventId > 0) {
    $sql .= ' AND e.eventID = ?';
    $params[] = $eventId;
}

if ($statusFilter === 'Late Payment') {
    $sql .= " AND p.paymentStatus IN ('Unpaid', 'Declined', 'Overdue')
              AND e.paymentDeadline IS NOT NULL AND e.paymentDeadline < CURDATE()";
} elseif ($statusFilter !== 'all') {
    $sql .= ' AND p.paymentStatus = ?';
    $params[] = $statusFilter;
}

// search: pwede name o student ID
if ($search !== '') {
    $like = '%' . $search . '%';
    $sql .= " AND (s.studentFirstName LIKE ? OR s.studentLastName LIKE ?
                   OR CONCAT(s.studentLastName, ', ', s.studentFirstName) LIKE ?
                   OR CAST(s.studentID AS CHAR) LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= ' ORDER BY e.eventDate DESC, s.studentLastName, s.studentFirstName';

$paymentsStatement = $pdo->prepare($sql);
$paymentsStatement->execute($params);
$payments = $paymentsStatement->fetchAll();

$collected = 0.00;
$totalDue = 0.00;
foreach ($payments as $payment) {
    $collected += (float) $payment['amountPaid'];
    $totalDue += (float) $payment['amountDue'];
}

$counts = attendance_payment_counts($payments);
$pager = attendance_paginate(count($payments));

/* Student din ang block rep, kaya yung sarili nyang fee sa student
   payment page binabayaran tapos dito ina-approve o vine-verify. */
$myOpenStatement = $pdo->prepare(
    "SELECT COUNT(*)
     FROM tbleventpayment p
     JOIN tbleventregistration r ON r.registrationID = p.registrationID
     JOIN tblevent e ON e.eventID = r.eventID
     WHERE r.studentID = ? AND e.archivedAt IS NULL AND e.eventStatus <> 'Cancelled'
       AND p.paymentStatus IN ('Unpaid', 'Declined', 'Overdue')"
);
$myOpenStatement->execute([$viewerId]);
$myOpenPayments = (int) $myOpenStatement->fetchColumn();

attendance_page_start('payment');
?>

<div class="stack">
    <div class="highlightGrid">
        <section class="highlight green">
            <div class="eyebrow">Paid</div>
            <strong><?= $counts['paid'] ?> student<?= $counts['paid'] === 1 ? '' : 's' ?></strong>
            <small><?= attendance_money($collected) ?> collected</small>
        </section>
        <section class="highlight red">
            <div class="eyebrow">Unpaid</div>
            <strong><?= $counts['unpaid'] ?> student<?= $counts['unpaid'] === 1 ? '' : 's' ?></strong>
            <small><?= $counts['pending'] ?> waiting for approval &middot; <?= $counts['late'] ?> late</small>
        </section>
        <section class="highlight light">
            <div>
                <div class="eyebrow">My block</div>
                <strong><?= h((string) ($studentRecord['classBlock'] ?? '-')) ?></strong>
                <small class="muted"><?= attendance_money($totalDue) ?> total due</small>
            </div>
            <img src="<?= h($attendanceAssets) ?>/users.png" alt="" style="width:2.2rem;height:2.2rem;object-fit:contain;opacity:0.7;">
        </section>
    </div>

    <section class="card">
        <div class="cardHead">
            <img src="<?= h($attendanceAssets) ?>/card.png" alt="">
            <h3>Event payments</h3>
            <span class="spacer"></span>
            <?php if ($myOpenPayments > 0): ?>
                <a class="pillLink" href="<?= h($attendanceBase) ?>/student/event-payment.php">
                    Pay my own fee (<?= $myOpenPayments ?>)
                    <img src="<?= h($attendanceAssets) ?>/chevron-right.png" alt="">
                </a>
            <?php endif; ?>
            <span class="pillLink"><?= $counts['paid'] ?> paid &middot; <?= $counts['unpaid'] ?> unpaid</span>
        </div>

        <div class="filterRow">
            <form method="get" class="btnRow">
                <select class="selectInline" name="event" aria-label="Filter by event">
                    <option value="0">All events</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?= (int) $event['eventID'] ?>" <?= (int) $event['eventID'] === $eventId ? 'selected' : '' ?>>
                            <?= h($event['eventTitle']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select class="selectInline" name="status" aria-label="Filter by status">
                    <?php foreach (['all' => 'All statuses', 'Unpaid' => 'Unpaid', 'Late Payment' => 'Late Payment', 'Pending Cash' => 'Pending Cash', 'For Verification' => 'For Verification', 'Paid' => 'Paid', 'Refund Pending' => 'Refund Pending', 'Refunded' => 'Refunded'] as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="searchInline" type="search" name="q" value="<?= h($search) ?>" placeholder="Search student name or ID">
                <button class="btn ghost tiny" type="submit">
                    <img src="<?= h($attendanceAssets) ?>/filter.png" alt="">
                    Apply
                </button>
                <?php if ($eventId > 0 || $statusFilter !== 'all' || $search !== ''): ?>
                    <a class="btn gray tiny" href="<?= h($currentUrl) ?>">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (count($payments) === 0): ?>
            <div class="emptyState">
                <img src="<?= h($attendanceAssets) ?>/wallet.png" alt="">
                <strong>No payment records found</strong>
                <p>Payments from your block will appear here after students register for paid events.</p>
            </div>
        <?php else: ?>
            <div class="tableWrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Event</th>
                            <th>Amount due</th>
                            <th>Paid</th>
                            <th>Deadline</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($payments, $pager['offset'], $pager['perPage']) as $payment): ?>
                            <?php
                            $status = (string) $payment['paymentStatus'];
                            $displayStatus = attendance_payment_status($payment);
                            $isSelf = (int) $payment['studentID'] === $viewerId;
                            ?>
                            <tr>
                                <td class="strong">
                                    <?= h(attendance_student_name($payment)) ?>
                                    <?php if ($isSelf): ?><span class="badge">You</span><?php endif; ?>
                                    <span class="sub"><?= attendance_student_sub($payment) ?></span>
                                </td>
                                <td>
                                    <?= h($payment['eventTitle']) ?>
                                    <span class="sub"><?= h(attendance_long_date($payment['eventDate'])) ?></span>
                                </td>
                                <td class="nowrap"><?= attendance_money((float) $payment['amountDue']) ?></td>
                                <td class="nowrap"><?= attendance_money((float) $payment['amountPaid']) ?></td>
                                <td class="nowrap">
                                    <?= !empty($payment['paymentDeadline']) ? h(attendance_long_date((string) $payment['paymentDeadline'])) : '<span class="small muted">None</span>' ?>
                                </td>
                                <td>
                                    <?= attendance_badge($displayStatus) ?>
                                    <span class="sub"><?= h((string) ($payment['paymentRemarks'] ?? '')) ?></span>
                                    <span class="sub"><?= h(attendance_payment_processor($payment)) ?></span>
                                </td>
                                <td>
                                    <?php if ($isSelf && in_array($status, ['Pending Cash', 'For Verification', 'Refund Pending'], true)): ?>
                                        <span class="small muted">Administrator review required</span>
                                    <?php elseif ($status === 'Pending Cash' || $status === 'For Verification'): ?>
                                        <div class="btnRow">
                                            <?php if ($status === 'Pending Cash'): ?>
                                                <form method="post" style="margin:0;">
                                                    <?= attendance_csrf_field() ?>
                                                    <input type="hidden" name="action" value="approve_cash">
                                                    <input type="hidden" name="payment_id" value="<?= (int) $payment['paymentID'] ?>">
                                                    <button class="btn tiny" type="submit">Received cash</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post" style="margin:0;">
                                                    <?= attendance_csrf_field() ?>
                                                    <input type="hidden" name="action" value="verify">
                                                    <input type="hidden" name="payment_id" value="<?= (int) $payment['paymentID'] ?>">
                                                    <button class="btn tiny" type="submit">Verify online</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="post" style="margin:0;">
                                                <?= attendance_csrf_field() ?>
                                                <input type="hidden" name="action" value="decline">
                                                <input type="hidden" name="payment_id" value="<?= (int) $payment['paymentID'] ?>">
                                                <button class="btn red tiny" type="submit">Decline</button>
                                            </form>
                                        </div>
                                    <?php elseif ($status === 'Refund Pending'): ?>
                                        <form method="post" style="margin:0;" onsubmit="return confirm('Approve this refund? The fee will be marked as Refunded.');">
                                            <?= attendance_csrf_field() ?>
                                            <input type="hidden" name="action" value="refund">
                                            <input type="hidden" name="payment_id" value="<?= (int) $payment['paymentID'] ?>">
                                            <button class="btn tiny" type="submit">Approve refund</button>
                                        </form>
                                    <?php elseif ($status === 'Paid'): ?>
                                        <button class="btn ghost tiny" type="button" data-modal-open="viewPaymentModal<?= (int) $payment['paymentID'] ?>">View</button>
                                    <?php elseif ($isSelf): ?>
                                        <a class="btn ghost tiny nowrap" href="<?= h($attendanceBase) ?>/student/event-payment.php">Pay my fee</a>
                                    <?php else: ?>
                                        <span class="small muted nowrap">Waiting for student</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= attendance_pagination($currentUrl, ['event' => $eventId, 'status' => $statusFilter, 'q' => $search], $pager['page'], $pager['totalPages']) ?>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="infoBox">
            <h4><img src="<?= h($attendanceAssets) ?>/info.png" alt=""> How block payments work</h4>
            <ul>
                <li>Students pay one event at a time and choose cash or Online Payment (GCash) on their payment page.</li>
                <li>Cash is given to you as the block representative, then you approve it with the Received cash button.</li>
                <li>Online Payment reference numbers are digits only, and payments are verified by you or the administrator with the Verify online button.</li>
                <li>You are a student too: pay your own fee from Pay my own fee; an administrator reviews your payment.</li>
                <li>Payments are open from the payment opens date until the deadline set on the event, and a late fee shows as Late Payment.</li>
                <li>Free events have no payment record &mdash; students just join, no payment needed.</li>
                <li>Approving a payment records the full amount due and the exact time it was received.</li>
            </ul>
        </div>
    </section>
</div>

<?php
foreach (array_slice($payments, $pager['offset'], $pager['perPage']) as $payment):
    if ((string) $payment['paymentStatus'] !== 'Paid') {
        continue;
    }
    ?>
    <div class="modal" id="viewPaymentModal<?= (int) $payment['paymentID'] ?>" hidden>
        <div class="modalBackdrop"></div>
        <div class="modalPanel">
            <div class="modalHead">
                <div>
                    <h3><?= h(attendance_student_name($payment)) ?> &mdash; <?= h($payment['eventTitle']) ?></h3>
                    <p>Payment reference for <?= h($payment['eventTitle']) ?></p>
                </div>
                <button class="closeBtn" type="button" data-modal-close aria-label="Close">
                    <img src="<?= h($attendanceAssets) ?>/close.png" alt="">
                </button>
            </div>
            <div class="modalBody">
                <div class="detailGrid">
                    <div class="field"><span>Student</span><div class="value"><?= h(attendance_student_name($payment)) ?></div></div>
                    <div class="field"><span>Student ID</span><div class="value"><?= attendance_student_id($payment) ?></div></div>
                    <?php if (!empty($payment['classBlock'])): ?>
                        <div class="field"><span>Block</span><div class="value"><?= h($payment['classBlock']) ?></div></div>
                    <?php endif; ?>
                    <div class="field"><span>Event</span><div class="value"><?= h($payment['eventTitle']) ?></div></div>
                    <div class="field"><span>Event date</span><div class="value"><?= h(attendance_long_date($payment['eventDate'])) ?></div></div>
                    <div class="field"><span>Amount due</span><div class="value"><?= attendance_money((float) $payment['amountDue']) ?></div></div>
                    <div class="field"><span>Amount paid</span><div class="value"><?= attendance_money((float) $payment['amountPaid']) ?></div></div>
                    <div class="field"><span>Payment date</span><div class="value"><?= h(attendance_datetime($payment['paymentDate'])) ?></div></div>
                    <div class="field"><span>Payment status</span><div class="value"><?= h($payment['paymentStatus']) ?></div></div>
                    <div class="field"><span>Processed by</span><div class="value"><?= h(attendance_payment_processor($payment)) ?></div></div>
                </div>
                <?php if (!empty($payment['paymentRemarks'])): ?>
                    <p class="sub" style="margin-top:0.75rem;"><?= h($payment['paymentRemarks']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php attendance_page_end(); ?>
