<?php
// Copy outside public_html to ../oyonx-private/billing.php (0600).
// Do not put real values in this repository. Start in Stripe test mode.
return [
 'enabled' => false,
 'environment' => 'test',
 'dsn' => 'mysql:host=localhost;dbname=YOUR_DATABASE;charset=utf8mb4',
 'db_user' => 'YOUR_DATABASE_USER',
 'db_password' => '',
 'stripe_secret_key' => '',
 'stripe_webhook_secret' => '',
 'mail_from' => 'noreply@oyonx.com',
];
