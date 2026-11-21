<?php
require_once __DIR__.'/_init.php';require_once __DIR__.'/../includes/admin-ui.php';$pdo=db();$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$name=trim((string)($_POST['name']??''));$username=trim((string)($_POST['username']??''));$current=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');$confirm=(string)($_POST['confirm_password']??'');
 try{
  if($name===''||!preg_match('/^[a-zA-Z0-9._-]{4,60}$/',$username))throw new RuntimeException('Nama wajib diisi dan username harus 4–60 karakter (huruf, angka, titik, garis bawah, atau tanda hubung).');
  $q=$pdo->prepare('SELECT password_hash FROM admin_users WHERE id=?');$q->execute([(int)$_SESSION['admin_id']]);$hash=$q->fetchColumn();
  if(!$hash||!password_verify($current,(string)$hash))throw new RuntimeException('Password saat ini tidak benar.');
  if($new!==''&&strlen($new)<12)throw new RuntimeException('Password baru minimal 12 karakter.');
  if($new!==$confirm)throw new RuntimeException('Konfirmasi password baru tidak sama.');
  if($new!=='')$pdo->prepare('UPDATE admin_users SET name=?,username=?,password_hash=? WHERE id=?')->execute([$name,$username,password_hash($new,PASSWORD_DEFAULT),(int)$_SESSION['admin_id']]);
  else $pdo->prepare('UPDATE admin_users SET name=?,username=? WHERE id=?')->execute([$name,$username,(int)$_SESSION['admin_id']]);
  $_SESSION['admin_name']=$name;$_SESSION['admin_username']=$username;flash('success','Profil administrator berhasil diperbarui.');header('Location: akun.php');exit;
 }catch(Throwable $e){$error=$e instanceof RuntimeException?$e->getMessage():'Perubahan akun gagal disimpan. Username mungkin sudah digunakan.';}
}
admin_header('Akun Administrator','akun'); ?>
<section class="panel"><div class="panel-head"><div><h2>Pengaturan akun login</h2><p class="panel-subtitle">Ubah nama, username, atau password administrator yang digunakan untuk masuk ke panel.</p></div></div><?php if($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="field"><label>Nama administrator</label><input name="name" required maxlength="120" value="<?= e($_SESSION['admin_name']??'Administrator') ?>"></div><div class="field"><label>Username baru</label><input name="username" required minlength="4" maxlength="60" value="<?= e($_SESSION['admin_username']??'') ?>" autocomplete="username"></div><hr class="admin-divider"><div class="field"><label>Password saat ini (wajib untuk menyimpan)</label><input type="password" name="current_password" required autocomplete="current-password"></div><div class="field"><label>Password baru (kosongkan jika tidak diganti)</label><input type="password" name="new_password" minlength="12" autocomplete="new-password"><small class="hint">Minimal 12 karakter.</small></div><div class="field"><label>Konfirmasi password baru</label><input type="password" name="confirm_password" minlength="12" autocomplete="new-password"></div><button class="btn btn-gold" type="submit">Simpan pengaturan akun</button></form></section>
<?php admin_footer(); ?>
