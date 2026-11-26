<?php

declare(strict_types=1);

// Allows administrators to approve block attendance and update event payment records.
require_once __DIR__ . '/../auth/require_auth.php';
require_once __DIR__ . '/../auth/roles.php';

$userContext = requireAnyRole(['admin']);
$pdo = db();
$tab = (string) ($_GET['tab'] ?? 'approvals');
if (!in_array($tab, ['approvals', 'payments'], true)) {
    $tab = 'approvals';
}

if (empty($_SESSION['eventOperationsCsrf'])) {
    $_SESSION['eventOperationsCsrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) $_SESSION['eventOperationsCsrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid form token. Reload the page and try again.');
    }

    $action = (string) ($_POST['action'] ?? '');
    try {
        if (in_array($action, ['approve_attendance', 'deny_attendance'], true)) {
            $approvalId = (int) ($_POST['approvalID'] ?? 0);
            if ($approvalId < 1) {
                throw new DomainException('Select a valid attendance request.');
            }

            $approvalStatement = $pdo->prepare(
                "UPDATE tbleventattendanceapproval
                 SET approvalStatus = ?, reviewedByAdminID = ?, reviewedAt = NOW()
                 WHERE approvalID = ? AND approvalStatus = 'Pending'"
            );
            $approvalStatement->execute([
                $action === 'approve_attendance' ? 'Approved' : 'Denied',
                $userContext['id'],
                $approvalId,
            ]);
            if ($approvalStatement->rowCount() !== 1) {
                throw new DomainException('The request is no longer pending or could not be found.');
            }

            $_SESSION['eventOperationsFlash'] = $action === 'approve_attendance'
                ? 'Attendance request approved.'
                : 'Attendance request denied.';
            $_SESSION['eventOperationsTab'] = 'approvals';
        } elseif ($action === 'update_payment') {
            $paymentId = (int) ($_POST['paymentID'] ?? 0);
            $amountReceived = (string) ($_POST['amountReceived'] ?? '');
            $remarks = trim((string) ($_POST['paymentRemarks'] ?? ''));
            if ($paymentId < 1 || !preg_match('/\A[0-9]{1,8}(?:\.[0-9]{1,2})?\z/', $amountReceived)
                || (float) $amountReceived <= 0
            ) {
                throw new DomainException('Enter a payment amount greater than zero with at most two decimal places.');
            }
            if (mb_strlen($remarks) > 255) {
                throw new DomainException('Payment remarks must be 255 characters or fewer.');
            }

            $pdo->beginTransaction();
            $paymentStatement = $pdo->prepare(
                'SELECT amountDue, amountPaid FROM tbleventpayment WHERE paymentID = ? FOR UPDATE'
            );
            $paymentStatement->execute([$paymentId]);
            $payment = $paymentStatement->fetch();
            if (!$payment) {
                throw new DomainException('Payment record not found.');
            }

            $amountDueCents = (int) round((float) $payment['amountDue'] * 100);
            $amountPaidCents = (int) round((float) $payment['amountPaid'] * 100);
            $amountReceivedCents = (int) round((float) $amountReceived * 100);
            $newAmountPaidCents = $amountPaidCents + $amountReceivedCents;
            if ($newAmountPaidCents > $amountDueCents) {
                throw new DomainException('Payment would exceed the amount due.');
            }
            $paymentStatus = $amountDueCents === 0 || $newAmountPaidCents >= $amountDueCents
                ? 'Paid'
                : 'Partial';
            $receiptStatement = $pdo->prepare(
                'INSERT INTO tbleventpaymentreceipt
                    (paymentID, receivedByAdminID, amountReceived, paymentRemarks)
                 VALUES (?, ?, ?, ?)'
            );
            $receiptStatement->execute([
                $paymentId,
                $userContext['id'],
                number_format((float) $amountReceived, 2, '.', ''),
                $remarks === '' ? null : $remarks,
            ]);
            $updateStatement = $pdo->prepare(
                'UPDATE tbleventpayment
                 SET amountPaid = ?, paymentStatus = ?, paymentDate = NOW(),
                     paymentRemarks = ?, receivedByAdminID = ?
                 WHERE paymentID = ?'
            );
            $updateStatement->execute([
                number_format($newAmountPaidCents / 100, 2, '.', ''),
                $paymentStatus,
                $remarks === '' ? null : $remarks,
                $userContext['id'],
                $paymentId,
            ]);
            $pdo->commit();

            $_SESSION['eventOperationsFlash'] = 'Payment record updated.';
            $_SESSION['eventOperationsTab'] = 'payments';
        } else {
            http_response_code(400);
            exit('Invalid event operation.');
        }
    } catch (DomainException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['eventOperationsFlash'] = $exception->getMessage();
        $_SESSION['eventOperationsFlashError'] = true;
        $_SESSION['eventOperationsTab'] = $tab;
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Event administration action failed: ' . $exception->getMessage());
        $_SESSION['eventOperationsFlash'] = 'The event operation could not be saved.';
        $_SESSION['eventOperationsFlashError'] = true;
        $_SESSION['eventOperationsTab'] = $tab;
    }

    $nextTab = (string) ($_SESSION['eventOperationsTab'] ?? $tab);
    unset($_SESSION['eventOperationsTab']);
    header('Location: ./admin_event_operations.php?tab=' . rawurlencode($nextTab));
    exit;
}

$message = (string) ($_SESSION['eventOperationsFlash'] ?? '');
$isError = !empty($_SESSION['eventOperationsFlashError']);
unset($_SESSION['eventOperationsFlash'], $_SESSION['eventOperationsFlashError']);

$approvalStatement = $pdo->query(
    "SELECT a.approvalID, a.approvalStatus, a.requestedAt,
            e.eventTitle, e.eventDate, b.classBlock,
            s.studentFirstName, s.studentLastName
     FROM tbleventattendanceapproval a
     JOIN tblevent e ON e.eventID = a.eventID
     JOIN tblblock b ON b.blockID = a.blockID
     JOIN tblstudent s ON s.studentID = a.requestedByStudentID
     WHERE a.approvalStatus = 'Pending' AND e.archivedAt IS NULL
     ORDER BY a.requestedAt"
);
$approvals = $approvalStatement->fetchAll();

$paymentStatement = $pdo->query(
    "SELECT p.paymentID, p.amountDue, p.amountPaid, p.paymentStatus, p.paymentRemarks,
            r.registrationID, s.studentFirstName, s.studentLastName, b.classBlock,
            e.eventTitle, e.eventDate
     FROM tbleventpayment p
     JOIN tbleventregistration r ON r.registrationID = p.registrationID
     JOIN tblstudent s ON s.studentID = r.studentID
     JOIN tblblock b ON b.blockID = s.blockID
     JOIN tblevent e ON e.eventID = r.eventID
     WHERE e.archivedAt IS NULL
     ORDER BY e.eventDate DESC, e.eventTitle, s.studentLastName"
);
$payments = $paymentStatement->fetchAll();
$receiptStatement = $pdo->query(
    'SELECT paymentID, amountReceived, paymentRemarks, receivedAt
     FROM tbleventpaymentreceipt
     ORDER BY receivedAt DESC'
);
$receiptsByPayment = [];
foreach ($receiptStatement->fetchAll() as $receipt) {
    $receiptsByPayment[(int) $receipt['paymentID']][] = $receipt;
}

function eventOperationsEscape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light" data-sidebar="closed">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Operations</title>
    <link rel="stylesheet" href="./assets/dashboard.css?v=10">
    <link rel="stylesheet" href="./assets/sidebar.css?v=1">
</head>
<body class="comfsci-shell">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="content" style="max-width:1100px;margin:30px auto">
    <p><a href="./dashboard.php">← Back to dashboard</a></p>
    <section class="hero">
        <h1>Event operations</h1>
        <p>Review block attendance requests and maintain event fee records.</p>
    </section>
    <nav style="display:flex;gap:12px;margin:16px 0">
        <a class="btn btn-solid" href="?tab=approvals">Attendance approvals</a>
        <a class="btn btn-solid" href="?tab=payments">Payments</a>
        <a class="btn btn-solid" href="./admin_events.php">Manage events</a>
    </nav>
    <?php if ($message !== ''): ?>
        <p class="toast" role="status"<?= $isError ? ' style="background:#fff0ee;color:#8f2d22"' : '' ?>><?= eventOperationsEscape($message) ?></p>
    <?php endif; ?>

    <?php if ($tab === 'approvals'): ?>
        <section class="card" style="padding:20px">
            <h2>Pending attendance requests</h2>
            <?php if (!$approvals): ?><p class="empty">No requests need review.</p><?php endif; ?>
            <?php foreach ($approvals as $approval): ?>
                <article style="padding:14px 0;border-bottom:1px solid #e8e8e8">
                    <strong><?= eventOperationsEscape($approval['eventTitle']) ?></strong>
                    <p><?= eventOperationsEscape($approval['classBlock']) ?> · <?= eventOperationsEscape($approval['studentFirstName'] . ' ' . $approval['studentLastName']) ?> · <?= eventOperationsEscape((string) $approval['eventDate']) ?></p>
                    <form method="post" style="display:flex;gap:8px">
                        <input type="hidden" name="csrf" value="<?= eventOperationsEscape((string) $_SESSION['eventOperationsCsrf']) ?>">
                        <input type="hidden" name="approvalID" value="<?= (int) $approval['approvalID'] ?>">
                        <button class="btn btn-solid" name="action" value="approve_attendance">Approve</button>
                        <button class="btn" name="action" value="deny_attendance">Deny</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>
    <?php else: ?>
        <section class="card" style="padding:20px">
            <h2>Event payments</h2>
            <?php if (!$payments): ?><p class="empty">There are no event registrations.</p><?php endif; ?>
            <?php foreach ($payments as $payment): ?>
                <article style="padding:14px 0;border-bottom:1px solid #e8e8e8">
                    <strong><?= eventOperationsEscape($payment['eventTitle']) ?></strong>
                    <p><?= eventOperationsEscape($payment['classBlock']) ?> · <?= eventOperationsEscape($payment['studentFirstName'] . ' ' . $payment['studentLastName']) ?> · <?= eventOperationsEscape((string) $payment['eventDate']) ?></p>
                    <p>Due: <?= eventOperationsEscape(number_format((float) $payment['amountDue'], 2)) ?> · Paid: <?= eventOperationsEscape(number_format((float) $payment['amountPaid'], 2)) ?> · <?= eventOperationsEscape($payment['paymentStatus']) ?></p>
                    <form method="post" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
                        <input type="hidden" name="csrf" value="<?= eventOperationsEscape((string) $_SESSION['eventOperationsCsrf']) ?>">
                        <input type="hidden" name="paymentID" value="<?= (int) $payment['paymentID'] ?>">
                        <input type="hidden" name="action" value="update_payment">
                        <label>New payment received
                            <input type="number" name="amountReceived" min="0.01" max="<?= eventOperationsEscape((string) max(0, (float) $payment['amountDue'] - (float) $payment['amountPaid'])) ?>" step="0.01" required>
                        </label>
                        <label>Remarks
                            <input type="text" name="paymentRemarks" maxlength="255" value="<?= eventOperationsEscape($payment['paymentRemarks']) ?>">
                        </label>
                        <button class="btn btn-solid" type="submit">Save payment</button>
                    </form>
                    <?php if (!empty($receiptsByPayment[(int) $payment['paymentID']])): ?>
                        <details style="margin-top:10px">
                            <summary>Payment history</summary>
                            <?php foreach ($receiptsByPayment[(int) $payment['paymentID']] as $receipt): ?>
                                <p><?= eventOperationsEscape((string) $receipt['receivedAt']) ?> · <?= eventOperationsEscape(number_format((float) $receipt['amountReceived'], 2)) ?><?= $receipt['paymentRemarks'] ? ' · ' . eventOperationsEscape($receipt['paymentRemarks']) : '' ?></p>
                            <?php endforeach; ?>
                        </details>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/ai_chat_widget.php'; ?>
</body>
</html>
