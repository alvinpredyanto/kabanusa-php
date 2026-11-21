<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../includes/admin-ui.php';
$pdo = db();
$roles = admin_role_definitions();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'create') {
            $role = (string)($_POST['role'] ?? '');
            $name = trim((string)($_POST['name'] ?? ''));
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            if (!isset($roles[$role])) throw new RuntimeException('Pilih salah satu tugas Admin yang tersedia.');
            if ($name === '' || !preg_match('/^[a-zA-Z0-9._-]{4,60}$/', $username)) {
                throw new RuntimeException('Nama wajib diisi. Username harus 4–60 karakter dan hanya berisi huruf, angka, titik, garis bawah, atau tanda hubung.');
            }
            if (strlen($password) < 12) throw new RuntimeException('Password Admin minimal 12 karakter.');

            $check = $pdo->prepare('SELECT id FROM admin_users WHERE account_slot = ? LIMIT 1');
            $check->execute([$role]);
            if ($check->fetchColumn()) throw new RuntimeException('Posisi Admin ini sudah memiliki akun. Edit akun yang ada atau hapus akun tersebut terlebih dahulu.');

            $stmt = $pdo->prepare('INSERT INTO admin_users (name, username, password_hash, role, is_active, account_slot) VALUES (?, ?, ?, ?, 1, ?)');
            $stmt->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $role, $role]);
            flash('success', 'Akun ' . $roles[$role]['label'] . ' berhasil dibuat. Bagikan kredensialnya secara pribadi.');
            header('Location: kelola-akun.php'); exit;
        }

        if ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $active = isset($_POST['is_active']) ? 1 : 0;

            if ($name === '' || !preg_match('/^[a-zA-Z0-9._-]{4,60}$/', $username)) {
                throw new RuntimeException('Nama wajib diisi dan username harus 4–60 karakter.');
            }
            if ($password !== '' && strlen($password) < 12) throw new RuntimeException('Password baru minimal 12 karakter.');
            $find = $pdo->prepare("SELECT id, role FROM admin_users WHERE id = ? AND role <> 'developer' AND account_slot IS NOT NULL LIMIT 1");
            $find->execute([$id]);
            $target = $find->fetch();
            if (!$target) throw new RuntimeException('Akun Admin tidak ditemukan. Akun Developer tidak dapat diubah dari halaman ini.');

            if ($password !== '') {
                $stmt = $pdo->prepare('UPDATE admin_users SET name=?, username=?, password_hash=?, is_active=? WHERE id=?');
                $stmt->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $active, $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE admin_users SET name=?, username=?, is_active=? WHERE id=?');
                $stmt->execute([$name, $username, $active, $id]);
            }
            flash('success', 'Data akun Admin berhasil diperbarui.');
            header('Location: kelola-akun.php'); exit;
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = ? AND role <> 'developer' AND account_slot IS NOT NULL");
            $stmt->execute([$id]);
            if ($stmt->rowCount() < 1) throw new RuntimeException('Akun tidak ditemukan atau tidak dapat dihapus.');
            flash('success', 'Akun Admin berhasil dihapus. Posisi tugasnya sekarang tersedia untuk akun baru.');
            header('Location: kelola-akun.php'); exit;
        }
        throw new RuntimeException('Aksi tidak dikenal.');
    } catch (Throwable $e) {
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'Perubahan akun gagal disimpan. Periksa username agar tidak duplikat.';
    }
}

$admins = $pdo->query("SELECT id, name, username, role, is_active, created_at FROM admin_users WHERE role <> 'developer' AND account_slot IS NOT NULL ORDER BY FIELD(role,'content','structure','program','reports'), id")->fetchAll();
$byRole = [];
foreach ($admins as $admin) $byRole[$admin['role']] = $admin;
$developer = $pdo->query("SELECT name, username, created_at FROM admin_users WHERE role='developer' LIMIT 1")->fetch() ?: null;

admin_header('Kelola Akun & Hak Akses', 'kelola-akun');
?>
<section class="welcome"><h2>Manajemen akses KABANUSA</h2><p>Satu akun Developer memiliki kendali penuh. Empat akun Admin memiliki tugas terpisah; setiap tugas hanya dapat diisi satu akun aktif pada satu waktu.</p></section>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<section class="panel"><div class="panel-head"><div><h2>Akun Developer</h2><p class="panel-subtitle">Pemilik sistem dengan akses ke seluruh menu dan pengelolaan akun Admin.</p></div><span class="pill green">Akses penuh</span></div>
<?php if ($developer): ?><div class="account-card"><div><b><?= e($developer['name']) ?></b><p>@<?= e($developer['username']) ?></p><small>Dibuat <?= e($developer['created_at']) ?></small></div><a class="btn btn-light btn-sm" href="akun.php">Kelola akun saya</a></div><?php else: ?><div class="empty">Belum ada akun Developer. Jalankan setup awal terlebih dahulu.</div><?php endif; ?></section>
<section class="panel"><div class="panel-head"><div><h2>Empat akun Admin berdasarkan tugas</h2><p class="panel-subtitle">Isi setiap slot dengan nama dan kredensial Admin yang berbeda. Password tidak ditampilkan kembali setelah disimpan.</p></div><span class="pill"><?= count($admins) ?>/4 terisi</span></div>
<div class="role-grid">
<?php foreach ($roles as $role => $definition): $existing = $byRole[$role] ?? null; ?>
<article class="role-card">
 <div class="role-card-head"><div><span class="role-kicker">SLOT ADMIN</span><h3><?= e($definition['label']) ?></h3></div><span class="pill <?= $existing ? ((int)$existing['is_active']===1?'green':'orange') : '' ?>"><?= $existing ? ((int)$existing['is_active']===1?'Aktif':'Nonaktif') : 'Kosong' ?></span></div>
 <p class="role-description"><?= e($definition['description']) ?></p>
 <?php if (!$existing): ?>
 <form method="post" class="role-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create"><input type="hidden" name="role" value="<?= e($role) ?>">
  <div class="field"><label>Nama Admin</label><input name="name" required maxlength="120" placeholder="Nama petugas"></div>
  <div class="field"><label>Username login</label><input name="username" required minlength="4" maxlength="60" autocomplete="off"></div>
  <div class="field"><label>Password awal (minimal 12 karakter)</label><input type="password" name="password" required minlength="12" autocomplete="new-password"></div>
  <button class="btn btn-gold" type="submit">Buat akun Admin</button>
 </form>
 <?php else: ?>
 <div class="account-card account-card--compact"><div><b><?= e($existing['name']) ?></b><p>@<?= e($existing['username']) ?></p><small>Dibuat <?= e($existing['created_at']) ?></small></div></div>
 <form method="post" class="role-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$existing['id'] ?>">
  <div class="field"><label>Nama Admin</label><input name="name" required maxlength="120" value="<?= e($existing['name']) ?>"></div>
  <div class="field"><label>Username</label><input name="username" required minlength="4" maxlength="60" value="<?= e($existing['username']) ?>"></div>
  <div class="field"><label>Password baru (kosongkan jika tetap)</label><input type="password" name="password" minlength="12" autocomplete="new-password"></div>
  <label class="active-toggle"><input type="checkbox" name="is_active" value="1" <?= (int)$existing['is_active']===1?'checked':'' ?>> Akun aktif dan boleh login</label>
  <button class="btn btn-gold" type="submit">Simpan perubahan</button>
 </form>
 <form method="post" class="delete-account-form" onsubmit="return confirm('Hapus akun Admin ini? Slot tugas akan menjadi kosong.')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$existing['id'] ?>"><button class="btn btn-danger btn-sm" type="submit">Hapus akun Admin</button></form>
 <?php endif; ?>
</article>
<?php endforeach; ?>
</div></section>
<section class="panel"><div class="panel-head"><div><h2>Aturan keamanan akun</h2><p class="panel-subtitle">Pembagian akses diterapkan di server, sehingga menebak alamat halaman admin lain tidak akan membuka akses.</p></div></div><ul class="security-list"><li>Hanya Developer dapat membuat, menonaktifkan, mengaktifkan, mengubah password, atau menghapus akun Admin.</li><li>Setiap slot Admin hanya dapat memiliki satu akun: Konten & Media, Struktur & Keanggotaan, Program & Kegiatan, serta Laporan & Pengawasan.</li><li>Admin tidak dapat mengubah peran atau membuat akun lain.</li><li>Admin yang dinonaktifkan akan ditolak saat login dan sesi aktifnya diblokir saat halaman berikutnya dimuat.</li><li>Gunakan password yang berbeda dan minimal 12 karakter untuk setiap akun.</li></ul></section>
<?php admin_footer(); ?>
