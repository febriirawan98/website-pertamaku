<?php
/**
 * Kerangka halaman (sidebar + header). Variabel yang dibaca:
 *   $pageTitle (string), $active (kunci menu), $extraHead (opsional)
 * Halaman harus sudah memanggil require_login() sebelum memuat file ini.
 */
$user = current_user();
$menu = [
    ['dashboard', 'Dashboard',        'dashboard/index.php',    'speedometer2'],
    ['items',     'Data Barang',      'items/index.php',        'pc-display'],
    ['categories', 'Kategori',        'categories/index.php',   'tags'],
    ['locations', 'Lokasi',           'locations/index.php',    'geo-alt'],
    ['transactions', 'Transaksi Stok', 'transactions/index.php', 'arrow-left-right'],
    ['loans',     'Peminjaman',       'loans/index.php',        'box-arrow-up-right'],
    ['maintenance', 'Pemeliharaan',   'maintenance/index.php',  'tools'],
    ['reports',   'Laporan',          'reports/index.php',      'file-earmark-bar-graph'],
];
if (is_admin()) {
    $menu[] = ['users', 'Pengguna', 'users/index.php', 'people'];
}
$pageTitle = $pageTitle ?? APP_NAME;
$active = $active ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <?= $extraHead ?? '' ?>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar no-print" id="sidebar">
        <a class="sidebar-brand" href="<?= url('dashboard/index.php') ?>">
            <i class="bi bi-pc-display-horizontal"></i>
            <span><?= e(APP_NAME) ?><small>Inventaris Lab RPL</small></span>
        </a>
        <nav class="sidebar-nav">
            <?php foreach ($menu as [$key, $label, $path, $icon]): ?>
                <a href="<?= url($path) ?>" class="<?= $active === $key ? 'active' : '' ?>">
                    <i class="bi bi-<?= $icon ?>"></i><span><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot"><?= e(SCHOOL_NAME) ?></div>
    </aside>
    <div class="sidebar-backdrop no-print" id="sidebarBackdrop"></div>

    <div class="main">
        <header class="topbar no-print">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle" type="button" aria-label="Menu">
                <i class="bi bi-list fs-5"></i>
            </button>
            <div class="topbar-title d-none d-md-block">
                <strong><?= e(APP_NAME) ?></strong>
                <span class="text-muted d-none d-xl-inline">&middot; <?= e(APP_FULLNAME) ?></span>
            </div>
            <form class="topbar-search ms-auto" action="<?= url('search.php') ?>" method="get">
                <div class="input-group input-group-sm">
                    <input type="search" name="q" class="form-control" placeholder="Cari kode, nama, no. seri, peminjam..." value="<?= e(get('q')) ?>">
                    <button class="btn btn-outline-secondary" type="submit" aria-label="Cari"><i class="bi bi-search"></i></button>
                </div>
            </form>
            <div class="dropdown">
                <button class="btn btn-sm btn-light border dropdown-toggle" data-bs-toggle="dropdown" type="button">
                    <i class="bi bi-person-circle me-1"></i><span class="d-none d-sm-inline"><?= e($user['nama']) ?></span>
                    <span class="badge text-bg-primary ms-1"><?= $user['role'] === 'admin' ? 'Admin' : 'Laboran' ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= url('auth/profile.php') ?>"><i class="bi bi-key me-2"></i>Profil &amp; Password</a></li>
                </ul>
            </div>
            <form action="<?= url('logout.php') ?>" method="post" class="m-0">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-box-arrow-right me-1"></i><span class="d-none d-sm-inline">Logout</span></button>
            </form>
        </header>

        <main class="content">
            <?= flash_render() ?>
