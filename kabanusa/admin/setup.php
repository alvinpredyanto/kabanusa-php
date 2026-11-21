<?php
require_once __DIR__ . '/../includes/bootstrap.php';

try {
    $count = (int)db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
} catch (Throwable $e) {
    http_response_code(500);
    exit('Database belum siap. Import database/kabanusa.sql dan periksa config.php terlebih dahulu.');
}
if ($count > 0) {
    http_response_code(403);
    exit('Akun Developer sudah dibuat. Halaman setup telah dikunci. Silakan login melalui <a href="login.php">halaman login</a>.');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    if ($name === '' || !preg_match('/^[a-zA-Z0-9._-]{4,60}$/', $username)) {
        $error = 'Nama wajib diisi. Username harus 4–60 karakter dan hanya berisi huruf, angka, titik, garis bawah, atau tanda hubung.';
    } elseif (strlen($password) < 12) {
        $error = 'Password minimal 12 karakter.';
    } elseif ($password !== $confirm) {
        $error = 'Konfirmasi password tidak sama.';
    } else {
        try {
            $stmt = db()->prepare('INSERT INTO admin_users (name, username, password_hash, role, is_active, account_slot) VALUES (?, ?, ?, ?, 1, ?)');
            $stmt->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), 'developer', 'developer']);
            flash('success', 'Akun Developer berhasil dibuat. Login, lalu buka Kelola Akun untuk membuat empat akun Admin.');
            header('Location: login.php'); exit;
        } catch (Throwable $e) {
            $error = 'Pembuatan administrator gagal. Username mungkin sudah digunakan.';
        }
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Setup Developer | KABANUSA</title><link rel="stylesheet" href="assets/admin.css"></head><body class="login-page"><main class="login-card"><div class="login-mark">K</div><h1>Setup Developer</h1><p>Buat satu akun Developer KABANUSA dengan akses penuh. Setelah login, Developer dapat membuat empat akun Admin sesuai tugasnya.</p><?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?><div class="danger-note">Akun Developer dapat mengelola seluruh website, membuat empat Admin, mengatur akses, dan mengelola semua data. Gunakan password unik minimal 12 karakter.</div><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="field"><label>Nama Developer</label><input name="name" required maxlength="120" autocomplete="name"></div><div class="field"><label>Username</label><input name="username" required minlength="4" maxlength="60" autocomplete="username"></div><div class="field"><label>Password (minimal 12 karakter)</label><input type="password" name="password" required minlength="12" autocomplete="new-password"></div><div class="field"><label>Ulangi password</label><input type="password" name="confirm_password" required minlength="12" autocomplete="new-password"></div><button class="btn" type="submit">Buat Akun Developer</button></form><div class="setup-note"><a href="../index.php">Kembali ke website</a></div></main></body></html>
