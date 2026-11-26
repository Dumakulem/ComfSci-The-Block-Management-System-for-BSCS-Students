<?php
require_once __DIR__ . '/includes/mailer.php';

$ok = sendMail(
    'YOUR_OWN_EMAIL@example.com',
    'Test from my PHP app',
    '<h2>It works!</h2><p>This email was sent through Brevo.</p>'
);

echo $ok ? 'Email sent! Check your inbox (and spam).' : 'Failed. Check your PHP error log.';