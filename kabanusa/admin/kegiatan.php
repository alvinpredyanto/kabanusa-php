<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../includes/admin-ui.php';
$pdo = db();

/** Normalize a multiple-file input into the shape expected by upload_image(). */
function kegiatan_uploaded_files(): array
{
    if (!isset($_FILES['foto_kegiatan']) || !is_array($_FILES['foto_kegiatan']['name'] ?? null)) return [];
    $input = $_FILES['foto_kegiatan'];
    $files = [];
    foreach ($input['name'] as $i => $name) {
        $error = (int)($input['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('Salah satu foto gagal diunggah. Periksa ukuran file dan batas upload server.');
        $files[] = [
            'name' => (string)$name,
            'type' => (string)($input['type'][$i] ?? ''),
            'tmp_name' => (string)($input['tmp_name'][$i] ?? ''),
            'error' => $error,
            'size' => (int)($input['size'][$i] ?? 0),
        ];
    }
    if (count($files) > 12) throw new RuntimeException('Maksimal 12 foto tambahan dalam satu kali unggah.');
    return $files;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'save');
    $newFiles = [];
    $uploadedPaths = [];
    $removedPaths = [];
    try {
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $q = $pdo->prepare('SELECT gambar FROM kegiatan WHERE id=?');
            $q->execute([$id]);
            $cover = $q->fetchColumn();
            if ($cover === false) throw new RuntimeException('Kegiatan tidak ditemukan.');
            $q = $pdo->prepare('SELECT gambar FROM kegiatan_foto WHERE kegiatan_id=?');
            $q->execute([$id]);
            $removedPaths = array_column($q->fetchAll(), 'gambar');
            if (is_string($cover) && $cover !== '') $removedPaths[] = $cover;
            $pdo->prepare('DELETE FROM kegiatan WHERE id=?')->execute([$id]);
            foreach (array_unique($removedPaths) as $path) remove_uploaded_image($path);
            flash('success', 'Kegiatan dan foto-fotonya berhasil dihapus.');
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $wasEdit = $id > 0;
            $judul = trim((string)($_POST['judul'] ?? ''));
            $kategori = trim((string)($_POST['kategori'] ?? ''));
            $tanggalInput = trim((string)($_POST['tanggal'] ?? ''));
            $tanggal = $tanggalInput !== '' ? $tanggalInput : null;
            $deskripsi = trim((string)($_POST['deskripsi'] ?? ''));
            $status = (($_POST['status'] ?? 'draft') === 'publikasi') ? 'publikasi' : 'draft';
            if ($judul === '' || $kategori === '' || $deskripsi === '') {
                throw new RuntimeException('Judul, kategori, dan deskripsi wajib diisi.');
            }
            if ($tanggal !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
                throw new RuntimeException('Format tanggal tidak valid.');
            }

            $newFiles = kegiatan_uploaded_files();
            $currentPhotos = [];
            if ($id > 0) {
                $q = $pdo->prepare('SELECT id FROM kegiatan WHERE id=?');
                $q->execute([$id]);
                if (!$q->fetchColumn()) throw new RuntimeException('Kegiatan yang akan diedit tidak ditemukan.');
                $q = $pdo->prepare('SELECT id, gambar FROM kegiatan_foto WHERE kegiatan_id=? ORDER BY urutan ASC, id ASC');
                $q->execute([$id]);
                $currentPhotos = $q->fetchAll();
            }

            $removeIds = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['hapus_foto'] ?? [])), static fn($v) => $v > 0)));
            $existingById = [];
            foreach ($currentPhotos as $photo) $existingById[(int)$photo['id']] = $photo;
            $validRemoveIds = array_values(array_filter($removeIds, static fn($photoId) => isset($existingById[$photoId])));
            $remainingCount = count($currentPhotos) - count($validRemoveIds) + count($newFiles);
            if ($status === 'publikasi' && $remainingCount < 3) {
                throw new RuntimeException('Sebelum dipublikasikan, setiap kegiatan wajib memiliki minimal 3 foto. Foto yang ada: ' . $remainingCount . '. Tambahkan ' . (3 - $remainingCount) . ' foto lagi.');
            }

            // Save validated image files first; clean them up if the database save fails.
            foreach ($newFiles as $file) {
                $path = upload_image($file, 'kegiatan');
                if ($path) $uploadedPaths[] = $path;
            }

            $pdo->beginTransaction();
            if ($id > 0) {
                foreach ($validRemoveIds as $photoId) {
                    $removedPaths[] = $existingById[$photoId]['gambar'];
                    $pdo->prepare('DELETE FROM kegiatan_foto WHERE id=? AND kegiatan_id=?')->execute([$photoId, $id]);
                }
            } else {
                $pdo->prepare('INSERT INTO kegiatan (judul,kategori,tanggal,deskripsi,status,gambar) VALUES (?,?,?,?,?,NULL)')
                    ->execute([$judul, $kategori, $tanggal, $deskripsi, $status]);
                $id = (int)$pdo->lastInsertId();
            }

            $q = $pdo->prepare('SELECT COALESCE(MAX(urutan),0) FROM kegiatan_foto WHERE kegiatan_id=?');
            $q->execute([$id]);
            $order = (int)$q->fetchColumn();
            $insertPhoto = $pdo->prepare('INSERT INTO kegiatan_foto (kegiatan_id,gambar,urutan) VALUES (?,?,?)');
            foreach ($uploadedPaths as $path) {
                $order++;
                $insertPhoto->execute([$id, $path, $order]);
            }

            $q = $pdo->prepare('SELECT gambar FROM kegiatan_foto WHERE kegiatan_id=? ORDER BY urutan ASC, id ASC LIMIT 1');
            $q->execute([$id]);
            $cover = $q->fetchColumn();
            if ($cover === false) $cover = null;
            $pdo->prepare('UPDATE kegiatan SET judul=?,kategori=?,tanggal=?,deskripsi=?,status=?,gambar=? WHERE id=?')
                ->execute([$judul, $kategori, $tanggal, $deskripsi, $status, $cover, $id]);
            $pdo->commit();

            foreach (array_unique($removedPaths) as $path) remove_uploaded_image($path);
            flash('success', $wasEdit ? 'Kegiatan berhasil disimpan. Galeri foto telah diperbarui.' : 'Kegiatan baru berhasil ditambahkan.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        foreach ($uploadedPaths as $path) remove_uploaded_image($path);
        flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'Gagal menyimpan kegiatan. Periksa database dan folder uploads.');
    }
    header('Location: kegiatan.php');
    exit;
}

$edit = null;
$editPhotos = [];
if (isset($_GET['edit'])) {
    $q = $pdo->prepare('SELECT * FROM kegiatan WHERE id=?');
    $q->execute([(int)$_GET['edit']]);
    $edit = $q->fetch() ?: null;
    if ($edit) {
        $q = $pdo->prepare('SELECT id,gambar FROM kegiatan_foto WHERE kegiatan_id=? ORDER BY urutan ASC,id ASC');
        $q->execute([(int)$edit['id']]);
        $editPhotos = $q->fetchAll();
    }
}
$rows = $pdo->query('SELECT k.*, (SELECT COUNT(*) FROM kegiatan_foto f WHERE f.kegiatan_id=k.id) AS jumlah_foto FROM kegiatan k ORDER BY k.created_at DESC')->fetchAll();
admin_header('Kelola Kegiatan', 'kegiatan');
?>
<section class="panel">
  <div class="panel-head"><div><h2><?= $edit ? 'Edit kegiatan' : 'Tambah kegiatan' ?></h2><p class="panel-subtitle">Setiap kegiatan yang dipublikasikan wajib memiliki minimal 3 foto. Anda boleh mengunggah lebih banyak foto untuk dokumentasi lengkap.</p></div></div>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div class="form-grid">
      <div class="field"><label>Judul kegiatan</label><input name="judul" required maxlength="180" value="<?= e($edit['judul'] ?? '') ?>"></div>
      <div class="field"><label>Kategori</label><input name="kategori" required maxlength="100" placeholder="Contoh: Sosial, Pendidikan" value="<?= e($edit['kategori'] ?? '') ?>"></div>
      <div class="field"><label>Tanggal</label><input type="date" name="tanggal" value="<?= e($edit['tanggal'] ?? '') ?>"></div>
      <div class="field"><label>Status</label><select name="status"><option value="draft" <?= ($edit['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option><option value="publikasi" <?= ($edit['status'] ?? '') === 'publikasi' ? 'selected' : '' ?>>Publikasikan</option></select></div>
    </div>
    <div class="field"><label>Deskripsi</label><textarea name="deskripsi" required><?= e($edit['deskripsi'] ?? '') ?></textarea></div>
    <?php if ($edit && $editPhotos): ?>
      <div class="field"><label>Foto yang sudah tersimpan (<?= count($editPhotos) ?>)</label>
        <div class="activity-photo-manage">
          <?php foreach ($editPhotos as $photo): ?>
            <label class="activity-photo-item"><img src="../<?= e($photo['gambar']) ?>" alt="Foto kegiatan"><span><input type="checkbox" name="hapus_foto[]" value="<?= (int)$photo['id'] ?>"> Hapus foto ini</span></label>
          <?php endforeach; ?>
        </div>
        <div class="hint">Centang foto yang ingin dihapus. Pastikan jumlah foto tetap minimal 3 jika status kegiatan dipublikasikan.</div>
      </div>
    <?php elseif ($edit): ?>
      <div class="notice">Kegiatan ini belum memiliki foto galeri. Tambahkan minimal 3 foto sebelum dipublikasikan.</div>
    <?php endif; ?>
    <div class="field"><label for="activityPhotos">Upload foto kegiatan</label>
      <input id="activityPhotos" type="file" name="foto_kegiatan[]" accept="image/jpeg,image/png,image/webp" multiple>
      <div class="hint">Pilih minimal 3 foto untuk publikasi. JPG, PNG, atau WEBP; maksimal 5 MB per foto; maksimal 12 foto tambahan sekali unggah.</div>
      <div class="hint" id="activityPhotoCount" aria-live="polite">Belum ada foto baru dipilih.</div>
      <div class="activity-upload-preview" id="activityPhotoPreview"></div>
    </div>
    <button class="btn btn-gold" type="submit"><?= $edit ? 'Simpan perubahan' : 'Tambah kegiatan' ?></button>
    <?php if ($edit): ?> <a class="btn btn-light" href="kegiatan.php">Batal</a><?php endif; ?>
  </form>
</section>
<section class="panel">
  <div class="panel-head"><div><h2>Daftar kegiatan</h2><p class="panel-subtitle"><?= count($rows) ?> data tersimpan.</p></div></div>
  <?php if (!$rows): ?><div class="empty">Belum ada kegiatan. Tambahkan kegiatan dan unggah minimal 3 foto sebelum dipublikasikan.</div>
  <?php else: ?><div class="table-wrap"><table><thead><tr><th>Foto Utama</th><th>Judul</th><th>Kategori</th><th>Tanggal</th><th>Galeri</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr>
      <td><?php if ($r['gambar']): ?><img class="image-thumb" src="../<?= e($r['gambar']) ?>" alt=""><?php else: ?>—<?php endif; ?></td>
      <td><?= e($r['judul']) ?></td><td><?= e($r['kategori']) ?></td><td><?= e($r['tanggal'] ?? '-') ?></td>
      <td><span class="pill <?= (int)$r['jumlah_foto'] >= 3 ? 'green' : 'orange' ?>"><?= (int)$r['jumlah_foto'] ?> foto<?= (int)$r['jumlah_foto'] >= 3 ? '' : ' · perlu ditambah' ?></span></td>
      <td><span class="pill <?= $r['status'] === 'publikasi' ? 'green' : 'orange' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
      <td><div class="table-actions"><a class="btn btn-light btn-sm" href="?edit=<?= (int)$r['id'] ?>">Edit</a><form class="inline-form" method="post" onsubmit="return confirm('Hapus kegiatan ini beserta seluruh fotonya?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-sm" type="submit">Hapus</button></form></div></td>
    </tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>
<script>
(() => {
  const input = document.getElementById('activityPhotos');
  const output = document.getElementById('activityPhotoCount');
  const preview = document.getElementById('activityPhotoPreview');
  if (!input || !output || !preview) return;
  let previousUrls = [];
  input.addEventListener('change', () => {
    previousUrls.forEach(url => URL.revokeObjectURL(url));
    previousUrls = [];
    preview.replaceChildren();
    const files = Array.from(input.files || []);
    output.textContent = files.length ? `${files.length} foto baru dipilih. Foto lama tetap tersimpan kecuali dicentang untuk dihapus.` : 'Belum ada foto baru dipilih.';
    files.forEach(file => {
      if (!file.type.startsWith('image/')) return;
      const url = URL.createObjectURL(file);
      previousUrls.push(url);
      const item = document.createElement('div');
      item.className = 'activity-upload-preview__item';
      const img = document.createElement('img');
      img.src = url;
      img.alt = file.name;
      const name = document.createElement('small');
      name.textContent = file.name;
      item.append(img, name);
      preview.append(item);
    });
  });
})();
</script>
<?php admin_footer(); ?>
