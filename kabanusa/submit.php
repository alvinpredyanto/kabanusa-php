<?php
require_once __DIR__ . '/includes/site.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Metode tidak diizinkan.'); }
verify_csrf();
if (!empty($_POST['website'] ?? '')) { header('Location: index.php'); exit; }
$type = (string)($_POST['form_type'] ?? '');
try {
    if ($type === 'registration') {
        $nama=trim((string)($_POST['nama']??'')); $email=trim((string)($_POST['email']??''));
        $wa=trim((string)($_POST['whatsapp']??'')); $domisili=trim((string)($_POST['domisili']??''));
        $peran=trim((string)($_POST['peran']??'')); $motivasi=trim((string)($_POST['motivasi']??''));
        if ($nama==='' || !filter_var($email,FILTER_VALIDATE_EMAIL) || $wa==='' || $peran==='') throw new RuntimeException('Lengkapi nama, email, WhatsApp, dan peran dengan benar.');
        db()->prepare('INSERT INTO anggota(nama_lengkap,email,whatsapp,domisili,minat_peran,motivasi) VALUES(?,?,?,?,?,?)')->execute([$nama,$email,$wa,$domisili,$peran,$motivasi]);
        flash('success','Pendaftaran berhasil dikirim. Tim KABANUSA akan meninjau data Anda.');
        header('Location: daftar.php'); exit;
    }
    if ($type === 'report') {
        $kategori=trim((string)($_POST['kategori']??'')); $nama=trim((string)($_POST['nama_pelapor']??''));
        $lokasi=trim((string)($_POST['lokasi']??'')); $detail=trim((string)($_POST['detail']??''));
        if ($kategori==='' || $nama==='' || $lokasi==='' || $detail==='') throw new RuntimeException('Semua kolom laporan wajib diisi.');
        $desc="Pelapor: {$nama}\nLokasi: {$lokasi}\n\n{$detail}";
        db()->prepare('INSERT INTO laporan_aktivitas(judul_aktivitas,deskripsi,regu) VALUES(?,?,?)')->execute([$kategori,$desc,$lokasi]);
        flash('success','Laporan berhasil disimpan dan telah diteruskan ke admin KABANUSA.');
        header('Location: laporan.php'); exit;
    }
    throw new RuntimeException('Jenis formulir tidak dikenal.');
} catch (Throwable $e) {
    flash('error',$e instanceof RuntimeException ? $e->getMessage() : 'Data belum dapat disimpan. Silakan coba kembali atau hubungi admin.');
    header('Location: ' . ($type==='report'?'laporan.php':'daftar.php')); exit;
}
