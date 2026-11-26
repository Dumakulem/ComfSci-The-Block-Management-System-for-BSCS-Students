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

    // ---- Email design (only this part changed) ----
    $font = '-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica Neue,Arial,sans-serif';

    // One box per digit, like the code boxes in the web app
    $cells = [];
    foreach (str_split($code) as $digit) {
        $cells[] = '<td class="digit" width="44" height="56" align="center" valign="middle" bgcolor="#fff1e6" '
                 . 'style="width:44px;height:56px;background-color:#fff1e6;border:1px solid #ffd9bd;border-radius:10px;'
                 . 'font-family:Arial,Helvetica,sans-serif;font-size:28px;font-weight:700;color:#17171a;">'
                 . $digit . '</td>';
    }
    $boxes = implode('<td width="6" style="width:6px;font-size:0;line-height:0;">&nbsp;</td>', $cells);

    $html = <<<HTML
 <!DOCTYPE html>
 <html lang="en">
 <head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <meta name="color-scheme" content="light">
 <title>Your verification code</title>
 <style>
 @media only screen and (max-width:480px) {
  .px { padding-left:20px !important; padding-right:20px !important; }
  .digit { width:40px !important; height:52px !important; font-size:24px !important; }
 }
 </style>
 </head>
 <body style="margin:0;padding:0;background-color:#f5f3f0;">

 <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
  Your verification code is {$code}. It expires in 3 minutes.
 </div>

 <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f5f3f0" style="background-color:#f5f3f0;">
 <tr><td align="center" style="padding:32px 16px;">

  <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:560px;background-color:#ffffff;border:1px solid #e6e1db;border-radius:16px;overflow:hidden;">

    <!-- Header -->
    <tr>
      <td class="px" bgcolor="#0e0e10" style="background-color:#0e0e10;padding:24px 32px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td width="36" valign="middle">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td width="36" height="36" align="center" valign="middle" bgcolor="#ff6a00" style="width:36px;height:36px;background-color:#ff6a00;border-radius:10px;font-family:{$font};font-size:18px;font-weight:700;color:#ffffff;">A</td>
                </tr>
              </table>
            </td>
            <td valign="middle" style="padding-left:12px;font-family:{$font};font-size:17px;font-weight:700;color:#ffffff;">Assignment Tracker</td>
            <td align="right" valign="middle" style="font-family:{$font};font-size:12px;color:#9a9aa2;">Email verification</td>
          </tr>
        </table>
      </td>
    </tr>
    <tr><td height="4" bgcolor="#ff6a00" style="height:4px;line-height:4px;font-size:0;background-color:#ff6a00;">&nbsp;</td></tr>

    <!-- Message and code -->
    <tr>
      <td class="px" style="padding:36px 32px 8px;font-family:{$font};">
        <h1 style="margin:0 0 10px;font-family:{$font};font-size:24px;line-height:1.3;font-weight:700;color:#17171a;">Verify your email</h1>
        <p style="margin:0;font-family:{$font};font-size:15px;line-height:1.6;color:#6b6b72;">
          Use the code below to confirm your email address and start receiving assignment reminders.
        </p>

        <p style="margin:28px 0 12px;font-family:{$font};font-size:12px;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:#6b6b72;text-align:center;">
          Your verification code
        </p>

        <table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;">
          <tr>{$boxes}</tr>
        </table>

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;">
          <tr>
            <td align="center" bgcolor="#fbf6f1" style="background-color:#fbf6f1;border:1px solid #e6e1db;border-radius:12px;padding:14px 16px;font-family:{$font};font-size:14px;color:#6b6b72;">
              This code expires in <strong style="color:#c24e00;">3 minutes</strong>.
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Divider -->
    <tr>
      <td class="px" style="padding:24px 32px 0;">
        <div style="height:1px;line-height:1px;font-size:0;background-color:#e6e1db;">&nbsp;</div>
      </td>
    </tr>

    <!-- Security note -->
    <tr>
      <td class="px" style="padding:20px 32px 32px;font-family:{$font};font-size:13px;line-height:1.6;color:#6b6b72;">
        <strong style="color:#17171a;">Didn&rsquo;t request this?</strong>
        You can safely ignore this email. For your security, never share this code with anyone.
      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td class="px" align="center" bgcolor="#fbf6f1" style="background-color:#fbf6f1;border-top:1px solid #e6e1db;padding:18px 32px;font-family:{$font};font-size:12px;line-height:1.6;color:#6b6b72;">
        This is an automated message from Assignment Tracker. Please do not reply to this email.
      </td>
    </tr>

  </table>

 </td></tr>
 </table>

 </body>
</html>
HTML;

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

function buildOverdueEmail(string $subjectName, string $assignmentTitle, string $dueDate): string
{
    $font = '-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica Neue,Arial,sans-serif';

    $subjectName     = htmlspecialchars($subjectName, ENT_QUOTES, 'UTF-8');
    $assignmentTitle = htmlspecialchars($assignmentTitle, ENT_QUOTES, 'UTF-8');
    $dueDate         = htmlspecialchars($dueDate, ENT_QUOTES, 'UTF-8');

    return <<<HTML
 <!DOCTYPE html>
 <html lang="en">
 <head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <meta name="color-scheme" content="light">
 <title>Assignment overdue</title>
 <style>
 @media only screen and (max-width:480px) {
  .px { padding-left:20px !important; padding-right:20px !important; }
 }
 </style>
 </head>
 <body style="margin:0;padding:0;background-color:#f5f3f0;">

 <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
  Overdue: {$assignmentTitle} ({$subjectName}) was due {$dueDate}.
 </div>

 <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f5f3f0" style="background-color:#f5f3f0;">
 <tr><td align="center" style="padding:32px 16px;">

  <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:560px;background-color:#ffffff;border:1px solid #e6e1db;border-radius:16px;overflow:hidden;">

    <!-- Header -->
    <tr>
      <td class="px" bgcolor="#0e0e10" style="background-color:#0e0e10;padding:24px 32px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td width="36" valign="middle">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td width="36" height="36" align="center" valign="middle" bgcolor="#ff6a00" style="width:36px;height:36px;background-color:#ff6a00;border-radius:10px;font-family:{$font};font-size:18px;font-weight:700;color:#ffffff;">A</td>
                </tr>
              </table>
            </td>
            <td valign="middle" style="padding-left:12px;font-family:{$font};font-size:17px;font-weight:700;color:#ffffff;">Assignment Tracker</td>
            <td align="right" valign="middle" style="font-family:{$font};font-size:12px;color:#9a9aa2;">Overdue notice</td>
          </tr>
        </table>
      </td>
    </tr>
    <tr><td height="4" bgcolor="#ff6a00" style="height:4px;line-height:4px;font-size:0;background-color:#ff6a00;">&nbsp;</td></tr>

    <!-- Message -->
    <tr>
      <td class="px" style="padding:36px 32px 8px;font-family:{$font};">

        <!-- Overdue badge -->
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td bgcolor="#fef3f2" style="background-color:#fef3f2;border:1px solid #fecdca;border-radius:999px;padding:5px 14px;font-family:{$font};font-size:12px;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:#d92d20;">
              &#9679;&nbsp; Overdue
            </td>
          </tr>
        </table>

        <h1 style="margin:16px 0 10px;font-family:{$font};font-size:24px;line-height:1.3;font-weight:700;color:#17171a;">Overdue</h1>
        <p style="margin:0;font-family:{$font};font-size:15px;line-height:1.6;color:#6b6b72;">
          The assignment below has passed its due date and has not been submitted yet.
        </p>

        <!-- Details card -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;">
          <tr>
            <td bgcolor="#fbf6f1" style="background-color:#fbf6f1;border:1px solid #e6e1db;border-radius:12px;padding:20px;">

              <p style="margin:0 0 4px;font-family:{$font};font-size:12px;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:#6b6b72;">Subject</p>
              <p style="margin:0;font-family:{$font};font-size:16px;line-height:1.4;font-weight:700;color:#17171a;">{$subjectName}</p>

              <div style="height:1px;line-height:1px;font-size:0;background-color:#e6e1db;margin:16px 0;">&nbsp;</div>

              <p style="margin:0 0 4px;font-family:{$font};font-size:12px;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:#6b6b72;">Assignment title</p>
              <p style="margin:0;font-family:{$font};font-size:16px;line-height:1.4;font-weight:700;color:#17171a;">{$assignmentTitle}</p>

              <div style="height:1px;line-height:1px;font-size:0;background-color:#e6e1db;margin:16px 0;">&nbsp;</div>

              <p style="margin:0 0 4px;font-family:{$font};font-size:12px;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:#6b6b72;">Due date</p>
              <p style="margin:0;font-family:{$font};font-size:16px;line-height:1.4;font-weight:700;color:#d92d20;">{$dueDate}</p>

            </td>
          </tr>
        </table>

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:16px;">
          <tr>
            <td align="center" bgcolor="#fff1e6" style="background-color:#fff1e6;border:1px solid #ffd9bd;border-radius:12px;padding:14px 16px;font-family:{$font};font-size:14px;color:#6b6b72;">
              Please submit it <strong style="color:#c24e00;">as soon as possible</strong>.
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Divider -->
    <tr>
      <td class="px" style="padding:24px 32px 0;">
        <div style="height:1px;line-height:1px;font-size:0;background-color:#e6e1db;">&nbsp;</div>
      </td>
    </tr>

    <!-- Note -->
    <tr>
      <td class="px" style="padding:20px 32px 32px;font-family:{$font};font-size:13px;line-height:1.6;color:#6b6b72;">
        <strong style="color:#17171a;">Already submitted?</strong>
        You can safely ignore this email.
      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td class="px" align="center" bgcolor="#fbf6f1" style="background-color:#fbf6f1;border-top:1px solid #e6e1db;padding:18px 32px;font-family:{$font};font-size:12px;line-height:1.6;color:#6b6b72;">
        This is an automated message from Assignment Tracker. Please do not reply to this email.
      </td>
    </tr>

  </table>

 </td></tr>
 </table>

 </body>
</html>
HTML;
}

function buildNewAssignmentEmail(string $subjectName, string $assignmentTitle, string $postDate): string
 {
    $font = '-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica Neue,Arial,sans-serif';

    $subjectName     = htmlspecialchars($subjectName, ENT_QUOTES, 'UTF-8');
    $assignmentTitle = htmlspecialchars($assignmentTitle, ENT_QUOTES, 'UTF-8');
    $postDate        = htmlspecialchars($postDate, ENT_QUOTES, 'UTF-8');

    return <<<HTML
 <!DOCTYPE html>
 <html lang="en">
 <head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <meta name="color-scheme" content="light">
 <title>New assignment</title>
 <style>
 @media only screen and (max-width:480px) {
  .px { padding-left:20px !important; padding-right:20px !important; }
 }
 </style>
 </head>
 <body style="margin:0;padding:0;background-color:#f5f3f0;">

 <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
  New assignment posted: {$assignmentTitle} ({$subjectName}).
 </div>

 <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f5f3f0" style="background-color:#f5f3f0;">
 <tr><td align="center" style="padding:32px 16px;">

  <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:560px;background-color:#ffffff;border:1px solid #e6e1db;border-radius:16px;overflow:hidden;">

    <!-- Header -->
    <tr>
      <td class="px" bgcolor="#0e0e10" style="background-color:#0e0e10;padding:24px 32px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td width="36" valign="middle">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td width="36" height="36" align="center" valign="middle" bgcolor="#ff6a00" style="width:36px;height:36px;background-color:#ff6a00;border-radius:10px;font-family:{$font};font-size:18px;font-weight:700;color:#ffffff;">A</td>
                </tr>
              </table>
            </td>
            <td valign="middle" style="padding-left:12px;font-family:{$font};font-size:17px;font-weight:700;color:#ffffff;">Assignment Tracker</td>
            <td align="right" valign="middle" style="font-family:{$font};font-size:12px;color:#9a9aa2;">New assignment</td>
          </tr>
        </table>
      </td>
    </tr>
    <tr><td height="4" bgcolor="#ff6a00" style="height:4px;line-height:4px;font-size:0;background-color:#ff6a00;">&nbsp;</td></tr>

    <!-- Message -->
    <tr>
      <td class="px" style="padding:36px 32px 8px;font-family:{$font};">

        <!-- New badge -->
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td bgcolor="#fff1e6" style="background-color:#fff1e6;border:1px solid #ffd9bd;border-radius:999px;padding:5px 14px;font-family:{$font};font-size:12px;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:#c24e00;">
              &#9679;&nbsp; New
            </td>
          </tr>
        </table>

        <h1 style="margin:16px 0 10px;font-family:{$font};font-size:24px;line-height:1.3;font-weight:700;color:#17171a;">A new assignment was posted</h1>
        <p style="margin:0;font-family:{$font};font-size:15px;line-height:1.6;color:#6b6b72;">
          A new assignment is now available in your Assignment Tracker. Here are the details.
        </p>

        <!-- Details card -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;">
          <tr>
            <td bgcolor="#fbf6f1" style="background-color:#fbf6f1;border:1px solid #e6e1db;border-radius:12px;padding:20px;">

              <p style="margin:0 0 4px;font-family:{$font};font-size:12px;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:#6b6b72;">Subject</p>
              <p style="margin:0;font-family:{$font};font-size:16px;line-height:1.4;font-weight:700;color:#17171a;">{$subjectName}</p>

              <div style="height:1px;line-height:1px;font-size:0;background-color:#e6e1db;margin:16px 0;">&nbsp;</div>

              <p style="margin:0 0 4px;font-family:{$font};font-size:12px;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:#6b6b72;">Assignment title</p>
              <p style="margin:0;font-family:{$font};font-size:16px;line-height:1.4;font-weight:700;color:#17171a;">{$assignmentTitle}</p>

              <div style="height:1px;line-height:1px;font-size:0;background-color:#e6e1db;margin:16px 0;">&nbsp;</div>

              <p style="margin:0 0 4px;font-family:{$font};font-size:12px;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:#6b6b72;">Date posted</p>
              <p style="margin:0;font-family:{$font};font-size:16px;line-height:1.4;font-weight:700;color:#c24e00;">{$postDate}</p>

            </td>
          </tr>
        </table>

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:16px;">
          <tr>
            <td align="center" bgcolor="#fff1e6" style="background-color:#fff1e6;border:1px solid #ffd9bd;border-radius:12px;padding:14px 16px;font-family:{$font};font-size:14px;color:#6b6b72;">
              Log in to <strong style="color:#c24e00;">Assignment Tracker</strong> to view the full instructions.
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Divider -->
    <tr>
      <td class="px" style="padding:24px 32px 0;">
        <div style="height:1px;line-height:1px;font-size:0;background-color:#e6e1db;">&nbsp;</div>
      </td>
    </tr>

    <!-- Note -->
    <tr>
      <td class="px" style="padding:20px 32px 32px;font-family:{$font};font-size:13px;line-height:1.6;color:#6b6b72;">
        <strong style="color:#17171a;">Why am I getting this?</strong>
        You receive this email because your account is set up for assignment reminders.
      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td class="px" align="center" bgcolor="#fbf6f1" style="background-color:#fbf6f1;border-top:1px solid #e6e1db;padding:18px 32px;font-family:{$font};font-size:12px;line-height:1.6;color:#6b6b72;">
        This is an automated message from Assignment Tracker. Please do not reply to this email.
      </td>
    </tr>

  </table>

 </td></tr>
 </table>

 </body>
 </html>
 HTML;
}