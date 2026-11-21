CREATE DATABASE IF NOT EXISTS db_kabanusa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_kabanusa;

CREATE TABLE IF NOT EXISTS admin_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(32) NOT NULL DEFAULT 'content',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  account_slot VARCHAR(40) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admin_account_slot (account_slot)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS struktur_organisasi (
  kode_jabatan VARCHAR(50) PRIMARY KEY,
  bagian ENUM('atas','tengah','bawah') NOT NULL,
  nama_jabatan VARCHAR(150) NOT NULL,
  nama_pengurus VARCHAR(150) NOT NULL DEFAULT 'Nama pengurus belum tersedia',
  foto VARCHAR(255) DEFAULT NULL,
  urutan INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS struktur_anggota (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kabanusa_migrations (
  migration_key VARCHAR(100) PRIMARY KEY,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO struktur_organisasi (kode_jabatan,bagian,nama_jabatan,nama_pengurus,foto,urutan) VALUES
('ketua_pembina','atas','Ketua Pembina','Nama pengurus belum tersedia','assets/foto-ketua-pembina.jpeg',1),
('dewan_pengawas','atas','Dewan Pengawas','Nama pengurus belum tersedia',NULL,2),
('ketua_umum','tengah','Ketua Umum','Nama pengurus belum tersedia',NULL,1),
('sekretaris','tengah','Sekretaris','Nama pengurus belum tersedia',NULL,2),
('bendahara','tengah','Bendahara','Nama pengurus belum tersedia',NULL,3),
('humas_1','bawah','Divisi Humas & Media Kreatif 1','Nama pengurus belum tersedia',NULL,1),
('humas_2','bawah','Divisi Humas & Media Kreatif 2','Nama pengurus belum tersedia',NULL,2),
('dsci','bawah','Divisi DSCI','Nama pengurus belum tersedia',NULL,3),
('logistik_1','bawah','Divisi Logistik & Perlengkapan 1','Nama pengurus belum tersedia',NULL,4),
('logistik_2','bawah','Divisi Logistik & Perlengkapan 2','Nama pengurus belum tersedia',NULL,5),
('sosial','bawah','Koordinator Sosial','Nama pengurus belum tersedia',NULL,6),
('kemanusiaan','bawah','Koordinator Kemanusiaan','Nama pengurus belum tersedia',NULL,7),
('pendidikan','bawah','Koordinator Pendidikan','Nama pengurus belum tersedia',NULL,8),
('keagamaan','bawah','Koordinator Keagamaan','Nama pengurus belum tersedia',NULL,9);

CREATE TABLE IF NOT EXISTS kegiatan (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  judul VARCHAR(180) NOT NULL,
  kategori VARCHAR(100) NOT NULL,
  tanggal DATE DEFAULT NULL,
  deskripsi TEXT NOT NULL,
  gambar VARCHAR(255) DEFAULT NULL,
  status ENUM('draft','publikasi') NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Galeri multi-foto untuk setiap kegiatan (minimal 3 foto sebelum publikasi).
CREATE TABLE IF NOT EXISTS kegiatan_foto (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kegiatan_id BIGINT UNSIGNED NOT NULL,
  gambar VARCHAR(255) NOT NULL,
  urutan INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_kegiatan_foto_kegiatan FOREIGN KEY (kegiatan_id)
    REFERENCES kegiatan(id) ON DELETE CASCADE,
  UNIQUE KEY uq_kegiatan_gambar (kegiatan_id, gambar),
  INDEX idx_kegiatan_foto_urutan (kegiatan_id, urutan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS anggota (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama_lengkap VARCHAR(150) NOT NULL,
  email VARCHAR(180) DEFAULT NULL,
  whatsapp VARCHAR(40) DEFAULT NULL,
  domisili VARCHAR(150) DEFAULT NULL,
  minat_peran VARCHAR(120) DEFAULT NULL,
  motivasi TEXT DEFAULT NULL,
  status ENUM('baru','diproses','diterima','ditolak') NOT NULL DEFAULT 'baru',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS laporan_aktivitas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  judul_aktivitas VARCHAR(180) NOT NULL,
  deskripsi TEXT NOT NULL,
  regu VARCHAR(100) DEFAULT NULL,
  status ENUM('baru','ditinjau','selesai') NOT NULL DEFAULT 'baru',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- CMS: konten website yang dapat diubah melalui panel admin
CREATE TABLE IF NOT EXISTS site_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NOT NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES
('site_name','Karya Bakti Anak Nusantara (KABANUSA)'),
('brand_text','KABA NUSA'),
('seo_description','Karya Bakti Anak Nusantara - bersama membangun masa depan Indonesia.'),
('logo_path','assets/Logo_Kabanusa.png'),
('hero_image','assets/1779535267_IMG_20250912_232412_563.jpg'),
('home_eyebrow','KARYA BAKTI ANAK NUSANTARA'),
('home_title_prefix','Wujudkan'),
('home_title_highlight','Masa Depan Cerah'),
('home_title_suffix','Mulai Hari Ini.'),
('home_description','Bersama KABANUSA, kita kolaborasikan visi, energi, dan aksi nyata untuk menciptakan dampak positif berkelanjutan bagi masyarakat.'),
('stat1_value','12+'),('stat1_label','Program Berjalan'),
('stat2_value','34'),('stat2_label','Provinsi Terjangkau'),
('stat3_value','+'),('stat3_label','Relawan Terdaftar'),
('home_section_eyebrow','BERGERAK BERSAMA'),
('home_section_title','Kolaborasi untuk dampak yang lebih luas'),
('home_section_text','Temukan ruang untuk berkontribusi, belajar, dan tumbuh bersama Karya Bakti Anak Nusantara.'),
('home_card1_title','Agenda Kegiatan'),('home_card1_text','Ikuti kegiatan, aksi sosial, workshop, dan agenda rutin KABANUSA.'),
('home_card2_title','Pilar Organisasi'),('home_card2_text','Kenali struktur dan sosok-sosok yang bekerja bersama mewujudkan visi organisasi.'),
('home_card3_title','Jalin Kemitraan'),('home_card3_text','Kami terbuka untuk kolaborasi, gagasan, dan kemitraan strategis.'),
('profile_eyebrow','KARYA BAKTI ANAK NUSANTARA'),
('profile_page_title','Mengenal KABANUSA'),
('profile_page_intro','Karya Bakti Anak Nusantara — tumbuh melalui kolaborasi, kepedulian, dan aksi nyata.'),
('profile_since','SINCE 2012'),('profile_heading','Bergerak bersama untuk Indonesia'),
('profile_paragraph_1','KABANUSA atau Karya Bakti Anak Nusantara merupakan ruang kolaborasi untuk mendorong kontribusi positif bagi masyarakat. Kami percaya perubahan yang berkelanjutan lahir dari kepedulian, kerja sama, dan tindakan nyata.'),
('profile_paragraph_2','Melalui berbagai program dan kegiatan, KABANUSA mengajak masyarakat untuk saling mendukung serta mengambil bagian dalam membangun masa depan yang lebih baik.'),
('profile_quote','Bersama membangun masa depan Indonesia yang lebih baik.'),
('profile_vision','Mewujudkan masyarakat yang berdaya, peduli, dan berkolaborasi untuk masa depan Indonesia yang lebih baik.'),
('profile_mission','Mendorong partisipasi dan kepedulian sosial.\nMengembangkan kolaborasi dan kemitraan.\nMendukung pemberdayaan serta peningkatan kapasitas masyarakat.\nMembangun kegiatan yang berdampak dan berkelanjutan.'),
('structure_page_title','Struktur Organisasi'),('structure_page_intro','Kenali susunan pengurus KABANUSA dan para penggerak organisasi.'),
('activities_eyebrow','KABANUSA BERGERAK'),('activities_title','Kegiatan & Aksi Nyata'),('activities_intro','Dokumentasi kegiatan, aksi sosial, dan agenda kolaborasi KABANUSA.'),
('program_page_eyebrow','KARYA BAKTI ANAK NUSANTARA'),('program_page_title','Inovasi yang Menggerakkan'),('program_page_intro','Telusuri bagaimana KABANUSA mendorong pemberdayaan dan kolaborasi untuk dampak sosial berkelanjutan.'),
('join_page_title','Jadilah Bagian dari Perubahan'),('join_page_intro','Bergabunglah bersama relawan KABANUSA dan ambil bagian dalam aksi positif.'),
('report_page_title','Formulir Laporan'),('report_page_intro','Sampaikan laporan kepada admin KABANUSA.'),
('contact_page_eyebrow','KARYA BAKTI ANAK NUSANTARA'),('contact_page_title','Mari Beraksi Bersama.'),('contact_page_intro','Pintu kolaborasi selalu terbuka. Hubungi tim kami untuk konsultasi kemitraan atau pendaftaran relawan.'),
('contact_email','kabanusa8@gmail.com'),('contact_whatsapp','6281217128383'),('contact_phone_display','+62 812-1712-8383'),
('contact_instagram','https://www.instagram.com/kabanusa.id/'),('contact_instagram_label','@kabanusa.id'),('contact_address',''),
('footer_description','Karya Bakti Anak Nusantara. Bersama membangun masa depan Indonesia yang lebih baik melalui kolaborasi dan aksi nyata.');

CREATE TABLE IF NOT EXISTS programs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  judul VARCHAR(180) NOT NULL,
  deskripsi TEXT NOT NULL,
  gambar VARCHAR(255) DEFAULT NULL,
  status ENUM('draft','publikasi') NOT NULL DEFAULT 'draft',
  urutan INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO programs (id,judul,deskripsi,gambar,status,urutan) VALUES
(1,'Pemberdayaan Masyarakat','Mendukung partisipasi, kapasitas, dan kemandirian masyarakat.',NULL,'publikasi',1),
(2,'Kolaborasi Digital','Menghubungkan ide, relawan, dan jejaring untuk memperluas dampak.',NULL,'publikasi',2),
(3,'Aksi Sosial','Mengembangkan kepedulian melalui kegiatan dan kontribusi nyata.',NULL,'publikasi',3);
