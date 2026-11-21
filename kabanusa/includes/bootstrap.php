<?php
declare(strict_types=1);

// Set these values in your hosting environment, or edit config.php for XAMPP.
require_once __DIR__ . '/../config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    ensure_rbac_schema($pdo);
    ensure_activity_gallery_schema($pdo);
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Add role-based access control columns to an existing installation.
 * For a legacy installation, the oldest existing account becomes Developer;
 * other legacy accounts are disabled until the Developer reviews them.
 */
function ensure_rbac_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $roleAdded = false;
    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM admin_users')->fetchAll() as $column) {
        $columns[(string)$column['Field']] = true;
    }

    if (!isset($columns['role'])) {
        $pdo->exec("ALTER TABLE admin_users ADD COLUMN role VARCHAR(32) NOT NULL DEFAULT 'content'");
        $roleAdded = true;
    }
    if (!isset($columns['is_active'])) {
        $pdo->exec('ALTER TABLE admin_users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1');
    }
    if (!isset($columns['account_slot'])) {
        $pdo->exec('ALTER TABLE admin_users ADD COLUMN account_slot VARCHAR(40) NULL DEFAULT NULL');
    }

    if ($roleAdded) {
        $firstId = $pdo->query('SELECT id FROM admin_users ORDER BY id ASC LIMIT 1')->fetchColumn();
        if ($firstId !== false) {
            $stmt = $pdo->prepare("UPDATE admin_users SET role='developer', is_active=1, account_slot='developer' WHERE id=?");
            $stmt->execute([(int)$firstId]);
            $others = $pdo->prepare("UPDATE admin_users SET role='content', is_active=0, account_slot=NULL WHERE id<>?");
            $others->execute([(int)$firstId]);
        }
    }

    $index = $pdo->query("SHOW INDEX FROM admin_users WHERE Key_name = 'uq_admin_account_slot'")->fetch();
    if (!$index) {
        $pdo->exec('ALTER TABLE admin_users ADD UNIQUE KEY uq_admin_account_slot (account_slot)');
    }
}

/**
 * Multi-photo gallery for each activity. Existing cover images are migrated
 * into the gallery once (and safely ignored on subsequent requests).
 */
function ensure_activity_gallery_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS kegiatan_foto (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        kegiatan_id BIGINT UNSIGNED NOT NULL,
        gambar VARCHAR(255) NOT NULL,
        urutan INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_kegiatan_foto_kegiatan FOREIGN KEY (kegiatan_id)
            REFERENCES kegiatan(id) ON DELETE CASCADE,
        UNIQUE KEY uq_kegiatan_gambar (kegiatan_id, gambar),
        INDEX idx_kegiatan_foto_urutan (kegiatan_id, urutan)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Carry the old single cover photo forward as the first gallery image.
    $pdo->exec("INSERT IGNORE INTO kegiatan_foto (kegiatan_id, gambar, urutan)
        SELECT k.id, k.gambar, 1
        FROM kegiatan k
        WHERE k.gambar IS NOT NULL AND k.gambar <> ''");
}

function current_admin(): ?array
{
    static $loaded = false;
    static $admin = null;
    if ($loaded) return $admin;
    $loaded = true;

    if (!isset($_SESSION['admin_id'])) return null;
    try {
        $stmt = db()->prepare('SELECT id, name, username, role, is_active FROM admin_users WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$_SESSION['admin_id']]);
        $row = $stmt->fetch();
        if (!$row || (int)$row['is_active'] !== 1) return null;
        $admin = $row;
        $_SESSION['admin_name'] = $row['name'];
        $_SESSION['admin_username'] = $row['username'];
        $_SESSION['admin_role'] = $row['role'];
        return $admin;
    } catch (Throwable $e) {
        return null;
    }
}

function is_admin(): bool
{
    return current_admin() !== null;
}

function require_admin(): void
{
    if (!is_admin()) {
        unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_username'], $_SESSION['admin_role']);
        header('Location: login.php');
        exit;
    }
}

function admin_role_definitions(): array
{
    return [
        'content' => [
            'label' => 'Admin Konten & Media',
            'description' => 'Mengelola teks website, identitas, logo, banner, dan informasi kontak.',
            'permissions' => ['content'],
        ],
        'structure' => [
            'label' => 'Admin Struktur & Keanggotaan',
            'description' => 'Mengelola struktur organisasi, divisi, pengurus, dan data pendaftaran anggota.',
            'permissions' => ['structure', 'members'],
        ],
        'program' => [
            'label' => 'Admin Program & Kegiatan',
            'description' => 'Mengelola program, kegiatan, foto, jadwal, dan publikasi.',
            'permissions' => ['programs'],
        ],
        'reports' => [
            'label' => 'Admin Laporan & Pengawasan',
            'description' => 'Meninjau laporan aktivitas yang masuk, memantau tindak lanjut, dan memperbarui status penanganannya.',
            'permissions' => ['reports'],
        ],
    ];
}

function admin_role_label(?string $role = null): string
{
    $role = $role ?? (string)($_SESSION['admin_role'] ?? '');
    if ($role === 'developer') return 'Developer · Akses Penuh';
    $roles = admin_role_definitions();
    return $roles[$role]['label'] ?? 'Administrator';
}

function has_permission(string $permission): bool
{
    $admin = current_admin();
    if (!$admin) return false;
    if ($admin['role'] === 'developer') return true;
    $roles = admin_role_definitions();
    return in_array($permission, $roles[$admin['role']]['permissions'] ?? [], true);
}

function require_permission(string $permission): void
{
    if (has_permission($permission)) return;
    http_response_code(403);
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Akses Ditolak</title><body style="font-family:Arial,sans-serif;padding:40px;max-width:650px;margin:auto"><h1>Akses Ditolak</h1><p>Akun Anda tidak memiliki izin untuk membuka bagian ini. Hubungi Developer KABANUSA jika membutuhkan akses.</p><p><a href="index.php">Kembali ke dashboard</a></p></body></html>';
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Sesi formulir tidak valid. Muat ulang halaman, lalu coba lagi.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($message) ? $message : null;
}

function upload_image(array $file, string $prefix = 'image'): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Unggahan gambar gagal.');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Ukuran gambar maksimal 5 MB.');
    }
    $tmp = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmp)) throw new RuntimeException('File unggahan tidak valid.');

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Format foto harus JPG, PNG, atau WEBP.');
    }

    $uploadDir = dirname(__DIR__) . '/uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Folder uploads tidak dapat dibuat.');
    }
    $filename = preg_replace('/[^a-z0-9_-]/i', '-', $prefix) . '_' . bin2hex(random_bytes(10)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($tmp, $uploadDir . '/' . $filename)) {
        throw new RuntimeException('Foto tidak dapat disimpan.');
    }
    return 'uploads/' . $filename;
}

function remove_uploaded_image(?string $path): void
{
    if (!$path || !str_starts_with($path, 'uploads/')) return;
    $fullPath = dirname(__DIR__) . '/' . $path;
    if (is_file($fullPath)) @unlink($fullPath);
}
