<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$pagePermissions = [
    'konten.php' => 'content',
    'struktur.php' => 'structure',
    'anggota.php' => 'members',
    'kegiatan.php' => 'programs',
    'program.php' => 'programs',
    'laporan.php' => 'reports',
    'kelola-akun.php' => 'developer_only',
];
if (isset($pagePermissions[$currentPage])) {
    $permission = $pagePermissions[$currentPage];
    if ($permission === 'developer_only') {
        if (($_SESSION['admin_role'] ?? '') !== 'developer') require_permission('developer_manage_accounts');
    } else {
        require_permission($permission);
    }
}

$adminName = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
