<?php

/**
 * Outbound SMTP settings for the centralized email service (app/Email).
 * Defaults target Gmail app-password accounts.
 */

return [
    'smtp_host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
    'smtp_port' => (int) (getenv('SMTP_PORT') ?: 587),
];
