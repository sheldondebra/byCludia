<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();

$error = null;
if (request_method() === 'POST') {
    if (!verify_csrf(post('csrf_token'))) {
        $error = 'Invalid session.';
    } elseif (attempt_login((string) post('email'), (string) post('password'))) {
        $user = current_user();
        if ($user && $user['role'] === 'admin') {
            header('Location: index.php');
            exit;
        }
        logout_user();
        $error = 'Admin access only.';
    } else {
        $error = 'Invalid credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body class="min-h-screen bg-[#FBF7F2] flex items-center justify-center px-4" style="font-family:Outfit,sans-serif">
  <form method="post" class="w-full max-w-md bg-white rounded-3xl shadow-lg p-8 space-y-4">
    <h1 class="text-3xl text-center" style="font-family:'Cormorant Garamond',serif">Admin</h1>
    <p class="text-center text-sm text-stone-500">admin@byclaudiadarlene.com / Admin123!</p>
    <?php if ($error): ?><div class="bg-rose-50 text-rose-700 text-sm rounded-xl px-3 py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <input type="email" name="email" required placeholder="Email" class="w-full rounded-2xl border px-4 py-3 text-sm">
    <input type="password" name="password" required placeholder="Password" class="w-full rounded-2xl border px-4 py-3 text-sm">
    <button class="w-full rounded-full bg-stone-900 text-white py-3 text-sm">Sign in</button>
  </form>
</body>
</html>
