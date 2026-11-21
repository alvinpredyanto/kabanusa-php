<?php
require_once __DIR__ . '/includes/site.php';
public_header('kegiatan');
public_page_hero(
    site_setting('activities_eyebrow', 'KABANUSA BERGERAK'),
    site_setting('activities_title', 'Kegiatan & Aksi Nyata'),
    site_setting('activities_intro', 'Dokumentasi kegiatan, aksi sosial, dan agenda kolaborasi KABANUSA.')
);
$rows = db()->query("SELECT * FROM kegiatan WHERE status='publikasi' ORDER BY COALESCE(tanggal, DATE(created_at)) DESC,id DESC")->fetchAll();
$galleries = [];
if ($rows) {
    $ids = array_map(static fn($row) => (int)$row['id'], $rows);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT kegiatan_id,gambar FROM kegiatan_foto WHERE kegiatan_id IN ($placeholders) ORDER BY urutan ASC,id ASC");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $photo) {
        $galleries[(int)$photo['kegiatan_id']][] = $photo['gambar'];
    }
}
?>
<main class="section">
  <div class="container">
    <?php if (!$rows): ?>
      <div class="empty-state">Belum ada kegiatan yang dipublikasikan. Silakan kunjungi kembali halaman ini.</div>
    <?php else: ?>
      <div class="grid-3">
        <?php foreach ($rows as $r): ?>
          <?php
            $activityPhotos = $galleries[(int)$r['id']] ?? [];
            if (!$activityPhotos && !empty($r['gambar'])) $activityPhotos = [$r['gambar']];
          ?>
          <article class="card content-card activity-card">
            <?php if ($activityPhotos): ?>
              <div class="activity-gallery" aria-label="Galeri foto <?= e($r['judul']) ?>">
                <?php foreach ($activityPhotos as $index => $photoPath): ?>
                  <a class="activity-gallery__item" href="<?= e($photoPath) ?>" target="_blank" rel="noopener" aria-label="Buka foto <?= (int)$index + 1 ?> kegiatan <?= e($r['judul']) ?>">
                    <img src="<?= e($photoPath) ?>" alt="<?= e($r['judul']) ?> — foto <?= (int)$index + 1 ?>" loading="lazy" decoding="async">
                  </a>
                <?php endforeach; ?>
              </div>
              <div class="activity-gallery-caption"><span>Galeri dokumentasi</span><strong><?= count($activityPhotos) ?> foto</strong></div>
            <?php else: ?>
              <div class="content-card-placeholder">KABANUSA</div>
            <?php endif; ?>
            <small class="muted"><?= e($r['kategori']) ?><?= $r['tanggal'] ? ' · ' . e(date('d M Y', strtotime($r['tanggal']))) : '' ?></small>
            <h3><?= e($r['judul']) ?></h3>
            <p><?= nl2br(e($r['deskripsi'])) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
<?php public_footer(); ?>
