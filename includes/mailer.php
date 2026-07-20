<?php
declare(strict_types=1);

/**
 * Lightweight SMTP mailer (no external dependencies).
 * Configured via admin Integrations settings.
 */

function mailer_last_error(?string $set = null): string
{
    static $err = '';
    if ($set !== null) {
        $err = $set;
    }
    return $err;
}

function mailer_enabled(): bool
{
    return setting('mail_enabled', '0') === '1';
}

/** Read an SMTP reply (handles multi-line) and check for an expected code. */
function smtp_read($conn, string $expected): bool
{
    $data = '';
    while (($line = fgets($conn, 515)) !== false) {
        $data .= $line;
        // Lines like "250-..." continue; "250 ..." ends.
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    if (strncmp($data, $expected, strlen($expected)) !== 0) {
        mailer_last_error('SMTP expected ' . $expected . ' got: ' . trim($data));
        return false;
    }
    return true;
}

function smtp_cmd($conn, string $cmd, string $expected): bool
{
    fwrite($conn, $cmd . "\r\n");
    return smtp_read($conn, $expected);
}

/**
 * Send an email. Returns true on success.
 */
function send_mail(string $to, string $subject, string $htmlBody, string $textBody = ''): bool
{
    if (!mailer_enabled()) {
        mailer_last_error('Email sending is disabled.');
        return false;
    }

    $host = (string) setting('smtp_host', '');
    $port = (int) (setting('smtp_port', '587') ?: 587);
    $user = (string) setting('smtp_user', '');
    $pass = (string) setting('smtp_pass', '');
    $secure = (string) setting('smtp_secure', 'tls'); // none|tls|ssl
    $fromEmail = (string) setting('mail_from', $user ?: 'no-reply@localhost');
    $fromName = (string) setting('mail_from_name', setting('store_name', 'By Claudia Darlene'));

    if ($textBody === '') {
        $textBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody)));
    }

    // Fallback to PHP mail() when no SMTP host is configured.
    if ($host === '') {
        $headers = 'From: ' . $fromName . ' <' . $fromEmail . ">\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n";
        $ok = @mail($to, $subject, $htmlBody, $headers);
        if (!$ok) {
            mailer_last_error('PHP mail() failed and no SMTP host configured.');
        }
        return $ok;
    }

    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
    $conn = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$conn) {
        mailer_last_error('Connection failed: ' . $errstr . ' (' . $errno . ')');
        return false;
    }
    stream_set_timeout($conn, 15);

    try {
        if (!smtp_read($conn, '220')) return false;
        $ehloHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if (!smtp_cmd($conn, 'EHLO ' . $ehloHost, '250')) return false;

        if ($secure === 'tls') {
            if (!smtp_cmd($conn, 'STARTTLS', '220')) return false;
            if (!stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                mailer_last_error('Failed to start TLS.');
                return false;
            }
            if (!smtp_cmd($conn, 'EHLO ' . $ehloHost, '250')) return false;
        }

        if ($user !== '') {
            if (!smtp_cmd($conn, 'AUTH LOGIN', '334')) return false;
            if (!smtp_cmd($conn, base64_encode($user), '334')) return false;
            if (!smtp_cmd($conn, base64_encode($pass), '235')) return false;
        }

        if (!smtp_cmd($conn, 'MAIL FROM:<' . $fromEmail . '>', '250')) return false;
        if (!smtp_cmd($conn, 'RCPT TO:<' . $to . '>', '250')) return false;
        if (!smtp_cmd($conn, 'DATA', '354')) return false;

        $boundary = 'bcd_' . bin2hex(random_bytes(8));
        $headers = 'From: ' . mailer_encode_header($fromName) . ' <' . $fromEmail . '>' . "\r\n"
            . 'To: <' . $to . '>' . "\r\n"
            . 'Subject: ' . mailer_encode_header($subject) . "\r\n"
            . 'Date: ' . date('r') . "\r\n"
            . 'MIME-Version: 1.0' . "\r\n"
            . 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n";

        $body = '--' . $boundary . "\r\n"
            . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
            . 'Content-Transfer-Encoding: 8bit' . "\r\n\r\n"
            . $textBody . "\r\n\r\n"
            . '--' . $boundary . "\r\n"
            . 'Content-Type: text/html; charset=UTF-8' . "\r\n"
            . 'Content-Transfer-Encoding: 8bit' . "\r\n\r\n"
            . $htmlBody . "\r\n\r\n"
            . '--' . $boundary . '--';

        // Dot-stuff lines beginning with a period.
        $data = preg_replace('/^\./m', '..', $headers . "\r\n" . $body);
        fwrite($conn, $data . "\r\n.\r\n");
        if (!smtp_read($conn, '250')) return false;

        smtp_cmd($conn, 'QUIT', '221');
        return true;
    } finally {
        @fclose($conn);
    }
}

function mailer_encode_header(string $text): string
{
    return preg_match('/[^\x20-\x7e]/', $text) ? '=?UTF-8?B?' . base64_encode($text) . '?=' : $text;
}
