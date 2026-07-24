<?php
declare(strict_types=1);

/**
 * Validate a coupon against the cart subtotal (GBP).
 *
 * @return array{ok:bool,valid:bool,message:string,discount?:float,code?:string,label?:string}
 */
function coupon_validate(string $code, float $subtotalGbp): array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return ['ok' => true, 'valid' => false, 'message' => ''];
    }

    $stmt = db()->prepare('SELECT * FROM coupons WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();

    if (!$coupon || !(int) $coupon['is_active']) {
        return ['ok' => true, 'valid' => false, 'message' => 'Invalid coupon code'];
    }
    if (!empty($coupon['expires_at']) && strtotime((string) $coupon['expires_at']) < strtotime('today')) {
        return ['ok' => true, 'valid' => false, 'message' => 'This coupon has expired'];
    }
    if (!empty($coupon['max_uses']) && (int) $coupon['used_count'] >= (int) $coupon['max_uses']) {
        return ['ok' => true, 'valid' => false, 'message' => 'This coupon has reached its usage limit'];
    }
    if (!empty($coupon['min_order']) && $subtotalGbp < (float) $coupon['min_order']) {
        return [
            'ok' => true,
            'valid' => false,
            'message' => 'Minimum order of ' . money((float) $coupon['min_order']) . ' required',
        ];
    }

    if ($coupon['type'] === 'percent') {
        $discount = round($subtotalGbp * ((float) $coupon['value'] / 100), 2);
        $label = rtrim(rtrim(number_format((float) $coupon['value'], 2), '0'), '.') . '% off';
    } else {
        $discount = min($subtotalGbp, (float) $coupon['value']);
        $label = money((float) $coupon['value']) . ' off';
    }

    return [
        'ok' => true,
        'valid' => true,
        'message' => $label . ' · saves ' . money($discount),
        'discount' => $discount,
        'code' => $code,
        'label' => $label,
    ];
}

/**
 * Validate a gift card code for checkout use.
 *
 * @return array{ok:bool,valid:bool,message:string,balance?:float,code?:string}
 */
function gift_card_validate(string $code): array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return ['ok' => true, 'valid' => false, 'message' => ''];
    }

    $card = gift_card_find($code);
    if (!$card) {
        return ['ok' => true, 'valid' => false, 'message' => 'Invalid gift card code'];
    }
    if (($card['status'] ?? '') !== 'active') {
        return ['ok' => true, 'valid' => false, 'message' => 'This gift card is not active'];
    }
    $balance = (float) ($card['balance'] ?? 0);
    if ($balance <= 0) {
        return ['ok' => true, 'valid' => false, 'message' => 'This gift card has no remaining balance'];
    }

    return [
        'ok' => true,
        'valid' => true,
        'message' => 'Balance ' . money($balance),
        'balance' => $balance,
        'code' => $code,
    ];
}
