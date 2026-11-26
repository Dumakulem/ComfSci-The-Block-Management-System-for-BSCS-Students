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
        if (empty($cfg['host']) || empty($cfg['username']) || empty($cfg['password']) || empty($cfg['from_email'])) {
        error_log('Assignment Tracker email is not configured. Set COMFSCI_SMTP_* environment variables.');
        return false;
    }
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

    return false;
    }
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
function buildUpcomingEmail(string $studentName, string $assignmentTitle, string $subjectName, string $dueDate): string
{
    $studentName = htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8');
    $assignmentTitle = htmlspecialchars($assignmentTitle, ENT_QUOTES, 'UTF-8');
    $subjectName = htmlspecialchars($subjectName, ENT_QUOTES, 'UTF-8');
    $dueDate = htmlspecialchars($dueDate, ENT_QUOTES, 'UTF-8');
    return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:24px;background:#f5f3f0;font-family:Arial,Helvetica,sans-serif;color:#17171a">
<div style="max-width:560px;margin:auto;background:#fff;border:1px solid #e6e1db;border-radius:14px;overflow:hidden">
<div style="background:#0e0e10;color:#fff;padding:22px;font-size:18px;font-weight:bold">ComfSci Assignment Tracker</div>
<div style="height:4px;background:#ff6a00"></div>
<div style="padding:28px"><h2 style="margin-top:0">Deadline reminder</h2><p>Hello {$studentName},</p>
<p>You have an assignment due within the next 24 hours.</p>
<div style="background:#fbf6f1;padding:16px;border-radius:10px"><strong>{$assignmentTitle}</strong><br>{$subjectName}<br><strong>Due:</strong> {$dueDate}</div>
<p>Please submit your work through ComfSci Assignment Tracker before the deadline.</p></div>
<div style="padding:16px;text-align:center;background:#fbf6f1;border-top:1px solid #e6e1db;font-size:12px;color:#6b6b72">Automated message from ComfSci. Please do not reply.</div></div></body></html>
HTML;
}
