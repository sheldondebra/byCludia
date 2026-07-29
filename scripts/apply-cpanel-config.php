<?php
/**
 * Apply production DB check + sync SMTP settings from config.php into the settings table.
 *
 * Usage (on the server or locally with Remote MySQL enabled):
 *   php scripts/apply-cpanel-config.php
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

echo "App env: " . ($config['env'] ?? '?') . PHP_EOL;
echo "DB host: " . ($config['db']['host'] ?? '?') . PHP_EOL;
echo "DB name: " . ($config['db']['name'] ?? '?') . PHP_EOL;
echo "DB user: " . ($config['db']['user'] ?? '?') . PHP_EOL;

try {
    $pdo = db();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    echo "Connected via: {$driver}" . PHP_EOL;

    if ($driver === 'sqlite') {
        echo "WARNING: Still using SQLite. Check use_sqlite / MySQL credentials." . PHP_EOL;
        exit(1);
    }

    // Ensure core tables exist (idempotent).
    $schemaFile = dirname(__DIR__) . '/database/schema.sql';
    if (is_file($schemaFile)) {
        $sql = file_get_contents($schemaFile);
        if (is_string($sql) && $sql !== '') {
            $pdo->exec($sql);
            echo "Schema applied/verified." . PHP_EOL;
        }
    }

    mailer_sync_settings_from_config(true);
    echo "SMTP settings synced:" . PHP_EOL;
    echo "  mail_enabled=" . setting('mail_enabled', '') . PHP_EOL;
    echo "  smtp_host=" . setting('smtp_host', '') . PHP_EOL;
    echo "  smtp_port=" . setting('smtp_port', '') . PHP_EOL;
    echo "  smtp_secure=" . setting('smtp_secure', '') . PHP_EOL;
    echo "  smtp_user=" . setting('smtp_user', '') . PHP_EOL;
    echo "  mail_from=" . setting('mail_from', '') . PHP_EOL;
    echo "Done." . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, "If you're running this on your laptop, enable Remote MySQL in cPanel" . PHP_EOL);
    fwrite(STDERR, "and set config db.host to your server hostname (not localhost)." . PHP_EOL);
    exit(1);
}
