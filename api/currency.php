<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();

$code = strtoupper((string) ($_GET['code'] ?? ''));
set_currency($code);

$redirect = $_SERVER['HTTP_REFERER'] ?? (rtrim($config['app_url'], '/') . '/index.php');
header('Location: ' . $redirect);
exit;
