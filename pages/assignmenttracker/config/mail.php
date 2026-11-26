<?php
require_once __DIR__ . '/../../../config.php';
return [
    'host' => envValue('COMFSCI_SMTP_HOST'),
    'port' => (int) envValue('COMFSCI_SMTP_PORT', '587'),
    'username' => envValue('COMFSCI_SMTP_USERNAME'),
    'password' => envValue('COMFSCI_SMTP_PASSWORD'),
    'from_email' => envValue('COMFSCI_SMTP_FROM_EMAIL', envValue('COMFSCI_SMTP_USERNAME')),
    'from_name' => envValue('COMFSCI_SMTP_FROM_NAME', 'ComfSci Assignment Tracker'),
];
