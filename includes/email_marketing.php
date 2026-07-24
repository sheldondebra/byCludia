<?php
declare(strict_types=1);

require_once __DIR__ . '/email_templates.php';

/**
 * Email marketing: audiences, assets, campaigns, send loop, unsubscribe.
 */

function email_is_unsubscribed(string $email): bool
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return false;
    }
    $stmt = db()->prepare('SELECT 1 FROM email_unsubscribes WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    return (bool) $stmt->fetchColumn();
}

function email_unsubscribe(string $email, string $reason = ''): void
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    try {
        db()->prepare('INSERT INTO email_unsubscribes (email, reason) VALUES (?, ?)')->execute([$email, $reason !== '' ? $reason : null]);
    } catch (Throwable $e) {
        // already unsubscribed
    }
}

/**
 * @return list<array{email:string,name:string,source:string,order_number:?string}>
 */
function email_collect_audience(array $sources, array $orderStatuses = [], string $customEmails = ''): array
{
    $byEmail = [];
    $unsub = [];
    foreach (db()->query('SELECT email FROM email_unsubscribes') as $row) {
        $unsub[strtolower((string) $row['email'])] = true;
    }

    $add = function (string $email, string $name, string $source, ?string $orderNumber = null) use (&$byEmail, $unsub): void {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($unsub[$email])) {
            return;
        }
        if (!isset($byEmail[$email])) {
            $byEmail[$email] = [
                'email' => $email,
                'name' => $name !== '' ? $name : 'there',
                'source' => $source,
                'order_number' => $orderNumber,
            ];
            return;
        }
        if ($orderNumber && empty($byEmail[$email]['order_number'])) {
            $byEmail[$email]['order_number'] = $orderNumber;
        }
        if ($byEmail[$email]['name'] === 'there' && $name !== '') {
            $byEmail[$email]['name'] = $name;
        }
    };

    if (in_array('subscribers', $sources, true)) {
        foreach (db()->query("SELECT name, email FROM subscribers WHERE email IS NOT NULL AND TRIM(email) <> ''") as $row) {
            $add((string) $row['email'], trim((string) ($row['name'] ?? '')), 'subscriber');
        }
        try {
            foreach (db()->query("SELECT name, email FROM newsletter_subscribers WHERE email IS NOT NULL AND TRIM(email) <> ''") as $row) {
                $add((string) $row['email'], trim((string) ($row['name'] ?? '')), 'subscriber');
            }
        } catch (Throwable $e) {
            // table may not exist on some installs
        }
    }

    if (in_array('users', $sources, true)) {
        foreach (db()->query("SELECT name, email FROM users WHERE role = 'customer' AND email IS NOT NULL AND TRIM(email) <> ''") as $row) {
            $add((string) $row['email'], trim((string) ($row['name'] ?? '')), 'customer');
        }
    }

    if (in_array('orders', $sources, true)) {
        $sql = "SELECT email, shipping_name, order_number, status FROM orders WHERE email IS NOT NULL AND TRIM(email) <> ''";
        $params = [];
        $orderStatuses = array_values(array_filter(array_map('strval', $orderStatuses)));
        if ($orderStatuses) {
            $placeholders = implode(',', array_fill(0, count($orderStatuses), '?'));
            $sql .= " AND status IN ($placeholders)";
            $params = $orderStatuses;
        }
        $sql .= ' ORDER BY id DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt as $row) {
            $add((string) $row['email'], trim((string) ($row['shipping_name'] ?? '')), 'order', (string) ($row['order_number'] ?? ''));
        }
    }

    if ($customEmails !== '') {
        foreach (preg_split('/[\s,;]+/', $customEmails) ?: [] as $raw) {
            $add(trim($raw), '', 'custom');
        }
    }

    return array_values($byEmail);
}

/**
 * Unified contact directory for admin browsing.
 *
 * @return list<array{email:string,name:string,sources:string,orders:int}>
 */
function email_unified_contacts(?string $q = null): array
{
    $orderCounts = [];
    foreach (db()->query("SELECT LOWER(email) AS e, COUNT(*) AS c FROM orders WHERE email IS NOT NULL AND TRIM(email) <> '' GROUP BY LOWER(email)") as $row) {
        $orderCounts[(string) $row['e']] = (int) $row['c'];
    }

    $map = [];
    $bump = function (string $email, string $name, string $source) use (&$map): void {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        if (!isset($map[$email])) {
            $map[$email] = ['email' => $email, 'name' => $name ?: '—', 'sources' => [$source]];
            return;
        }
        if (($map[$email]['name'] === '—' || $map[$email]['name'] === 'there') && $name !== '') {
            $map[$email]['name'] = $name;
        }
        if (!in_array($source, $map[$email]['sources'], true)) {
            $map[$email]['sources'][] = $source;
        }
    };

    foreach (db()->query("SELECT name, email FROM subscribers WHERE email IS NOT NULL AND TRIM(email) <> ''") as $row) {
        $bump((string) $row['email'], trim((string) ($row['name'] ?? '')), 'subscriber');
    }
    try {
        foreach (db()->query("SELECT name, email FROM newsletter_subscribers WHERE email IS NOT NULL AND TRIM(email) <> ''") as $row) {
            $bump((string) $row['email'], trim((string) ($row['name'] ?? '')), 'subscriber');
        }
    } catch (Throwable $e) {
    }
    foreach (db()->query("SELECT name, email FROM users WHERE role = 'customer' AND email IS NOT NULL AND TRIM(email) <> ''") as $row) {
        $bump((string) $row['email'], trim((string) ($row['name'] ?? '')), 'customer');
    }
    foreach (db()->query("SELECT shipping_name, email FROM orders WHERE email IS NOT NULL AND TRIM(email) <> ''") as $row) {
        $bump((string) $row['email'], trim((string) ($row['shipping_name'] ?? '')), 'order');
    }

    $out = [];
    foreach ($map as $email => $row) {
        if (email_is_unsubscribed($email)) {
            continue;
        }
        if ($q !== null && $q !== '') {
            $hay = strtolower($email . ' ' . $row['name']);
            if (!str_contains($hay, strtolower($q))) {
                continue;
            }
        }
        $out[] = [
            'email' => $email,
            'name' => $row['name'],
            'sources' => implode(', ', $row['sources']),
            'orders' => $orderCounts[$email] ?? 0,
        ];
    }

    usort($out, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    return $out;
}

function email_merge_tags(string $html, array $recipient, array $extras = []): string
{
    $store = setting('store_name', 'By Claudia Darlene') ?: 'By Claudia Darlene';
    $map = [
        '{{name}}' => $recipient['name'] ?? 'there',
        '{{email}}' => $recipient['email'] ?? '',
        '{{store_name}}' => $store,
        '{{coupon_code}}' => $extras['coupon_code'] ?? '',
        '{{order_number}}' => $recipient['order_number'] ?? ($extras['order_number'] ?? ''),
    ];
    return str_replace(array_keys($map), array_values($map), $html);
}

function email_upload_asset(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return null;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        return null;
    }
    $dir = ROOT_PATH . '/assets/images/uploads/email';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $name = 'em_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $rel = 'assets/images/uploads/email/' . $name;
    if (!@move_uploaded_file($tmp, ROOT_PATH . '/' . $rel)) {
        return null;
    }
    try {
        db()->prepare('INSERT INTO email_assets (path, original_name) VALUES (?, ?)')
            ->execute([$rel, (string) ($file['name'] ?? $name)]);
    } catch (Throwable $e) {
    }
    return $rel;
}

/**
 * @param array{subject:string,preview_text?:string,template_type?:string,headline?:string,body_html:string,cta_label?:string,cta_url?:string,hero_image?:string,coupon_code?:string,audience:array,created_by?:?int} $data
 * @param list<array{email:string,name:string,source:string,order_number:?string}> $recipients
 * @return array{campaign_id:int,sent:int,failed:int}
 */
function email_campaign_send(array $data, array $recipients, bool $testOnly = false): array
{
    $subject = trim((string) ($data['subject'] ?? ''));
    $bodyInner = (string) ($data['body_html'] ?? '');
    if ($subject === '' || $bodyInner === '' || !$recipients) {
        return ['campaign_id' => 0, 'sent' => 0, 'failed' => 0];
    }

    $parts = [
        'template_type' => (string) ($data['template_type'] ?? 'promo'),
        'headline' => (string) ($data['headline'] ?? ''),
        'eyebrow' => (string) ($data['eyebrow'] ?? ''),
        'body' => $bodyInner,
        'cta_label' => (string) ($data['cta_label'] ?? ''),
        'cta_url' => (string) ($data['cta_url'] ?? ''),
        'hero_image' => (string) ($data['hero_image'] ?? ''),
        'coupon_code' => (string) ($data['coupon_code'] ?? ''),
        'preview_text' => (string) ($data['preview_text'] ?? ''),
    ];
    $fullHtml = email_render_html($parts);

    $pdo = db();
    $pdo->prepare(
        'INSERT INTO email_campaigns (subject, preview_text, template_type, headline, body_html, cta_label, cta_url, hero_image, coupon_code, audience_json, status, recipient_count, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $subject,
        $data['preview_text'] ?? null,
        $data['template_type'] ?? 'promo',
        $data['headline'] ?? null,
        $fullHtml,
        $data['cta_label'] ?? null,
        $data['cta_url'] ?? null,
        $data['hero_image'] ?? null,
        $data['coupon_code'] ?? null,
        json_encode($data['audience'] ?? [], JSON_UNESCAPED_UNICODE),
        $testOnly ? 'sent' : 'sending',
        count($recipients),
        $data['created_by'] ?? null,
    ]);
    $campaignId = (int) $pdo->lastInsertId();

    $ins = $pdo->prepare(
        'INSERT INTO email_campaign_recipients (campaign_id, email, name, source, order_number, status, error_message, sent_at)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    $upd = $pdo->prepare(
        'UPDATE email_campaign_recipients SET status = ?, error_message = ?, sent_at = ? WHERE id = ?'
    );

    $sent = 0;
    $failed = 0;
    $extras = ['coupon_code' => (string) ($data['coupon_code'] ?? '')];

    // Raise limits for larger immediate sends
    @set_time_limit(0);
    @ini_set('max_execution_time', '0');

    foreach ($recipients as $r) {
        $ins->execute([
            $campaignId,
            $r['email'],
            $r['name'] ?? null,
            $r['source'] ?? null,
            $r['order_number'] ?? null,
            'pending',
            null,
            null,
        ]);
        $rid = (int) $pdo->lastInsertId();
        $html = email_merge_tags($fullHtml, $r, $extras);
        $subj = email_merge_tags($subject, $r, $extras);
        $ok = send_mail($r['email'], $subj, $html);
        if ($ok) {
            $sent++;
            $upd->execute(['sent', null, date('Y-m-d H:i:s'), $rid]);
        } else {
            $failed++;
            $upd->execute(['failed', mailer_last_error() ?: 'Send failed', date('Y-m-d H:i:s'), $rid]);
        }
    }

    $pdo->prepare(
        'UPDATE email_campaigns SET status = ?, sent_count = ?, failed_count = ?, sent_at = ?, updated_at = ? WHERE id = ?'
    )->execute(['sent', $sent, $failed, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $campaignId]);

    return ['campaign_id' => $campaignId, 'sent' => $sent, 'failed' => $failed];
}

function email_contact_stats(): array
{
    $contacts = email_unified_contacts();
    $campaigns = (int) db()->query('SELECT COUNT(*) FROM email_campaigns')->fetchColumn();
    $sent = (int) db()->query("SELECT COALESCE(SUM(sent_count),0) FROM email_campaigns")->fetchColumn();
    $unsub = (int) db()->query('SELECT COUNT(*) FROM email_unsubscribes')->fetchColumn();
    return [
        'contacts' => count($contacts),
        'campaigns' => $campaigns,
        'emails_sent' => $sent,
        'unsubscribed' => $unsub,
    ];
}
