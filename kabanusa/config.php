<?php
declare(strict_types=1);

// Konfigurasi database untuk XAMPP. Ubah DB_PASS sesuai konfigurasi hosting Anda.
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'db_kabanusa');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : '');
