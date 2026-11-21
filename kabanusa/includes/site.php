<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

/**
 * Create the normalized structure table and migrate the former fixed 14 positions once.
 * This is deliberately idempotent so existing installations do not need to re-import SQL.
 */
function ensure_structure_members_table(): void {
    static $ready = false;
    if ($ready) return;

    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS struktur_anggota (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        kelompok ENUM('pembina','inti','divisi') NOT NULL,
        nama_divisi VARCHAR(120) DEFAULT NULL,
        nama_jabatan VARCHAR(150) NOT NULL,
        nama_pengurus VARCHAR(150) NOT NULL,
        foto VARCHAR(255) DEFAULT NULL,
        urutan INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_struktur_kelompok (kelompok),
        INDEX idx_struktur_divisi (nama_divisi),
        INDEX idx_struktur_urutan (urutan)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS kabanusa_migrations (
        migration_key VARCHAR(100) PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $migrationKey = '20261119_dynamic_structure_members_v1';
    $check = $pdo->prepare('SELECT migration_key FROM kabanusa_migrations WHERE migration_key = ?');
    $check->execute([$migrationKey]);
    if (!$check->fetchColumn()) {
        $pdo->beginTransaction();
        try {
            $tableCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'struktur_organisasi'");
            $tableCheck->execute();
            if ((int)$tableCheck->fetchColumn() > 0) {
                $legacyRows = $pdo->query("SELECT * FROM struktur_organisasi ORDER BY FIELD(bagian,'atas','tengah','bawah'), urutan ASC")->fetchAll();
                $insert = $pdo->prepare('INSERT INTO struktur_anggota (kelompok, nama_divisi, nama_jabatan, nama_pengurus, foto, urutan) VALUES (?, ?, ?, ?, ?, ?)');
                foreach ($legacyRows as $row) {
                    $oldGroup = (string)$row['bagian'];
                    $group = $oldGroup === 'atas' ? 'pembina' : ($oldGroup === 'tengah' ? 'inti' : 'divisi');
                    $division = null;
                    if ($group === 'divisi') {
                        $code = (string)$row['kode_jabatan'];
                        if (str_starts_with($code, 'humas_')) $division = 'Humas & Media Kreatif';
                        elseif ($code === 'dsci') $division = 'DSCI';
                        elseif (str_starts_with($code, 'logistik_')) $division = 'Logistik & Perlengkapan';
                        elseif ($code === 'sosial') $division = 'Sosial';
                        elseif ($code === 'kemanusiaan') $division = 'Kemanusiaan';
                        elseif ($code === 'pendidikan') $division = 'Pendidikan';
                        elseif ($code === 'keagamaan') $division = 'Keagamaan';
                        else $division = 'Divisi Umum';
                    }
                    $insert->execute([
                        $group,
                        $division,
                        (string)$row['nama_jabatan'],
                        (string)$row['nama_pengurus'],
                        $row['foto'] !== null ? (string)$row['foto'] : null,
                        (int)$row['urutan'],
                    ]);
                }
            }
            $mark = $pdo->prepare('INSERT INTO kabanusa_migrations (migration_key) VALUES (?)');
            $mark->execute([$migrationKey]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
    $ready = true;
}


function site_setting(string $key, string $fallback = ''): string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll() as $row) {
                $cache[$row['setting_key']] = (string)$row['setting_value'];
            }
        } catch (Throwable $e) { $cache = []; }
    }
    return array_key_exists($key, $cache) && $cache[$key] !== '' ? $cache[$key] : $fallback;
}
function site_whatsapp_link(string $message = ''): string {
    $number = preg_replace('/\D+/', '', site_setting('contact_whatsapp', '6281217128383'));
    return 'https://wa.me/' . $number . ($message !== '' ? '?text=' . rawurlencode($message) : '');
}
function public_header(string $active = ''): void {
    $logo = site_setting('logo_path', 'assets/Logo_Kabanusa.png');
    $nav = [
        'index' => ['Beranda','index.php'], 'profil' => ['Profil','profil.php'],
        'struktur' => ['Struktur','struktur.php'], 'kegiatan' => ['Kegiatan','kegiatan.php'],
        'program' => ['Program','program.php'], 'kontak' => ['Kontak','kontak.php']
    ]; ?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="<?= e(site_setting('seo_description','Karya Bakti Anak Nusantara - bersama membangun masa depan Indonesia.')) ?>"><title><?= e(site_setting('site_name','KABANUSA')) ?></title><link rel="stylesheet" href="style.css"></head><body data-whatsapp-number="<?= e(preg_replace('/\D+/', '', site_setting('contact_whatsapp', '6281217128383'))) ?>">
<header class="site-header"><div class="container nav-wrap"><a class="brand" href="index.php"><img src="<?= e($logo) ?>" alt="Logo KABANUSA"><span><?= e(site_setting('brand_text','KABA NUSA')) ?></span></a><button class="menu-toggle" aria-label="Buka menu" aria-expanded="false">☰</button><nav class="nav-links" aria-label="Navigasi utama">
<?php foreach ($nav as $key => [$label,$href]): ?><a href="<?= e($href) ?>" class="<?= $active===$key?'active':'' ?>"><?= e($label) ?></a><?php endforeach; ?>
</nav><div class="nav-actions"><a class="btn btn-outline" href="portal-anggota.php">Portal Anggota</a><a class="btn btn-primary" href="daftar.php">Gabung Sekarang</a></div></div></header>
<?php
}
function public_footer(): void {
    $logo = site_setting('logo_path', 'assets/Logo_Kabanusa.png');
    $email = site_setting('contact_email','kabanusa8@gmail.com');
    $wa = preg_replace('/\D+/', '', site_setting('contact_whatsapp','6281217128383'));
    $waDisplay = site_setting('contact_phone_display','+62 812-1712-8383');
    $instagram = site_setting('contact_instagram','https://www.instagram.com/kabanusa.id/');
    $instagramLabel = site_setting('contact_instagram_label','@kabanusa.id'); ?>
<footer class="site-footer"><div class="container"><div class="footer-grid"><div><a class="brand" href="index.php"><img src="<?= e($logo) ?>" alt=""><span style="color:white"><?= e(site_setting('brand_text','KABA NUSA')) ?></span></a><p><?= e(site_setting('footer_description','Karya Bakti Anak Nusantara. Bersama membangun masa depan Indonesia yang lebih baik melalui kolaborasi dan aksi nyata.')) ?></p></div><div><h3>Navigasi</h3><div class="footer-links"><a href="index.php">Beranda</a><a href="profil.php">Profil</a><a href="struktur.php">Struktur</a><a href="kegiatan.php">Kegiatan</a><a href="program.php">Program</a><a href="kontak.php">Kontak</a></div></div><div><h3>Hubungi Kami</h3><div class="footer-links"><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a><a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener"><?= e($waDisplay) ?></a><a href="<?= e($instagram) ?>" target="_blank" rel="noopener">Instagram <?= e($instagramLabel) ?></a></div></div></div><div class="copyright">© <?= date('Y') ?> <?= e(site_setting('site_name','Karya Bakti Anak Nusantara (KABANUSA)')) ?>. Semua Hak Dilindungi.</div></div></footer><a class="wa-float" href="<?= e(site_whatsapp_link('Halo Admin KABANUSA, saya ingin bertanya.')) ?>" target="_blank" rel="noopener" aria-label="Chat WhatsApp">☏</a><script src="script.js"></script></body></html><?php
}
function public_page_hero(string $eyebrow, string $title, string $description): void { ?>
<section class="page-hero"><div class="container"><span class="eyebrow"><?= e($eyebrow) ?></span><h1><?= e($title) ?></h1><p><?= e($description) ?></p></div></section>
<?php }
