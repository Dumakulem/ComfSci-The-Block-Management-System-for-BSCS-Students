<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../libs/PHPMailer/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/SMTP.php';

function sendMail(string $to, string $subject, string $htmlBody): bool
 {
    $cfg  = require __DIR__ . '/../config/mail.php';
    $mail = new PHPMailer(true);   // true = throw exceptions

    try {
        $mail->isSMTP();
        $mail->Host       = $cfg['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['username'];
        $mail->Password   = $cfg['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $cfg['port'];

        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);   // plain-text fallback
        //$mail->SMTPDebug = 2;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mail error: ' . $mail->ErrorInfo);
        echo '<pre>Mail error: ' . htmlspecialchars($mail->ErrorInfo) . '</pre>';  // TEMPORARY

    return false;
    }
}

function verifyEmail(string $email): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Invalid email address.'];
    }

    // 60-second cooldown so nobody can spam the send button.
    if (isset($_SESSION['email_code']) && time() - $_SESSION['email_code']['sent'] < 60) {
        return ['ok' => false, 'message' => 'Please wait a minute before requesting a new code.'];
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $html = '<p>Your verification code is:</p>'
          . '<p style="font-size:28px;font-weight:bold;letter-spacing:6px;">' . $code . '</p>'
          . '<p>It expires in 3 minutes.</p>';

    if (!sendMail($email, 'Your verification code', $html)) {
        return ['ok' => false, 'message' => 'Could not send the email. Try again later.'];
    }

    $_SESSION['email_code'] = [
        'email'    => $email,
        'hash'     => hash('sha256', $code),   // never store the plain code
        'expires'  => time() + 180,            // 3 minutes
        'sent'     => time(),
        'attempts' => 0,
    ];

    return ['ok' => true, 'message' => 'Code sent. It expires in 3 minutes.'];
}

function checkEmailCode(string $code): array
 {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $max  = 5;   // wrong guesses allowed before the code is thrown away
    $data = $_SESSION['email_code'] ?? null;

    if (!$data) {
        return ['ok' => false, 'reason' => 'none', 'message' => 'No code requested yet. Please request a new one.'];
    }

    if (time() > $data['expires']) {
        unset($_SESSION['email_code']);
        return ['ok' => false, 'reason' => 'expired', 'message' => 'This code has expired. Please request a new one.'];
    }

    $attempt = ++$_SESSION['email_code']['attempts'];

    if (hash_equals($data['hash'], hash('sha256', trim($code)))) {
        $email = $data['email'];
        unset($_SESSION['email_code']);   // single use
        return ['ok' => true, 'message' => 'Email verified.', 'email' => $email];
    }

    if ($attempt >= $max) {
        unset($_SESSION['email_code']);
        return ['ok' => false, 'reason' => 'locked', 'attempt' => $max, 'max' => $max,
                'message' => 'Too many wrong tries. Please request a new code.'];
    }

    return ['ok' => false, 'reason' => 'wrong', 'attempt' => $attempt, 'max' => $max,
            'message' => 'Wrong code. Please try again.'];
}