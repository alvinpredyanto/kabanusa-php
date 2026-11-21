<?php
require_once __DIR__ . '/includes/site.php';
ensure_structure_members_table();
public_header('struktur');
public_page_hero(
    'KARYA BAKTI ANAK NUSANTARA',
    site_setting('structure_page_title', 'Struktur Organisasi'),
    site_setting('structure_page_intro', 'Kenali susunan pengurus KABANUSA dan para penggerak organisasi.')
);
$rows = db()->query("SELECT * FROM struktur_anggota ORDER BY FIELD(kelompok,'pembina','inti','divisi'), nama_divisi ASC, urutan ASC, id ASC")->fetchAll();
$structureLayout = site_setting('structure_layout', 'cards');
if (!in_array($structureLayout, ['cards', 'hierarchy', 'list', 'gallery'], true)) $structureLayout = 'cards';
$groups = ['pembina' => [], 'inti' => [], 'divisi' => []];
foreach ($rows as $row) {
    $group = (string)$row['kelompok'];
    if (!isset($groups[$group])) continue;
    if ($group === 'divisi') {
        $division = trim((string)($row['nama_divisi'] ?? '')) ?: 'Divisi Lainnya';
        $groups['divisi'][$division][] = $row;
    } else {
        $groups[$group][] = $row;
    }
}
function kabanusa_structure_card(array $member, string $variant = ''): void {
    $photo = trim((string)($member['foto'] ?? ''));
    $name = (string)$member['nama_pengurus'];
    $role = (string)$member['nama_jabatan'];
    ?>
    <article class="org-member <?= $variant ?>">
      <div class="org-member__photo-wrap <?= $photo === '' ? 'org-member__photo-wrap--empty' : '' ?>">
        <?php if ($photo !== ''): ?>
          <img class="org-member__photo" src="<?= e($photo) ?>" alt="Foto <?= e($name) ?>" loading="lazy">
        <?php else: ?>
          <span aria-hidden="true">♙</span>
        <?php endif; ?>
      </div>
      <span class="org-member__role"><?= e($role) ?></span>
      <h3 class="org-member__name"><?= e($name) ?></h3>
    </article>
    <?php
}
?>
<main class="section structure-page structure-layout--<?= e($structureLayout) ?>">
  <div class="container">
    <div class="section-head center">
      <span class="eyebrow">BERSAMA DALAM SATU TUJUAN</span>
      <h2>Susunan kepengurusan</h2>
      <p>Setiap bagian dan divisi memiliki ruang kerja masing-masing untuk bergerak bersama KABANUSA.</p>
    </div>

    <?php if ($groups['pembina']): ?>
      <section class="org-section" aria-labelledby="org-pembina">
        <div class="org-section-heading">
          <span class="org-section-heading__eyebrow">PENGARAH ORGANISASI</span>
          <h2 id="org-pembina">Dewan Pembina & Pengawas</h2>
          <p>Pembinaan, arahan, dan pengawasan organisasi.</p>
        </div>
        <div class="org-grid org-grid--top org-grid--layout">
          <?php foreach ($groups['pembina'] as $member) kabanusa_structure_card($member, 'org-member--top'); ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($groups['inti']): ?>
      <section class="org-section" aria-labelledby="org-inti">
        <div class="org-section-heading">
          <span class="org-section-heading__eyebrow">PENGELOLA ORGANISASI</span>
          <h2 id="org-inti">Pengurus Inti</h2>
          <p>Koordinasi dan pengelolaan kegiatan organisasi.</p>
        </div>
        <div class="org-grid org-grid--middle org-grid--layout">
          <?php foreach ($groups['inti'] as $member) kabanusa_structure_card($member, 'org-member--middle'); ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($groups['divisi']): ?>
      <section class="org-section" aria-labelledby="org-divisi">
        <div class="org-section-heading">
          <span class="org-section-heading__eyebrow">BIDANG KERJA ORGANISASI</span>
          <h2 id="org-divisi">Divisi & Koordinator</h2>
          <p>Setiap divisi tampil dalam kotak otomatis. Jumlah kolom dan baris menyesuaikan jumlah divisi, anggota, serta ukuran layar.</p>
        </div>
        <div class="division-list">
          <?php foreach ($groups['divisi'] as $divisionName => $members): ?>
            <section class="division-block" aria-label="<?= e($divisionName) ?>">
              <div class="division-block__heading">
                <div><span class="division-block__label">DIVISI</span><h3><?= e($divisionName) ?></h3></div>
                <span class="division-block__count"><?= count($members) ?> anggota</span>
              </div>
              <div class="org-grid org-grid--division org-grid--layout">
                <?php foreach ($members as $member) kabanusa_structure_card($member); ?>
              </div>
            </section>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if (!$rows): ?>
      <div class="empty-state">Data struktur organisasi belum tersedia. Silakan tambahkan anggota melalui panel administrasi.</div>
    <?php endif; ?>
  </div>
</main>
<?php public_footer(); ?>
