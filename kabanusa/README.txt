KABANUSA — WEBSITE RESPONSIF + CMS / PANEL ADMIN
=================================================

WEBSITE PUBLIK
- Halaman beranda, profil, struktur, kegiatan, program, kontak, pendaftaran, laporan, chatbot, dan portal anggota.
- Tampilan responsif untuk desktop, tablet, dan ponsel.
- Halaman publik menggunakan PHP untuk mengambil konten terbaru dari database.
- URL lama berakhiran .html diarahkan ke halaman PHP melalui .htaccess.

PANEL ADMIN + PEMBAGIAN HAK AKSES (RBAC)
- Login berbasis peran. Hak akses diperiksa di sisi server pada setiap halaman, bukan hanya dengan menyembunyikan menu.
- Tepat satu akun Developer dengan akses penuh.
- Empat slot Admin dengan tugas tetap: Admin Konten & Media; Admin Struktur & Keanggotaan; Admin Program & Kegiatan; Admin Laporan & Pengawasan.
- Hanya Developer yang dapat membuat, mengubah, menonaktifkan, mengaktifkan, mengganti password, dan menghapus akun Admin.
- Satu slot tugas hanya dapat dimiliki satu akun. Admin tidak dapat membuka halaman bagian lain dengan mengetik URL langsung.
- Password disimpan dengan password_hash/password_verify, session PHP, CSRF token, dan pembatasan percobaan login.
- Dashboard dan menu disaring berdasarkan tugas akun.
- Konten Website: ubah judul, teks beranda, statistik, teks profil/visi/misi, teks struktur/kegiatan/program/kontak, informasi kontak, footer, dan SEO.
- Foto & Identitas: ganti logo website dan foto banner utama beranda.
- Struktur Pengurus: kelompok Dewan Pembina & Pengawas, Pengurus Inti, dan setiap Divisi ditampilkan terpisah. Admin dapat menambah, mengedit, memindahkan, mengganti foto, dan menghapus anggota kapan saja. Developer dan Admin Struktur juga dapat memilih satu dari empat model tampilan publik: Kartu Divisi, Bagan Hierarki, Daftar Ringkas, atau Galeri Foto.
- Kegiatan: tambah, edit, hapus, atur draft/publikasi, dan kelola galeri multi-foto. Setiap kegiatan wajib memiliki minimal 3 foto sebelum dipublikasikan; dapat ditambah hingga 12 foto dalam satu kali unggah (maksimal 5 MB per foto).
- Program: tambah, edit, hapus, ganti foto, urutan, dan status publikasi.
- Data Anggota: pendaftaran publik otomatis masuk ke database dan status dapat diperbarui admin.
- Laporan: formulir laporan publik otomatis masuk ke panel admin dan status dapat dikelola.

INSTALASI LOKAL MENGGUNAKAN XAMPP
1. Ekstrak folder KABANUSA-HTML-CSS-JS ke C:/xampp/htdocs/.
2. Jalankan Apache dan MySQL dari XAMPP Control Panel.
3. Buka http://localhost/phpmyadmin.
4. Import file database/kabanusa.sql. Jika database db_kabanusa dari versi sebelumnya sudah ada, import ulang file SQL yang baru ini. Script menggunakan CREATE TABLE IF NOT EXISTS dan INSERT IGNORE untuk menambah tabel/isi awal CMS tanpa menghapus tabel/data lama. JANGAN menghapus database jika ingin mempertahankan data sebelumnya.
5. Buka config.php dan sesuaikan DB_HOST, DB_NAME, DB_USER, DB_PASS jika diperlukan. Saat halaman Struktur atau menu Admin > Struktur Pengurus pertama kali dibuka, sistem membuat tabel struktur dinamis dan memindahkan 14 data struktur lama satu kali secara otomatis.
6. Buka http://localhost/KABANUSA-HTML-CSS-JS/admin/setup.php untuk membuat SATU akun Developer pertama.
   - Username minimal 4 karakter.
   - Password minimal 12 karakter.
   - Simpan kredensial Developer di tempat aman. Jangan bagikan akun Developer kepada Admin.
7. Login melalui http://localhost/KABANUSA-HTML-CSS-JS/admin/login.php.
8. Developer buka menu Kelola Akun & Hak Akses, lalu buat empat Admin dengan username dan password masing-masing.
9. Isi empat slot tugas: Admin Konten & Media; Admin Struktur & Keanggotaan; Admin Program & Kegiatan; Admin Laporan & Pengawasan. Satu slot hanya boleh memiliki satu akun.
10. Berikan setiap Admin hanya kredensial miliknya. Developer tetap menjadi satu-satunya akun dengan akses penuh.

ALUR MENGGANTI FOTO / TEKS
- Logo dan foto banner beranda: Admin > Konten Website.
- Struktur pengurus dan anggota baru: Admin > Struktur Pengurus. Pilih model tampilan pada bagian atas halaman, lalu tekan Simpan Tampilan Struktur. Pilihan berlaku untuk seluruh pengunjung. Tambahkan anggota, pilih bagian/divisi, isi jabatan dan nama, lalu unggah foto. Nama divisi yang sama akan mengelompokkan anggota dalam divisi tersebut.
- Foto kegiatan: Admin > Kegiatan. Unggah minimal 3 foto untuk setiap kegiatan sebelum memilih status Publikasikan. Pada saat mengedit, foto lama tetap tersimpan kecuali dicentang untuk dihapus. Halaman publik menampilkan galeri foto kegiatan; klik foto untuk membukanya lebih besar. Foto sampul lama otomatis dimasukkan sebagai foto pertama ketika tabel galeri dibuat.
- Foto program: Admin > Program.
- Teks beranda, profil, kontak, nomor WhatsApp, email, Instagram, dan footer: Admin > Konten Website.
- Setelah tombol Simpan berhasil, perubahan langsung terlihat di website publik. Muat ulang halaman jika browser masih menampilkan cache.

FORMULIR PUBLIK
- Pendaftaran relawan tersimpan ke tabel anggota.
- Laporan tersimpan ke tabel laporan_aktivitas.
- Pesan pada halaman kontak dan permintaan informasi program akan membuka WhatsApp dengan nomor yang diatur melalui Konten Website.

PERSYARATAN / CATATAN
- PHP 8+ dengan ekstensi PDO MySQL dan Fileinfo.
- MySQL atau MariaDB.
- Jalankan di Apache/XAMPP atau hosting yang mendukung PHP + MySQL; jangan membuka file PHP dengan klik dua kali atau Live Server statis.
- Folder uploads harus dapat ditulisi oleh server web.
- Batas unggah gambar pada aplikasi 5 MB per file. Form galeri kegiatan menerima hingga 12 foto per unggahan; sesuaikan upload_max_filesize, post_max_size, dan max_file_uploads pada php.ini jika batas server lebih kecil.
- Untuk hosting publik, gunakan HTTPS, ubah kredensial database, batasi akses setup.php setelah Developer dibuat, dan lakukan backup database serta folder uploads secara rutin.
- Akun database MySQL perlu izin ALTER TABLE saat pertama kali membuka versi baru agar kolom dan indeks hak akses dapat dimigrasikan otomatis. Jika hosting membatasi ALTER, jalankan migrasi SQL melalui phpMyAdmin dengan akun database berizin.
- Setup hanya dapat dipakai sekali untuk membuat Developer. Pada instalasi lama, akun administrator tertua dimigrasikan menjadi Developer; akun lama lainnya dinonaktifkan agar Developer dapat meninjau dan membuat ulang empat akun Admin sesuai tugas.
- Bila database sudah ada, buka halaman admin sekali agar penambahan kolom RBAC otomatis dijalankan. Buat backup database terlebih dahulu sebelum memperbarui file website.

Catatan verifikasi: file PHP telah diperiksa dengan php -l. Pengujian login dan penyimpanan data secara langsung memerlukan runtime PHP yang dilengkapi PDO MySQL serta server MySQL.


Pembaruan susunan divisi:
- Kotak divisi pada halaman publik otomatis menyesuaikan jumlah divisi dan lebar layar.
- Kartu anggota di setiap divisi otomatis membuat baris baru saat anggota ditambahkan.
- Panel divisi di admin juga menggunakan grid otomatis; tidak perlu mengatur jumlah kolom/baris secara manual.
- Setelah Developer/Admin Struktur menambahkan divisi atau anggota dan menyimpan, halaman publik menyusun ulang kotaknya otomatis.

PEMBARUAN GALERI FOTO KEGIATAN
- Halaman Kegiatan menampilkan galeri gambar pada setiap kartu kegiatan, bukan hanya satu foto sampul.
- Kegiatan yang berstatus Publikasi harus memiliki minimal 3 foto. Status Draft boleh disimpan dengan foto kurang dari 3 agar admin dapat melengkapi dokumentasi sebelum dipublikasikan.
- Admin Program & Kegiatan dapat mengunggah beberapa foto sekaligus, menghapus foto tertentu saat mengedit, dan melihat jumlah foto yang sudah tersimpan.
- Tabel kegiatan_foto dibuat otomatis saat koneksi database pertama dibuka. Foto sampul dari data kegiatan lama dimasukkan sebagai foto pertama agar tidak hilang.
- Jika database lama telah terpasang, tidak perlu menghapus database atau mengimpor ulang. Sistem akan membuat tabel galeri saat halaman publik atau admin terhubung ke database.
