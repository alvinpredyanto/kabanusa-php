<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../includes/site.php';
require_once __DIR__ . '/../includes/admin-ui.php';

$pdo = db();
ensure_structure_members_table();
$allowedGroups = ['pembina', 'inti', 'divisi'];
$length = static function (string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save_layout') {
            $layout = (string)($_POST['structure_layout'] ?? '');
            $allowedLayouts = ['cards', 'hierarchy', 'list', 'gallery'];
            if (!in_array($layout, $allowedLayouts, true)) {
                throw new RuntimeException('Pilih model tampilan struktur yang tersedia.');
            }
            $saveLayout = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            $saveLayout->execute(['structure_layout', $layout]);
            flash('success', 'Model tampilan struktur berhasil disimpan dan diterapkan pada website publik.');
        } elseif ($action === 'add' || $action === 'update') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $kelompok = (string)($_POST['kelompok'] ?? '');
            $divisi = trim((string)($_POST['nama_divisi'] ?? ''));
            $jabatan = trim((string)($_POST['nama_jabatan'] ?? ''));
            $nama = trim((string)($_POST['nama_pengurus'] ?? ''));

            if (!in_array($kelompok, $allowedGroups, true)) throw new RuntimeException('Pilih bagian organisasi yang valid.');
            if ($kelompok !== 'divisi') $divisi = '';
            if ($kelompok === 'divisi' && $divisi === '') throw new RuntimeException('Nama divisi wajib diisi untuk anggota divisi.');
            if ($jabatan === '' || $nama === '') throw new RuntimeException('Nama jabatan dan nama anggota wajib diisi.');
            if ($length($divisi) > 120 || $length($jabatan) > 150 || $length($nama) > 150) {
                throw new RuntimeException('Nama divisi maksimal 120 karakter, jabatan dan nama anggota maksimal 150 karakter.');
            }
            $divisionValue = $divisi !== '' ? $divisi : null;
            $newPhoto = upload_image($_FILES['foto'] ?? [], 'struktur');

            if ($action === 'add') {
                if ($kelompok === 'divisi') {
                    $sort = $pdo->prepare("SELECT COALESCE(MAX(urutan),0)+1 FROM struktur_anggota WHERE kelompok = 'divisi' AND nama_divisi = ?");
                    $sort->execute([$divisionValue]);
                } else {
                    $sort = $pdo->prepare('SELECT COALESCE(MAX(urutan),0)+1 FROM struktur_anggota WHERE kelompok = ?');
                    $sort->execute([$kelompok]);
                }
                $insert = $pdo->prepare('INSERT INTO struktur_anggota (kelompok, nama_divisi, nama_jabatan, nama_pengurus, foto, urutan) VALUES (?, ?, ?, ?, ?, ?)');
                $insert->execute([$kelompok, $divisionValue, $jabatan, $nama, $newPhoto, (int)$sort->fetchColumn()]);
                flash('success', 'Anggota baru berhasil ditambahkan ke struktur organisasi.');
            } else {
                if (!$id) throw new RuntimeException('Data anggota tidak valid.');
                $find = $pdo->prepare('SELECT foto FROM struktur_anggota WHERE id = ?');
                $find->execute([$id]);
                $oldPhoto = $find->fetchColumn();
                if ($oldPhoto === false) throw new RuntimeException('Anggota tidak ditemukan.');
                if ($newPhoto !== null) {
                    $update = $pdo->prepare('UPDATE struktur_anggota SET kelompok=?, nama_divisi=?, nama_jabatan=?, nama_pengurus=?, foto=? WHERE id=?');
                    $update->execute([$kelompok, $divisionValue, $jabatan, $nama, $newPhoto, $id]);
                    remove_uploaded_image(is_string($oldPhoto) ? $oldPhoto : null);
                } else {
                    $update = $pdo->prepare('UPDATE struktur_anggota SET kelompok=?, nama_divisi=?, nama_jabatan=?, nama_pengurus=? WHERE id=?');
                    $update->execute([$kelompok, $divisionValue, $jabatan, $nama, $id]);
                }
                flash('success', 'Data anggota berhasil diperbarui.');
            }
        } elseif ($action === 'delete') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) throw new RuntimeException('Data anggota tidak valid.');
            $find = $pdo->prepare('SELECT foto FROM struktur_anggota WHERE id = ?');
            $find->execute([$id]);
            $photo = $find->fetchColumn();
            if ($photo === false) throw new RuntimeException('Anggota tidak ditemukan.');
            $delete = $pdo->prepare('DELETE FROM struktur_anggota WHERE id = ?');
            $delete->execute([$id]);
            remove_uploaded_image(is_string($photo) ? $photo : null);
            flash('success', 'Anggota berhasil dihapus dari struktur.');
        } else {
            throw new RuntimeException('Aksi tidak dikenal.');
        }
    } catch (Throwable $e) {
        flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'Perubahan struktur gagal disimpan. Periksa koneksi database dan folder uploads.');
    }
    header('Location: struktur.php');
    exit;
}

$rows = $pdo->query("SELECT * FROM struktur_anggota ORDER BY FIELD(kelompok,'pembina','inti','divisi'), nama_divisi ASC, urutan ASC, id ASC")->fetchAll();
$currentLayout = site_setting('structure_layout', 'cards');
$layoutOptions = [
    'cards' => ['title' => 'Kartu Divisi', 'description' => 'Kartu anggota dengan foto bulat dan pembagian divisi yang jelas.'],
    'hierarchy' => ['title' => 'Bagan Hierarki', 'description' => 'Menampilkan tingkatan Pembina, Pengurus Inti, lalu Divisi dengan garis penghubung.'],
    'list' => ['title' => 'Daftar Ringkas', 'description' => 'Tampilan horizontal yang hemat ruang dan mudah dibaca di perangkat kecil.'],
    'gallery' => ['title' => 'Galeri Foto', 'description' => 'Foto anggota lebih besar dalam bentuk galeri kartu visual.'],
];
$groupLabels = ['pembina' => 'Dewan Pembina & Pengawas', 'inti' => 'Pengurus Inti'];
$grouped = ['pembina' => [], 'inti' => [], 'divisi' => []];
foreach ($rows as $row) {
    $key = (string)$row['kelompok'];
    if ($key === 'divisi') {
        $divisionName = trim((string)($row['nama_divisi'] ?? '')) ?: 'Divisi Lainnya';
        $grouped['divisi'][$divisionName][] = $row;
    } elseif (isset($grouped[$key])) {
        $grouped[$key][] = $row;
    }
}

admin_header('Struktur Pengurus', 'struktur');
?>
<section class="panel">
  <div class="panel-head"><div><h2>Model tampilan struktur website</h2><p class="panel-subtitle">Developer dan Admin Struktur dapat memilih salah satu dari empat model. Pilihan ini berlaku untuk semua pengunjung website.</p></div></div>
  <form method="post" class="structure-layout-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_layout">
    <div class="structure-layout-options">
      <?php foreach ($layoutOptions as $key => $option): ?>
        <label class="structure-layout-option <?= $currentLayout === $key ? 'is-selected' : '' ?>">
          <input type="radio" name="structure_layout" value="<?= e($key) ?>" <?= $currentLayout === $key ? 'checked' : '' ?> required>
          <span class="structure-layout-option__preview structure-layout-option__preview--<?= e($key) ?>" aria-hidden="true"><i></i><i></i><i></i></span>
          <span class="structure-layout-option__title"><?= e($option['title']) ?></span>
          <span class="structure-layout-option__description"><?= e($option['description']) ?></span>
          <span class="structure-layout-option__selected">✓ Dipilih</span>
        </label>
      <?php endforeach; ?>
    </div>
    <div class="sticky-save"><button class="btn btn-gold" type="submit">Simpan Tampilan Struktur</button><a class="btn btn-light" href="../struktur.php" target="_blank" rel="noopener">Lihat Pratinjau Website ↗</a><span class="hint">Model aktif: <strong><?= e($layoutOptions[$currentLayout]['title'] ?? 'Kartu Divisi') ?></strong></span></div>
  </form>
</section>
<section class="panel">
  <div class="panel-head"><div><h2>Tambah anggota baru</h2><p class="panel-subtitle">Tambahkan pengurus baru kapan saja. Pilih divisi untuk menempatkan anggota pada kelompok yang benar. Kotak divisi dan baris anggota akan tersusun otomatis saat ada penambahan.</p></div></div>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="add">
    <div class="structure-add-grid">
      <div class="field"><label for="new-group">Bagian organisasi</label><select id="new-group" name="kelompok" required><option value="pembina">Dewan Pembina & Pengawas</option><option value="inti">Pengurus Inti</option><option value="divisi" selected>Divisi</option></select></div>
      <div class="field" id="new-division-field"><label for="new-division">Nama divisi</label><input id="new-division" name="nama_divisi" maxlength="120" placeholder="Contoh: Humas & Media Kreatif"><div class="hint">Gunakan nama yang sama agar anggota masuk ke divisi yang sama.</div></div>
      <div class="field"><label for="new-role">Jabatan / posisi</label><input id="new-role" name="nama_jabatan" maxlength="150" required placeholder="Contoh: Koordinator Divisi"></div>
      <div class="field"><label for="new-name">Nama anggota</label><input id="new-name" name="nama_pengurus" maxlength="150" required placeholder="Nama lengkap anggota"></div>
      <div class="field"><label for="new-photo">Foto anggota (opsional)</label><input id="new-photo" type="file" name="foto" accept="image/jpeg,image/png,image/webp"><div class="hint">JPG, PNG, atau WEBP. Maksimal 5 MB.</div></div>
    </div>
    <button class="btn btn-gold" type="submit">＋ Tambah Anggota</button>
  </form>
</section>

<?php foreach ($groupLabels as $key => $label): ?>
  <section class="panel">
    <div class="panel-head"><div><h2><?= e($label) ?></h2><p class="panel-subtitle"><?= count($grouped[$key]) ?> anggota</p></div></div>
    <?php if (!$grouped[$key]): ?><div class="empty">Belum ada anggota pada bagian ini.</div><?php else: ?>
      <div class="structure-admin-grid">
      <?php foreach ($grouped[$key] as $member): ?>
        <article class="structure-admin-card">
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$member['id'] ?>">
            <div class="structure-admin-card__top">
              <?php if (!empty($member['foto'])): ?><img src="../<?= e($member['foto']) ?>" alt="Foto <?= e($member['nama_pengurus']) ?>" class="structure-admin-photo"><?php else: ?><div class="structure-admin-photo structure-admin-photo--empty">♙</div><?php endif; ?>
              <div><span class="pill">ID #<?= (int)$member['id'] ?></span><h3><?= e($member['nama_pengurus']) ?></h3><p><?= e($member['nama_jabatan']) ?></p></div>
            </div>
            <div class="field"><label>Bagian organisasi</label><select name="kelompok" class="member-group-select"><option value="pembina" <?= $member['kelompok']==='pembina'?'selected':'' ?>>Dewan Pembina & Pengawas</option><option value="inti" <?= $member['kelompok']==='inti'?'selected':'' ?>>Pengurus Inti</option><option value="divisi" <?= $member['kelompok']==='divisi'?'selected':'' ?>>Divisi</option></select></div>
            <div class="field member-division-field" style="<?= $member['kelompok']==='divisi'?'':'display:none' ?>"><label>Nama divisi</label><input name="nama_divisi" value="<?= e((string)($member['nama_divisi'] ?? '')) ?>" maxlength="120" placeholder="Nama divisi"></div>
            <div class="field"><label>Jabatan / posisi</label><input name="nama_jabatan" value="<?= e($member['nama_jabatan']) ?>" maxlength="150" required></div>
            <div class="field"><label>Nama anggota</label><input name="nama_pengurus" value="<?= e($member['nama_pengurus']) ?>" maxlength="150" required></div>
            <div class="field"><label>Ganti foto</label><input type="file" name="foto" accept="image/jpeg,image/png,image/webp"><div class="hint">Kosongkan jika tidak ingin mengganti foto.</div></div>
            <button class="btn btn-gold" type="submit">Simpan Perubahan</button>
          </form>
          <form method="post" class="structure-delete-form" onsubmit="return confirm('Hapus anggota ini dari struktur organisasi?')">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$member['id'] ?>">
            <button class="btn btn-danger btn-sm" type="submit">Hapus Anggota</button>
          </form>
        </article>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
<?php endforeach; ?>

<?php if ($grouped['divisi']): ?>
  <div class="admin-division-grid" aria-label="Daftar divisi organisasi">
  <?php foreach ($grouped['divisi'] as $divisionName => $members): ?>
    <section class="panel admin-division-panel">
      <div class="panel-head"><div><span class="section-label">DIVISI</span><h2><?= e($divisionName) ?></h2><p class="panel-subtitle"><?= count($members) ?> anggota · Kotak divisi dan baris anggota menyesuaikan otomatis saat data bertambah.</p></div></div>
      <div class="structure-admin-grid">
        <?php foreach ($members as $member): ?>
          <article class="structure-admin-card">
            <form method="post" enctype="multipart/form-data">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$member['id'] ?>">
              <div class="structure-admin-card__top">
                <?php if (!empty($member['foto'])): ?><img src="../<?= e($member['foto']) ?>" alt="Foto <?= e($member['nama_pengurus']) ?>" class="structure-admin-photo"><?php else: ?><div class="structure-admin-photo structure-admin-photo--empty">♙</div><?php endif; ?>
                <div><span class="pill">ID #<?= (int)$member['id'] ?></span><h3><?= e($member['nama_pengurus']) ?></h3><p><?= e($member['nama_jabatan']) ?></p></div>
              </div>
              <div class="field"><label>Bagian organisasi</label><select name="kelompok" class="member-group-select"><option value="pembina">Dewan Pembina & Pengawas</option><option value="inti">Pengurus Inti</option><option value="divisi" selected>Divisi</option></select></div>
              <div class="field member-division-field"><label>Nama divisi</label><input name="nama_divisi" value="<?= e((string)($member['nama_divisi'] ?? '')) ?>" maxlength="120" placeholder="Nama divisi"></div>
              <div class="field"><label>Jabatan / posisi</label><input name="nama_jabatan" value="<?= e($member['nama_jabatan']) ?>" maxlength="150" required></div>
              <div class="field"><label>Nama anggota</label><input name="nama_pengurus" value="<?= e($member['nama_pengurus']) ?>" maxlength="150" required></div>
              <div class="field"><label>Ganti foto</label><input type="file" name="foto" accept="image/jpeg,image/png,image/webp"><div class="hint">Kosongkan jika tidak ingin mengganti foto.</div></div>
              <button class="btn btn-gold" type="submit">Simpan Perubahan</button>
            </form>
            <form method="post" class="structure-delete-form" onsubmit="return confirm('Hapus anggota ini dari struktur organisasi?')">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$member['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit">Hapus Anggota</button>
            </form>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
(function () {
  const newGroup = document.getElementById('new-group');
  const newDivisionField = document.getElementById('new-division-field');
  const newDivisionInput = document.getElementById('new-division');
  function toggleNewDivision() {
    const isDivision = newGroup && newGroup.value === 'divisi';
    if (newDivisionField) newDivisionField.style.display = isDivision ? '' : 'none';
    if (newDivisionInput) newDivisionInput.required = !!isDivision;
  }
  if (newGroup) { newGroup.addEventListener('change', toggleNewDivision); toggleNewDivision(); }

  document.querySelectorAll('.member-group-select').forEach(function (select) {
    const form = select.closest('form');
    const divisionField = form ? form.querySelector('.member-division-field') : null;
    const divisionInput = divisionField ? divisionField.querySelector('input[name="nama_divisi"]') : null;
    function toggleDivision() {
      const isDivision = select.value === 'divisi';
      if (divisionField) divisionField.style.display = isDivision ? '' : 'none';
      if (divisionInput) divisionInput.required = isDivision;
    }
    select.addEventListener('change', toggleDivision);
    toggleDivision();
  });
})();
</script>
<?php admin_footer(); ?>
