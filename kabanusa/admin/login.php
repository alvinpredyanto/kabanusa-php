<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (is_admin()) { header('Location: index.php'); exit; }
$error = '';
$flash = get_flash();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $now = time();
    if (!isset($_SESSION['login_attempts']) || !is_array($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = [];
    $_SESSION['login_attempts'] = array_values(array_filter($_SESSION['login_attempts'], fn($t) => $t > $now - 900));
    if (count($_SESSION['login_attempts']) >= 6) {
        $error = 'Terlalu banyak percobaan login. Coba kembali 15 menit lagi.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        try {
            $stmt = db()->prepare('SELECT id, name, username, password_hash, role, is_active FROM admin_users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            if ($admin && (int)$admin['is_active'] === 1 && password_verify($password, $admin['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int)$admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['login_attempts'] = [];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header('Location: index.php'); exit;
            }
            $_SESSION['login_attempts'][] = $now;
            $error = 'Username atau password tidak sesuai.';
        } catch (Throwable $e) {
            $error = 'Database tidak dapat diakses. Periksa konfigurasi dan import SQL.';
        }
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login Admin & Developer | KABANUSA</title><link rel="stylesheet" href="assets/admin.css"></head><body class="login-page"><main class="login-card"><div class="login-mark">K</div><h1>Login Administrator</h1><p>Masuk ke panel administrasi KABANUSA</p><?php if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="field"><label>Username</label><input name="username" required autocomplete="username" autofocus></div><div class="field"><label>Password</label><input type="password" name="password" required autocomplete="current-password"></div><button class="btn" type="submit">Masuk ke Administrasi</button></form><div class="setup-note"><a href="../index.php">← Kembali ke website KABANUSA</a><br><br>Belum ada akun Developer? <a href="setup.php">Buat akun Developer</a></div></main></body></html>
