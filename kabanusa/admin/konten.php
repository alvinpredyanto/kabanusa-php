<?php
require_once __DIR__.'/_init.php';
require_once __DIR__.'/../includes/admin-ui.php';
$pdo=db();
$groups=[
 'Identitas Website'=>[
  'site_name'=>['Nama website','text'], 'brand_text'=>['Tulisan brand di header','text'], 'seo_description'=>['Deskripsi website (SEO)','textarea'],
  'contact_email'=>['Email kontak','text'], 'contact_whatsapp'=>['Nomor WhatsApp internasional (contoh 62812...)','text'], 'contact_phone_display'=>['Nomor WhatsApp yang ditampilkan','text'],
  'contact_instagram'=>['Link Instagram','text'], 'contact_instagram_label'=>['Nama akun Instagram','text'], 'contact_address'=>['Alamat (boleh dikosongkan)','text'], 'footer_description'=>['Deskripsi footer','textarea'],
 ],
 'Beranda'=>[
  'home_eyebrow'=>['Label kecil di hero','text'], 'home_title_prefix'=>['Judul hero bagian awal','text'], 'home_title_highlight'=>['Judul hero yang disorot','text'], 'home_title_suffix'=>['Judul hero baris kedua','text'], 'home_description'=>['Deskripsi hero','textarea'],
  'stat1_value'=>['Statistik 1 - angka','text'], 'stat1_label'=>['Statistik 1 - label','text'], 'stat2_value'=>['Statistik 2 - angka','text'], 'stat2_label'=>['Statistik 2 - label','text'], 'stat3_value'=>['Statistik 3 - angka','text'], 'stat3_label'=>['Statistik 3 - label','text'],
  'home_section_eyebrow'=>['Label bagian kolaborasi','text'], 'home_section_title'=>['Judul bagian kolaborasi','text'], 'home_section_text'=>['Deskripsi bagian kolaborasi','textarea'],
  'home_card1_title'=>['Beranda - judul kartu kegiatan','text'], 'home_card1_text'=>['Beranda - deskripsi kartu kegiatan','textarea'],
  'home_card2_title'=>['Beranda - judul kartu struktur','text'], 'home_card2_text'=>['Beranda - deskripsi kartu struktur','textarea'],
  'home_card3_title'=>['Beranda - judul kartu kemitraan','text'], 'home_card3_text'=>['Beranda - deskripsi kartu kemitraan','textarea'],
 ],
 'Halaman Profil'=>[
  'profile_eyebrow'=>['Label halaman','text'], 'profile_page_title'=>['Judul halaman','text'], 'profile_page_intro'=>['Deskripsi halaman','textarea'], 'profile_since'=>['Label tahun / sejak','text'], 'profile_heading'=>['Judul profil','text'], 'profile_paragraph_1'=>['Paragraf profil 1','textarea'], 'profile_paragraph_2'=>['Paragraf profil 2','textarea'], 'profile_quote'=>['Kutipan utama','textarea'], 'profile_vision'=>['Visi','textarea'], 'profile_mission'=>['Misi (satu poin per baris)','textarea'],
 ],
 'Halaman Struktur, Kegiatan & Program'=>[
  'structure_page_title'=>['Judul halaman struktur','text'], 'structure_page_intro'=>['Deskripsi halaman struktur','textarea'],
  'activities_eyebrow'=>['Label halaman kegiatan','text'], 'activities_title'=>['Judul halaman kegiatan','text'], 'activities_intro'=>['Deskripsi halaman kegiatan','textarea'],
  'program_page_eyebrow'=>['Label halaman program','text'], 'program_page_title'=>['Judul halaman program','text'], 'program_page_intro'=>['Deskripsi halaman program','textarea'],
 ],
 'Formulir Publik'=>[
  'join_page_title'=>['Judul pendaftaran','text'], 'join_page_intro'=>['Deskripsi pendaftaran','textarea'], 'report_page_title'=>['Judul laporan','text'], 'report_page_intro'=>['Deskripsi laporan','textarea'],
  'contact_page_eyebrow'=>['Label halaman kontak','text'], 'contact_page_title'=>['Judul halaman kontak','text'], 'contact_page_intro'=>['Deskripsi halaman kontak','textarea'],
 ]
];
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 try{
  $pdo->beginTransaction();
  foreach($groups as $fields){ foreach($fields as $key=>$definition){
   $value=trim((string)($_POST['settings'][$key]??''));
   if(mb_strlen($value)>12000) throw new RuntimeException('Teks terlalu panjang pada bagian '.$definition[0].'.');
   $q=$pdo->prepare('INSERT INTO site_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');$q->execute([$key,$value]);
  }}
  $files=[ 'logo'=>'logo_path', 'hero_image'=>'hero_image' ];
  foreach($files as $field=>$key){
   if(isset($_FILES[$field]) && ($_FILES[$field]['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
    $q=$pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key=?');$q->execute([$key]);$old=$q->fetchColumn();
    $uploaded=upload_image($_FILES[$field],$field);
    $up=$pdo->prepare('INSERT INTO site_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');$up->execute([$key,$uploaded]);
    remove_uploaded_image(is_string($old)?$old:null);
   }
  }
  $pdo->commit();flash('success','Konten website berhasil diperbarui. Perubahan langsung tampil di halaman publik.');
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('error',$e instanceof RuntimeException?$e->getMessage():'Gagal menyimpan konten. Pastikan database sudah diperbarui dan folder uploads dapat ditulisi.');}
 header('Location: konten.php');exit;
}
$values=[];foreach($pdo->query('SELECT setting_key,setting_value FROM site_settings')->fetchAll() as $r)$values[$r['setting_key']]=$r['setting_value'];
admin_header('Konten Website','konten');
?>
<section class="welcome"><h2>Semua perubahan website dari satu tempat</h2><p>Ubah judul, kalimat, informasi kontak, logo, dan foto banner. Klik Simpan agar perubahan terlihat pada website publik.</p></section>
<section class="panel"><div class="panel-head"><div><h2>Foto & identitas visual</h2><p class="panel-subtitle">Format JPG, PNG, WEBP. Maksimal 3 MB per gambar.</p></div></div><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<div class="media-edit-grid"><div class="media-edit-card"><label>Logo website</label><img class="media-preview logo-preview" src="../<?= e($values['logo_path']??'assets/Logo_Kabanusa.png') ?>" alt="Logo saat ini"><input type="file" name="logo" accept="image/jpeg,image/png,image/webp"><small class="hint">Foto lama tetap digunakan jika file dikosongkan.</small></div><div class="media-edit-card"><label>Foto latar beranda</label><img class="media-preview hero-preview" src="../<?= e($values['hero_image']??'assets/1779535267_IMG_20250912_232412_563.jpg') ?>" alt="Banner saat ini"><input type="file" name="hero_image" accept="image/jpeg,image/png,image/webp"><small class="hint">Digunakan sebagai latar utama halaman beranda.</small></div></div>
<?php foreach($groups as $group=>$fields): ?><div class="content-edit-group"><div class="section-label"><?= e($group) ?></div><div class="form-grid"><?php foreach($fields as $key=>$definition): ?><div class="field <?= $definition[1]==='textarea'?'field-full':'' ?>"><label for="<?= e($key) ?>"><?= e($definition[0]) ?></label><?php if($definition[1]==='textarea'): ?><textarea id="<?= e($key) ?>" name="settings[<?= e($key) ?>]" rows="3"><?= e($values[$key]??'') ?></textarea><?php else: ?><input id="<?= e($key) ?>" name="settings[<?= e($key) ?>]" value="<?= e($values[$key]??'') ?>" maxlength="1000"><?php endif; ?></div><?php endforeach; ?></div></div><?php endforeach; ?>
<div class="sticky-save"><button class="btn btn-gold" type="submit">Simpan Semua Perubahan</button><a class="btn btn-light" href="../index.php" target="_blank" rel="noopener">Lihat Website ↗</a></div></form></section>
<?php admin_footer(); ?>
