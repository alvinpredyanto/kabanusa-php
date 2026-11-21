<?php
function admin_header(string $title, string $active = ''): void {
    $flash = get_flash();
    $roleLabel = admin_role_label();
    ?>
    <!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?> | Admin KABANUSA</title><link rel="stylesheet" href="assets/admin.css"></head><body>
    <div class="admin-shell">
      <aside class="sidebar" id="sidebar">
        <a class="admin-brand" href="index.php"><span class="brand-mark">K</span><span><b>KABANUSA</b><small>PANEL ADMINISTRASI</small></span></a>
        <nav class="side-nav">
          <span class="nav-label">MENU UTAMA</span>
          <a class="<?= $active==='dashboard'?'active':'' ?>" href="index.php">▦ <span>Dashboard</span></a>
          <?php if (has_permission('content')): ?><a class="<?= $active==='konten'?'active':'' ?>" href="konten.php">✎ <span>Konten Website</span></a><?php endif; ?>
          <?php if (has_permission('structure')): ?><a class="<?= $active==='struktur'?'active':'' ?>" href="struktur.php">♙ <span>Struktur Pengurus</span></a><?php endif; ?>
          <?php if (has_permission('programs')): ?>
            <a class="<?= $active==='kegiatan'?'active':'' ?>" href="kegiatan.php">▣ <span>Kegiatan</span></a>
            <a class="<?= $active==='program'?'active':'' ?>" href="program.php">✧ <span>Program</span></a>
          <?php endif; ?>
          <?php if (has_permission('members')): ?><a class="<?= $active==='anggota'?'active':'' ?>" href="anggota.php">♧ <span>Data Anggota</span></a><?php endif; ?>
          <?php if (has_permission('reports')): ?><a class="<?= $active==='laporan'?'active':'' ?>" href="laporan.php">☷ <span>Laporan</span></a><?php endif; ?>
          <?php if (($_SESSION['admin_role'] ?? '') === 'developer'): ?><a class="<?= $active==='kelola-akun'?'active':'' ?>" href="kelola-akun.php">♙ <span>Kelola Akun & Hak Akses</span></a><?php endif; ?>
          <a class="<?= $active==='akun'?'active':'' ?>" href="akun.php">⚙ <span>Akun Saya</span></a>
          <span class="nav-label nav-label--bottom">WEBSITE</span>
          <a href="../index.php" target="_blank" rel="noopener">↗ <span>Lihat Website</span></a>
        </nav>
        <div class="sidebar-foot"><div class="admin-avatar"><?= e(strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1))) ?></div><div><b><?= e($_SESSION['admin_name'] ?? 'Administrator') ?></b><small><?= e($roleLabel) ?></small></div></div>
      </aside>
      <main class="main-area">
        <header class="topbar"><button class="menu-btn" id="menuBtn" aria-label="Buka menu">☰</button><div><span class="topbar-kicker"><?= e($roleLabel) ?></span><h1><?= e($title) ?></h1></div><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="logout-btn" type="submit">Keluar ↗</button></form></header>
        <div class="page-content">
          <?php if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
    <?php
}
function admin_footer(): void { ?>
        </div><footer class="admin-footer">KABANUSA · Sistem Administrasi Internal</footer>
      </main>
    </div><script>const b=document.getElementById('menuBtn'),s=document.getElementById('sidebar');if(b&&s)b.addEventListener('click',()=>s.classList.toggle('show'));document.addEventListener('keydown',e=>{if(e.key==='Escape'&&s)s.classList.remove('show')});</script></body></html>
<?php }
