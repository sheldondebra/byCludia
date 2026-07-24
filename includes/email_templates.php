<?php
declare(strict_types=1);

/**
 * Editorial HTML email shells and starter templates for Email Marketing.
 * Table-based + inline CSS for Gmail / Apple Mail / Outlook compatibility.
 */

function email_template_types(): array
{
    return [
        'promo' => 'Promo / general',
        'coupon' => 'Coupon',
        'discount' => 'Discount bulk',
        'holiday' => 'Holiday',
        'failed_order' => 'Failed / unfinished order',
    ];
}

function email_abs_url(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }
    $base = rtrim((string) ($GLOBALS['config']['app_url'] ?? ''), '/');
    return $base . '/' . ltrim($path, '/');
}

/**
 * @return array{subject:string,preview:string,headline:string,eyebrow:string,body:string,cta_label:string,cta_url:string}
 */
function email_template_starter(string $type): array
{
    $store = setting('store_name', 'By Claudia Darlene') ?: 'By Claudia Darlene';
    $shop = url('index.php?page=shop');

    return match ($type) {
        'coupon' => [
            'subject' => 'Inside: your private code from ' . $store,
            'preview' => 'An exclusive offer, reserved for you — open to reveal your code.',
            'eyebrow' => 'Private invitation',
            'headline' => 'A code, just for you',
            'body' => '<p style="margin:0 0 18px;">Dear {{name}},</p>'
                . '<p style="margin:0 0 18px;">Thank you for being part of the ' . e($store) . ' circle. We\'ve set aside a special code as a quiet thank-you — use it at checkout whenever you\'re ready for something beautiful.</p>'
                . '<p style="margin:0;">Apply the code below, then explore textures made to feel like you.</p>',
            'cta_label' => 'Redeem & shop',
            'cta_url' => $shop,
        ],
        'discount' => [
            'subject' => 'A limited offer from ' . $store,
            'preview' => 'Thoughtful savings on pieces you\'ll love living in.',
            'eyebrow' => 'Limited offer',
            'headline' => 'Beauty, thoughtfully priced',
            'body' => '<p style="margin:0 0 18px;">Dear {{name}},</p>'
                . '<p style="margin:0 0 18px;">For a short window, selected pieces are yours at a softer price — the same craftsmanship, the same movement, a little more room to say yes.</p>'
                . '<p style="margin:0;">When the offer ends, it ends. We\'d love to see you before then.</p>',
            'cta_label' => 'Shop the offer',
            'cta_url' => $shop,
        ],
        'holiday' => [
            'subject' => 'Seasonal wishes from ' . $store,
            'preview' => 'Celebrate with textures that glow — gift or keep.',
            'eyebrow' => 'Seasonal edit',
            'headline' => 'For nights that shimmer',
            'body' => '<p style="margin:0 0 18px;">Dear {{name}},</p>'
                . '<p style="margin:0 0 18px;">This season is for soft light, full volume, and looks that feel like a celebration. We\'ve gathered pieces that photograph beautifully — and feel even better in person.</p>'
                . '<p style="margin:0;">Send a gift, or keep the glow for yourself.</p>',
            'cta_label' => 'Explore the edit',
            'cta_url' => $shop,
        ],
        'failed_order' => [
            'subject' => 'Your ' . $store . ' bag is still waiting',
            'preview' => 'No pressure — your selections are saved whenever you\'re ready.',
            'eyebrow' => 'Your order',
            'headline' => 'We saved your place',
            'body' => '<p style="margin:0 0 18px;">Dear {{name}},</p>'
                . '<p style="margin:0 0 18px;">It looks like order <strong style="color:#2c2420;">{{order_number}}</strong> wasn\'t finished. That\'s perfectly alright — your selections are still here, waiting softly.</p>'
                . '<p style="margin:0;">If checkout gave you any trouble, simply reply to this email. We\'re happy to help you through.</p>',
            'cta_label' => 'Return to bag',
            'cta_url' => url('index.php?page=cart'),
        ],
        default => [
            'subject' => 'From the desk of ' . $store,
            'preview' => 'New pieces, quiet details, and looks you\'ll love living in.',
            'eyebrow' => 'Studio note',
            'headline' => 'Something beautiful, waiting',
            'body' => '<p style="margin:0 0 18px;">Dear {{name}},</p>'
                . '<p style="margin:0 0 18px;">A quiet note from ' . e($store) . ' — new textures, refined finishes, and pieces designed to move with you from soft mornings to late evenings.</p>'
                . '<p style="margin:0;">Take a moment to see what\'s new in the atelier.</p>',
            'cta_label' => 'Browse the collection',
            'cta_url' => $shop,
        ],
    };
}

/**
 * @param array{
 *   template_type?:string,
 *   headline?:string,
 *   eyebrow?:string,
 *   body:string,
 *   cta_label?:string,
 *   cta_url?:string,
 *   hero_image?:string,
 *   coupon_code?:string,
 *   preview_text?:string
 * } $parts
 */
function email_render_html(array $parts): string
{
    $store = setting('store_name', 'By Claudia Darlene') ?: 'By Claudia Darlene';
    $type = (string) ($parts['template_type'] ?? 'promo');
    if (!isset(email_template_types()[$type])) {
        $type = 'promo';
    }

    $headline = trim((string) ($parts['headline'] ?? ''));
    $eyebrow = trim((string) ($parts['eyebrow'] ?? ''));
    if ($eyebrow === '') {
        $eyebrow = match ($type) {
            'coupon' => 'Private invitation',
            'discount' => 'Limited offer',
            'holiday' => 'Seasonal edit',
            'failed_order' => 'Your order',
            default => 'Studio note',
        };
    }

    $body = (string) ($parts['body'] ?? '');
    $ctaLabel = trim((string) ($parts['cta_label'] ?? ''));
    $ctaUrl = trim((string) ($parts['cta_url'] ?? ''));
    $hero = trim((string) ($parts['hero_image'] ?? ''));
    $coupon = trim((string) ($parts['coupon_code'] ?? ''));
    $preview = trim((string) ($parts['preview_text'] ?? ''));
    $unsub = url('index.php?page=unsubscribe&email={{email}}');
    $shop = url('index.php?page=shop');
    $contact = (string) setting('contact_email', '');
    $instagram = trim((string) setting('social_instagram', ''));

    $logoPath = trim((string) setting('logo_path', 'assets/images/logo.png'));
    $logoAbs = $logoPath !== '' && file_exists(ROOT_PATH . '/' . ltrim($logoPath, '/'))
        ? email_abs_url($logoPath)
        : '';

    $heroAbs = $hero !== '' ? email_abs_url($hero) : '';

    // Accent systems per template
    $theme = match ($type) {
        'coupon' => ['accent' => '#C9A08A', 'wash' => '#FBF4EF', 'deep' => '#3A2E28', 'ribbon' => 'YOUR CODE'],
        'discount' => ['accent' => '#B76E79', 'wash' => '#FBF1F3', 'deep' => '#2F2422', 'ribbon' => 'LIMITED OFFER'],
        'holiday' => ['accent' => '#8B6B4A', 'wash' => '#F7F1E8', 'deep' => '#2A221C', 'ribbon' => 'SEASONAL EDIT'],
        'failed_order' => ['accent' => '#A89080', 'wash' => '#F8F4F0', 'deep' => '#2C2420', 'ribbon' => 'STILL WAITING'],
        default => ['accent' => '#E8A8A8', 'wash' => '#FBF7F2', 'deep' => '#2C2420', 'ribbon' => 'HAIR BY CLAUDIA DARLENE'],
    };

    $previewHidden = $preview !== ''
        ? '<div style="display:none;font-size:1px;color:#FBF7F2;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">'
            . e($preview) . '&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;</div>'
        : '';

    // Top brand bar
    $logoBlock = $logoAbs !== ''
        ? '<img src="' . e($logoAbs) . '" alt="' . e($store) . '" width="132" style="display:block;margin:0 auto;max-width:132px;height:auto;border:0;">'
        : '<p style="margin:0;font-family:Georgia,\'Times New Roman\',serif;font-size:28px;letter-spacing:0.06em;color:#ffffff;line-height:1.2;">'
            . e($store) . '</p>';

    $header = '<tr><td style="background:' . $theme['deep'] . ';padding:0;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0">'
        . '<tr><td style="height:4px;background:' . $theme['accent'] . ';font-size:0;line-height:0;">&nbsp;</td></tr>'
        . '<tr><td align="center" style="padding:28px 32px 22px;">' . $logoBlock . '</td></tr>'
        . '<tr><td align="center" style="padding:0 32px 20px;">'
        . '<p style="margin:0;font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:10px;letter-spacing:0.28em;text-transform:uppercase;color:rgba(255,255,255,0.72);">'
        . e($theme['ribbon']) . '</p></td></tr>'
        . '</table></td></tr>';

    // Hero
    $heroHtml = '';
    if ($heroAbs !== '') {
        $heroHtml = '<tr><td style="padding:0;line-height:0;font-size:0;">'
            . '<img src="' . e($heroAbs) . '" alt="" width="600" style="display:block;width:100%;max-width:600px;height:auto;border:0;">'
            . '</td></tr>';
    } else {
        // Decorative placeholder band when no hero — still looks intentional
        $heroHtml = '<tr><td style="padding:0;background:' . $theme['wash'] . ';">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0">'
            . '<tr><td align="center" style="padding:48px 40px;">'
            . '<p style="margin:0 0 10px;font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:11px;letter-spacing:0.32em;text-transform:uppercase;color:' . $theme['accent'] . ';">'
            . e($eyebrow) . '</p>'
            . '<div style="width:56px;height:1px;background:' . $theme['accent'] . ';margin:0 auto 18px;"></div>'
            . ($headline !== ''
                ? '<p style="margin:0;font-family:Georgia,\'Times New Roman\',serif;font-size:34px;line-height:1.2;color:' . $theme['deep'] . ';">' . e($headline) . '</p>'
                : '')
            . '</td></tr></table></td></tr>';
    }

    // Eyebrow + headline (when hero image present, show text below)
    $titleHtml = '';
    if ($heroAbs !== '') {
        $titleHtml = '<tr><td style="padding:36px 44px 0;text-align:center;background:#ffffff;">'
            . '<p style="margin:0 0 12px;font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:11px;letter-spacing:0.28em;text-transform:uppercase;color:' . $theme['accent'] . ';">'
            . e($eyebrow) . '</p>'
            . '<div style="width:40px;height:1px;background:' . $theme['accent'] . ';margin:0 auto 16px;"></div>'
            . ($headline !== ''
                ? '<p style="margin:0;font-family:Georgia,\'Times New Roman\',serif;font-size:32px;line-height:1.25;color:' . $theme['deep'] . ';">' . e($headline) . '</p>'
                : '')
            . '</td></tr>';
    } elseif ($headline === '') {
        // no hero, no headline — still show eyebrow already in band; nothing extra
        $titleHtml = '';
    } else {
        // headline already in decorative band when no hero
        $titleHtml = '';
    }

    // Body
    $bodyHtml = '<tr><td style="padding:28px 44px 8px;font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:16px;line-height:1.75;color:#5A504A;background:#ffffff;">'
        . $body
        . '</td></tr>';

    // Coupon / offer card
    $featureHtml = '';
    if ($coupon !== '') {
        $featureHtml = '<tr><td style="padding:12px 44px 8px;background:#ffffff;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:' . $theme['wash'] . ';border:1px solid ' . $theme['accent'] . '55;border-radius:18px;">'
            . '<tr><td align="center" style="padding:28px 24px;">'
            . '<p style="margin:0 0 8px;font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:10px;letter-spacing:0.3em;text-transform:uppercase;color:' . $theme['accent'] . ';">Exclusive code</p>'
            . '<p style="margin:0 0 6px;font-family:Georgia,\'Times New Roman\',serif;font-size:34px;letter-spacing:0.14em;color:' . $theme['deep'] . ';font-weight:normal;">'
            . e($coupon) . '</p>'
            . '<p style="margin:10px 0 0;font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:12px;color:#8A7C74;">Enter at checkout · One beautiful moment</p>'
            . '</td></tr></table></td></tr>';
    } elseif ($type === 'discount') {
        $featureHtml = '<tr><td style="padding:12px 44px 8px;background:#ffffff;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:' . $theme['deep'] . ';border-radius:18px;">'
            . '<tr><td align="center" style="padding:26px 24px;">'
            . '<p style="margin:0 0 6px;font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:10px;letter-spacing:0.28em;text-transform:uppercase;color:' . $theme['accent'] . ';">For a limited time</p>'
            . '<p style="margin:0;font-family:Georgia,\'Times New Roman\',serif;font-size:26px;line-height:1.3;color:#ffffff;">Soft savings on selected pieces</p>'
            . '</td></tr></table></td></tr>';
    } elseif ($type === 'failed_order') {
        $featureHtml = '<tr><td style="padding:12px 44px 8px;background:#ffffff;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:' . $theme['wash'] . ';border-left:3px solid ' . $theme['accent'] . ';border-radius:4px;">'
            . '<tr><td style="padding:18px 20px;">'
            . '<p style="margin:0 0 4px;font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:10px;letter-spacing:0.22em;text-transform:uppercase;color:#8A7C74;">Order reference</p>'
            . '<p style="margin:0;font-family:Georgia,\'Times New Roman\',serif;font-size:20px;color:' . $theme['deep'] . ';">{{order_number}}</p>'
            . '</td></tr></table></td></tr>';
    } elseif ($type === 'holiday') {
        $featureHtml = '<tr><td style="padding:8px 44px 4px;background:#ffffff;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0">'
            . '<tr>'
            . '<td width="33%" style="padding:8px;text-align:center;font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#8A7C74;">Gift ready</td>'
            . '<td width="33%" style="padding:8px;text-align:center;font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#8A7C74;border-left:1px solid #EDE4DC;border-right:1px solid #EDE4DC;">Tracked shipping</td>'
            . '<td width="33%" style="padding:8px;text-align:center;font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#8A7C74;">Luxe finish</td>'
            . '</tr></table></td></tr>';
    }

    // CTA
    $ctaHtml = '';
    if ($ctaLabel !== '' && $ctaUrl !== '') {
        $ctaHtml = '<tr><td align="center" style="padding:28px 44px 36px;background:#ffffff;">'
            . '<table role="presentation" cellspacing="0" cellpadding="0"><tr>'
            . '<td align="center" style="border-radius:999px;background:' . $theme['deep'] . ';">'
            . '<a href="' . e($ctaUrl) . '" style="display:inline-block;padding:16px 36px;font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:13px;letter-spacing:0.16em;text-transform:uppercase;text-decoration:none;color:#ffffff;border-radius:999px;border:1px solid ' . $theme['deep'] . ';">'
            . e($ctaLabel) . '</a></td></tr></table>'
            . '<p style="margin:18px 0 0;font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:12px;color:#A0948C;">'
            . '<a href="' . e($shop) . '" style="color:#A0948C;text-decoration:underline;">Or browse the full collection</a></p>'
            . '</td></tr>';
    }

    // Soft divider quote strip
    $strip = '<tr><td style="padding:0;background:' . $theme['wash'] . ';">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0">'
        . '<tr><td align="center" style="padding:28px 40px;">'
        . '<p style="margin:0;font-family:Georgia,\'Times New Roman\',serif;font-size:18px;line-height:1.55;font-style:italic;color:' . $theme['deep'] . ';">'
        . 'Luxury hair for every curl story.</p>'
        . '</td></tr></table></td></tr>';

    // Footer
    $social = '';
    if ($instagram !== '') {
        $igUrl = str_starts_with($instagram, 'http') ? $instagram : 'https://instagram.com/' . ltrim($instagram, '@');
        $social = '<a href="' . e($igUrl) . '" style="color:#A0948C;text-decoration:none;letter-spacing:0.12em;text-transform:uppercase;font-size:11px;">Instagram</a>';
    }

    $footer = '<tr><td style="padding:32px 40px 36px;background:' . $theme['deep'] . ';text-align:center;">'
        . '<p style="margin:0 0 6px;font-family:Georgia,\'Times New Roman\',serif;font-size:18px;letter-spacing:0.04em;color:#ffffff;">'
        . e($store) . '</p>'
        . '<p style="margin:0 0 16px;font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:11px;letter-spacing:0.18em;text-transform:uppercase;color:rgba(255,255,255,0.55);">Atelier · Texture · Care</p>'
        . ($social !== '' ? '<p style="margin:0 0 14px;">' . $social . '</p>' : '')
        . ($contact !== '' ? '<p style="margin:0 0 14px;font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:12px;color:rgba(255,255,255,0.55);"><a href="mailto:' . e($contact) . '" style="color:rgba(255,255,255,0.55);text-decoration:none;">' . e($contact) . '</a></p>' : '')
        . '<p style="margin:0;font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:11px;line-height:1.6;color:rgba(255,255,255,0.4);">'
        . 'You\'re receiving this because you shopped with us or joined our list.<br>'
        . '<a href="' . e($unsub) . '" style="color:rgba(255,255,255,0.55);text-decoration:underline;">Unsubscribe</a>'
        . '</p></td></tr>';

    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="color-scheme" content="light only">'
        . '<title>' . e($store) . '</title>'
        . '<!--[if mso]><style>body,table,td{font-family:Georgia,serif!important;}</style><![endif]-->'
        . '</head>'
        . '<body style="margin:0;padding:0;background:#EFE8E1;color:#2c2420;-webkit-text-size-adjust:100%;">'
        . $previewHidden
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#EFE8E1;">'
        . '<tr><td align="center" style="padding:40px 12px;">'
        . '<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#ffffff;border-collapse:collapse;overflow:hidden;box-shadow:0 12px 40px rgba(44,36,32,0.08);">'
        . $header
        . $heroHtml
        . $titleHtml
        . $bodyHtml
        . $featureHtml
        . $ctaHtml
        . $strip
        . $footer
        . '</table>'
        . '<p style="margin:20px 0 0;font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:11px;color:#B0A49C;text-align:center;">'
        . e($store) . ' · Crafted with care</p>'
        . '</td></tr></table></body></html>';
}
