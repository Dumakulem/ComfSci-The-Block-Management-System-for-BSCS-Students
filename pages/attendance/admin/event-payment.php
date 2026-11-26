<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/attendance-base.php';

attendance_guard('admin');

$currentUrl = $attendanceBase . '/admin/event-payment.php';

$eventId = (int) ($_GET['event'] ?? 0);
$courseId = (int) ($_GET['course'] ?? 0);
// block name ang filter (A, B, C, D) hindi blockID, para pagsama-samahin
// lahat ng block A ng lahat ng course kapag All courses
$blockName = trim((string) ($_GET['block'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? 'all');
$search = trim((string) ($_GET['q'] ?? ''));

if (!in_array($statusFilter, ['all', 'Unpaid', 'Pending Cash', 'For Verification', 'Paid', 'Refund Pending', 'Refunded', 'Declined', 'Late Payment'], true)) {
    $statusFilter = 'all';
}

$filterQuery = '?event=' . $eventId . '&course=' . $courseId . '&block=' . urlencode($blockName)
    . '&status=' . urlencode($statusFilter) . '&q=' . urlencode($search);

/* ------------------------------------------------------------------
   Dito nagde-decide ang admin sa payments ng buong campus
------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    attendance_check_csrf();

    $paymentId = (int) ($_POST['payment_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    try {
        $check = $pdo->prepare(
            'SELECT p.paymentID, p.paymentStatus, e.eventStatus, e.paymentDeadline
             FROM tbleventpayment p
             JOIN tbleventregistration r ON r.registrationID = p.registrationID
             JOIN tblevent e ON e.eventID = r.eventID
             WHERE p.paymentID = ?'
        );
        $check->execute([$paymentId]);
        $payment = $check->fetch();

        if ($payment === false) {
            attendance_flash('error', 'That payment record could not be found.');
        } elseif ((string) $payment['eventStatus'] === 'Cancelled' && $action !== 'refund') {
            // cancelled na event = sarado na lahat ng payments, refund approval lang ang pwede
            attendance_flash('error', 'That event is cancelled, so its payments are already closed.');
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
                             processedByAdminID = ?, processedByStudentID = NULL,
                             paymentRemarks = CONCAT(LEFT(COALESCE(paymentRemarks, ''), 160), ' | Refund approved by admin')
                         WHERE paymentID = ? AND paymentStatus = 'Refund Pending'"
                    );
                    $update->execute([$viewerId, $paymentId]);
                    attendance_flash('success', 'Refund approved. The payment is now marked as Refunded.');
                }
            } elseif ($action === 'verify') {
                if ($currentStatus !== 'For Verification') {
                    attendance_flash('error', 'Only online payments waiting for verification can be verified.');
                } else {
                    $update = $pdo->prepare(
                        "UPDATE tbleventpayment
                         SET amountPaid = amountDue, paymentStatus = 'Paid', paymentDate = NOW(),
                             receivedByAdminID = ?,
                             processedByAdminID = ?, processedByStudentID = NULL,
                             paymentRemarks = CONCAT(LEFT(COALESCE(paymentRemarks, ''), 100), ' | Online payment verified', ?)
                         WHERE paymentID = ? AND paymentStatus = 'For Verification'"
                    );
                    $update->execute([$viewerId, $viewerId, $lateNote, $paymentId]);
                    attendance_flash('success', 'Online payment verified and marked as paid.');
                }
            } elseif ($action === 'mark_paid') {
                if ($currentStatus === 'Paid') {
                    attendance_flash('error', 'That payment is already paid.');
                } elseif (in_array($currentStatus, ['Refund Pending', 'Refunded'], true)) {
                    attendance_flash('error', 'That fee is being refunded because the event was cancelled.');
                } else {
                    $update = $pdo->prepare(
                        "UPDATE tbleventpayment
                         SET amountPaid = amountDue, paymentStatus = 'Paid', paymentDate = NOW(),
                             receivedByAdminID = ?,
                             processedByAdminID = ?, processedByStudentID = NULL,
                             paymentRemarks = CONCAT(LEFT(COALESCE(paymentRemarks, ''), 100), ' | Cash received at the office', ?)
                         WHERE paymentID = ?"
                    );
                    $update->execute([$viewerId, $viewerId, $lateNote, $paymentId]);
                    attendance_flash('success', 'Payment marked as paid.');
                }
            } elseif ($action === 'decline') {
                if (!in_array($currentStatus, ['Pending Cash', 'For Verification'], true)) {
                    attendance_flash('error', 'Only submitted payments can be declined.');
                } else {
                    $update = $pdo->prepare(
                        "UPDATE tbleventpayment
                         SET paymentStatus = 'Declined',
                             processedByAdminID = ?, processedByStudentID = NULL,
                             paymentRemarks = CONCAT(LEFT(COALESCE(paymentRemarks, ''), 130), ' | Declined by admin')
                         WHERE paymentID = ?"
                    );
                    $update->execute([$viewerId, $paymentId]);
                    attendance_flash('success', 'Payment declined. The student can submit it again.');
                }
            } else {
                attendance_flash('error', 'Unknown action.');
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

$courses = $pdo->query('SELECT courseID, courseName FROM tblcourse ORDER BY courseName')->fetchAll();

/* Blocks sa dropdown:
   - may napiling course -> blocks lang ng course na yun
   - All courses -> isa lang bawat name (A, B, C, D) para hindi dumoble */
$blockSql = 'SELECT DISTINCT classBlock FROM tblblock';
$blockParams = [];
if ($courseId > 0) {
    $blockSql .= ' WHERE courseID = ?';
    $blockParams[] = $courseId;
}
$blockSql .= ' ORDER BY classBlock';
$blockStatement = $pdo->prepare($blockSql);
$blockStatement->execute($blockParams);
$blocks = $blockStatement->fetchAll();

// nagpalit ng course tapos wala na yung block, balik sa All blocks
if ($blockName !== '' && !in_array($blockName, array_column($blocks, 'classBlock'), true)) {
    $blockName = '';
}

$sql = "SELECT p.*, r.registrationID, r.registrationStatus,
               s.studentID, s.studentFirstName, s.studentLastName, s.studentMiddleName, s.studentExtensionName,
               b.classBlock, c.courseName,
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
        LEFT JOIN tblcourse c ON c.courseID = b.courseID
        LEFT JOIN tbladmin processorAdmin ON processorAdmin.adminID = p.processedByAdminID
        LEFT JOIN tblstudent processorStudent ON processorStudent.studentID = p.processedByStudentID
        LEFT JOIN tbladmin receiverAdmin ON receiverAdmin.adminID = p.receivedByAdminID
        WHERE e.archivedAt IS NULL";

$params = [];

if ($eventId > 0) {
    $sql .= ' AND e.eventID = ?';
    $params[] = $eventId;
}

if ($courseId > 0) {
    $sql .= ' AND b.courseID = ?';
    $params[] = $courseId;
}

if ($blockName !== '') {
    // classBlock compare para sama-sama lahat ng A pag All courses
    $sql .= ' AND b.classBlock = ?';
    $params[] = $blockName;
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

$sql .= ' ORDER BY e.eventDate DESC, c.courseName, b.classBlock, s.studentLastName';

$paymentsStatement = $pdo->prepare($sql);
$paymentsStatement->execute($params);
$payments = $paymentsStatement->fetchAll();

$totalDue = 0.00;
$totalPaid = 0.00;
foreach ($payments as $payment) {
    $totalDue += (float) $payment['amountDue'];
    $totalPaid += (float) $payment['amountPaid'];
}

$counts = attendance_payment_counts($payments);
$outstanding = max(0, $totalDue - $totalPaid);
$pager = attendance_paginate(count($payments));

attendance_page_start('payment');
?>

<div class="stack">
    <div class="highlightGrid">
        <section class="highlight green">
            <div class="eyebrow">Paid</div>
            <strong><?= $counts['paid'] ?> student<?= $counts['paid'] === 1 ? '' : 's' ?></strong>
            <small><?= attendance_money($totalPaid) ?> collected</small>
        </section>
        <section class="highlight red">
            <div class="eyebrow">Unpaid</div>
            <strong><?= $counts['unpaid'] ?> student<?= $counts['unpaid'] === 1 ? '' : 's' ?></strong>
            <small><?= $counts['pending'] ?> waiting for approval &middot; <?= $counts['late'] ?> late</small>
        </section>
        <section class="highlight light">
            <div>
                <div class="eyebrow">Outstanding</div>
                <strong><?= attendance_money($outstanding) ?></strong>
                <small class="muted">Of <?= attendance_money($totalDue) ?> due</small>
            </div>
            <img src="<?= h($attendanceAssets) ?>/wallet.png" alt="" style="width:2.2rem;height:2.2rem;object-fit:contain;opacity:0.7;">
        </section>
    </div>

    <section class="card">
        <div class="cardHead">
            <img src="<?= h($attendanceAssets) ?>/card.png" alt="">
            <h3>Event payments</h3>
            <span class="spacer"></span>
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

                <select class="selectInline" name="course" aria-label="Filter by course" onchange="this.form.submit()">
                    <option value="0">All courses</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= (int) $course['courseID'] ?>" <?= (int) $course['courseID'] === $courseId ? 'selected' : '' ?>>
                            <?= h($course['courseName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select class="selectInline" name="block" aria-label="Filter by block" onchange="this.form.submit()">
                    <option value="">All blocks</option>
                    <?php foreach ($blocks as $block): ?>
                        <?php $blockOption = (string) $block['classBlock']; ?>
                        <option value="<?= h($blockOption) ?>" <?= $blockOption === $blockName ? 'selected' : '' ?>>
                            <?= h($blockOption) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select class="selectInline" name="status" aria-label="Filter by status">
                    <?php foreach (['all' => 'All statuses', 'Unpaid' => 'Unpaid', 'Late Payment' => 'Late Payment', 'Pending Cash' => 'Pending Cash', 'For Verification' => 'For Verification', 'Paid' => 'Paid', 'Refund Pending' => 'Refund Pending', 'Refunded' => 'Refunded', 'Declined' => 'Declined'] as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>

                <input class="searchInline" type="search" name="q" value="<?= h($search) ?>" placeholder="Search student name or ID">

                <button class="btn ghost tiny" type="submit">
                    <img src="<?= h($attendanceAssets) ?>/filter.png" alt="">
                    Apply
                </button>

                <?php if ($eventId > 0 || $courseId > 0 || $blockName !== '' || $statusFilter !== 'all' || $search !== ''): ?>
                    <a class="btn gray tiny" href="<?= h($currentUrl) ?>">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (count($payments) === 0): ?>
            <div class="emptyState">
                <img src="<?= h($attendanceAssets) ?>/wallet.png" alt="">
                <strong>No payment records found</strong>
                <p>Change the filters or wait for students to register for paid events.</p>
            </div>
        <?php else: ?>
            <div class="tableWrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course / Block</th>
                            <th>Student ID</th>
                            <th>Event</th>
                            <th>Due</th>
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
                            ?>
                            <tr>
                                <td class="strong"><?= h(attendance_student_name($payment)) ?></td>
                                <td>
                                    <?= h((string) ($payment['courseName'] ?? '-')) ?>
                                    <span class="sub"><?= h((string) ($payment['classBlock'] ?? '-')) ?></span>
                                </td>
                                <td class="nowrap"><?= attendance_student_id($payment) ?></td>
                                <td>
                                    <?= h($payment['eventTitle']) ?>
                                    <span class="sub"><?= h(attendance_long_date($payment['eventDate'])) ?></span>
                                </td>
                                <td class="nowrap"><?= attendance_money((float) $payment['amountDue']) ?></td>
                                <td class="nowrap"><?= attendance_money((float) $payment['amountPaid']) ?></td>
                                <td class="nowrap"><?= $payment['paymentDeadline'] ? h(attendance_long_date((string) $payment['paymentDeadline'])) : '<span class="small muted">No deadline</span>' ?></td>
                                <td>
                                    <?= attendance_badge($displayStatus) ?>
                                    <span class="sub"><?= h((string) ($payment['paymentRemarks'] ?? '')) ?></span>
                                    <span class="sub"><?= h(attendance_payment_processor($payment)) ?></span>
                                </td>
                                <td>
                                    <div class="btnRow">
                                        <?php if ($status === 'For Verification'): ?>
                                            <form method="post" style="margin:0;">
                                                <?= attendance_csrf_field() ?>
                                                <input type="hidden" name="action" value="verify">
                                                <input type="hidden" name="payment_id" value="<?= (int) $payment['paymentID'] ?>">
                                                <button class="btn tiny" type="submit">Verify online payment</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (in_array($status, ['Unpaid', 'Pending Cash', 'Declined', 'Overdue'], true)): ?>
                                            <form method="post" style="margin:0;">
                                                <?= attendance_csrf_field() ?>
                                                <input type="hidden" name="action" value="mark_paid">
                                                <input type="hidden" name="payment_id" value="<?= (int) $payment['paymentID'] ?>">
                                                <button class="btn ghost tiny" type="submit">Receive cash</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (in_array($status, ['Pending Cash', 'For Verification'], true)): ?>
                                            <form method="post" style="margin:0;">
                                                <?= attendance_csrf_field() ?>
                                                <input type="hidden" name="action" value="decline">
                                                <input type="hidden" name="payment_id" value="<?= (int) $payment['paymentID'] ?>">
                                                <button class="btn red tiny" type="submit">Decline</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($status === 'Refund Pending'): ?>
                                            <form method="post" style="margin:0;" onsubmit="return confirm('Approve this refund? The fee will be marked as Refunded.');">
                                                <?= attendance_csrf_field() ?>
                                                <input type="hidden" name="action" value="refund">
                                                <input type="hidden" name="payment_id" value="<?= (int) $payment['paymentID'] ?>">
                                                <button class="btn tiny" type="submit">Approve refund</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($status === 'Paid'): ?>
                                            <button class="btn ghost tiny" type="button" data-modal-open="viewPaymentModal<?= (int) $payment['paymentID'] ?>">View</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= attendance_pagination($currentUrl, ['event' => $eventId, 'course' => $courseId, 'block' => $blockName, 'status' => $statusFilter, 'q' => $search], $pager['page'], $pager['totalPages']) ?>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="infoBox">
            <h4><img src="<?= h($attendanceAssets) ?>/info.png" alt=""> Payment handling</h4>
            <ul>
                <li>Students pay between the payment start date and the deadline set on the event.</li>
                <li>After the deadline an unpaid fee shows as Late Payment, and a fee paid late is saved with a late payment remark.</li>
                <li>Online Payments (GCash) become For Verification after the student submits a reference number.</li>
                <li>Cash payments are approved by the block representative, and the administrator can also mark them paid.</li>
                <li>The block representative can approve cash and verify online payments of their own block.</li>
                <li>Free events do not create a payment record &mdash; students just register and join.</li>
                <li>Declined payments go back to the student so they can submit again.</li>
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
                    <div class="field"><span>Course</span><div class="value"><?= h((string) ($payment['courseName'] ?? '-')) ?></div></div>
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
