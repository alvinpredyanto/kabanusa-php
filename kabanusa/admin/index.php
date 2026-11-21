<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../includes/admin-ui.php';
$pdo = db();
$stats = [];
if (has_permission('structure')) {
    $stats['struktur'] = ['Posisi struktur', (int)$pdo->query('SELECT COUNT(*) FROM struktur_organisasi')->fetchColumn(), 'Data jabatan organisasi'];
}
if (has_permission('programs')) {
    $stats['kegiatan'] = ['Total kegiatan', (int)$pdo->query('SELECT COUNT(*) FROM kegiatan')->fetchColumn(), 'Draft dan publikasi'];
    $stats['program'] = ['Total program', (int)$pdo->query('SELECT COUNT(*) FROM programs')->fetchColumn(), 'Program tersimpan'];
}
if (has_permission('members')) {
    $stats['anggota'] = ['Data anggota', (int)$pdo->query('SELECT COUNT(*) FROM anggota')->fetchColumn(), 'Pendaftar dan anggota'];
}
if (has_permission('reports')) {
    $stats['laporan'] = ['Laporan masuk', (int)$pdo->query('SELECT COUNT(*) FROM laporan_aktivitas')->fetchColumn(), 'Laporan aktivitas'];
}
$quickLinks = [];
if (has_permission('content')) $quickLinks[] = ['Ubah teks, logo & foto website', 'konten.php'];
if (has_permission('structure')) $quickLinks[] = ['Kelola struktur pengurus', 'struktur.php'];
if (has_permission('members')) $quickLinks[] = ['Kelola data anggota', 'anggota.php'];
if (has_permission('programs')) {
    $quickLinks[] = ['Kelola kegiatan', 'kegiatan.php'];
    $quickLinks[] = ['Kelola program', 'program.php'];
}
if (has_permission('reports')) $quickLinks[] = ['Lihat laporan aktivitas', 'laporan.php'];
if (($_SESSION['admin_role'] ?? '') === 'developer') $quickLinks[] = ['Kelola empat akun Admin', 'kelola-akun.php'];
$recent = has_permission('reports')
    ? $pdo->query('SELECT id, judul_aktivitas, status, waktu FROM laporan_aktivitas ORDER BY id DESC LIMIT 5')->fetchAll()
    : [];
admin_header('Dashboard', 'dashboard');
?>
<section class="welcome"><h2>Selamat datang, <?= e($adminName) ?>!</h2><p><?= e(admin_role_label()) ?>. Menu dan data yang tersedia disesuaikan dengan tugas akun Anda.</p></section>
<?php if ($stats): ?><section class="stats-grid"><?php foreach ($stats as $stat): ?><article class="stat-card"><span><?= e($stat[0]) ?></span><strong><?= (int)$stat[1] ?></strong><small><?= e($stat[2]) ?></small></article><?php endforeach; ?></section><?php endif; ?>
<section class="panel"><div class="panel-head"><div><h2>Akses tugas Anda</h2><p class="panel-subtitle">Pilih bagian yang menjadi tanggung jawab akun ini.</p></div></div><?php if ($quickLinks): ?><div class="form-grid"><?php foreach ($quickLinks as [$label, $url]): ?><a class="btn btn-light" href="<?= e($url) ?>"><?= e($label) ?> →</a><?php endforeach; ?></div><?php else: ?><div class="empty">Belum ada menu yang diberikan kepada akun ini. Hubungi Developer.</div><?php endif; ?></section>
<?php if (has_permission('reports')): ?><section class="panel"><div class="panel-head"><div><h2>Laporan terbaru</h2><p class="panel-subtitle">Lima laporan aktivitas terakhir.</p></div><a class="btn btn-light btn-sm" href="laporan.php">Lihat semua</a></div><?php if (!$recent): ?><div class="empty">Belum ada laporan aktivitas.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Judul laporan</th><th>Status</th></tr></thead><tbody><?php foreach ($recent as $r): ?><tr><td><?= e($r['waktu']) ?></td><td><?= e($r['judul_aktivitas']) ?></td><td><span class="pill <?= $r['status']==='selesai'?'green':($r['status']==='ditinjau'?'orange':'') ?>"><?= e(ucfirst($r['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section><?php endif; ?>
<?php admin_footer(); ?>
