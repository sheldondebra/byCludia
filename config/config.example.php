<?php
/**
 * Copy to config.php and fill in your values.
 * Never commit config.php with real secrets.
 */
return [
    'app_name'   => 'Hair by Claudia Darlene',
    'app_url'    => 'http://localhost:8080',
    'env'        => 'local', // local | production

    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => 'byclaudiadarlene',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],

    'base_currency' => 'GBP',
    'currencies' => ['GBP', 'USD', 'EUR', 'GHS'],

    'stripe' => [
        'public_key' => '',
        'secret_key' => '',
        'webhook_secret' => '',
    ],
    'paypal' => [
        'client_id' => '',
        'secret' => '',
        'mode' => 'sandbox', // sandbox | live
    ],
    'klarna' => [
        'username' => '',
        'password' => '',
        'region' => 'eu',
    ],
    'clearpay' => [
        'merchant_id' => '',
        'secret_key' => '',
        'mode' => 'sandbox',
    ],

    'mail' => [
        'from' => 'info@byclaudiadarlene.com',
        'from_name' => 'Hair by Claudia Darlene',
    ],

    'admin_email' => 'admin@byclaudiadarlene.com',
];
