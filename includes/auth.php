<?php
declare(strict_types=1);

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user !== null) {
        return $user;
    }
    $stmt = db()->prepare('SELECT id, name, email, phone, role, loyalty_points FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    if (!$user) {
        unset($_SESSION['user_id']);
    }
    return $user;
}

function require_login(): void
{
    if (!current_user()) {
        flash('error', 'Please sign in to continue.');
        redirect('index.php?page=login');
    }
}

function require_admin(): void
{
    $user = current_user();
    if (!$user || $user['role'] !== 'admin') {
        flash('error', 'Admin access required.');
        redirect('admin/login.php');
    }
}

function normalize_phone(string $phone): string
{
    $phone = trim($phone);
    $hasPlus = str_starts_with($phone, '+');
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits === '') {
        return '';
    }
    return $hasPlus ? '+' . $digits : $digits;
}

function is_valid_phone(string $phone): bool
{
    $normalized = normalize_phone($phone);
    $digits = preg_replace('/\D+/', '', $normalized) ?? '';
    $len = strlen($digits);
    return $len >= 8 && $len <= 15;
}

function find_user_by_identifier(string $type, string $identifier): ?array
{
    if ($type === 'phone') {
        $phone = normalize_phone($identifier);
        if ($phone === '') {
            return null;
        }
        $stmt = db()->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
        $stmt->execute([$phone]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    $email = strtolower(trim($identifier));
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function attempt_login(string $identifier, string $password, string $type = 'email'): bool
{
    $type = $type === 'phone' ? 'phone' : 'email';
    $user = find_user_by_identifier($type, $identifier);
    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }
    $_SESSION['user_id'] = (int) $user['id'];
    return true;
}

function logout_user(): void
{
    unset($_SESSION['user_id']);
}

function register_user(string $name, string $password, ?string $email = null, ?string $phone = null): array
{
    $name = trim($name);
    if ($name === '') {
        return ['ok' => false, 'error' => 'Please enter your name'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Password must be at least 8 characters'];
    }

    $email = $email !== null && trim($email) !== '' ? strtolower(trim($email)) : null;
    $phone = $phone !== null && trim($phone) !== '' ? normalize_phone($phone) : null;

    if ($email === null && $phone === null) {
        return ['ok' => false, 'error' => 'Enter an email or phone number'];
    }
    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid email'];
    }
    if ($phone !== null && !is_valid_phone($phone)) {
        return ['ok' => false, 'error' => 'Enter a valid phone number'];
    }

    if ($email !== null) {
        $check = db()->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            return ['ok' => false, 'error' => 'Email already registered'];
        }
    }
    if ($phone !== null) {
        $check = db()->prepare('SELECT id FROM users WHERE phone = ?');
        $check->execute([$phone]);
        if ($check->fetch()) {
            return ['ok' => false, 'error' => 'Phone number already registered'];
        }
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins = db()->prepare('INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)');
    $ins->execute([$name, $email, $phone, $hash, 'customer']);
    $_SESSION['user_id'] = (int) db()->lastInsertId();
    return ['ok' => true];
}
