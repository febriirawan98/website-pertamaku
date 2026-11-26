<?php
/**
 * Bootstrap: dimuat oleh setiap halaman.
 * Halaman publik (login, install) mendefinisikan PUBLIC_PAGE sebelum memuat file ini.
 */
define('ROOT_PATH', dirname(__DIR__));

require ROOT_PATH . '/config/config.php';
require ROOT_PATH . '/config/database.php';

date_default_timezone_set(APP_TZ);
mb_internal_encoding('UTF-8');

// Deteksi BASE_URL otomatis
if (!defined('BASE_URL')) {
    $root = str_replace('\\', '/', (string) realpath(ROOT_PATH));
    $doc  = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $base = ($doc !== '' && strpos($root, $doc) === 0) ? substr($root, strlen($doc)) : '/silab-rpl';
    define('BASE_URL', rtrim($base, '/'));
}

// Header keamanan dasar
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

// Session
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']),
]);
session_name('SILABRPLSESS');
session_start();

require ROOT_PATH . '/includes/functions.php';
require ROOT_PATH . '/includes/auth.php';

// Penanganan error global: jangan bocorkan detail/kredensial ke pengguna
set_exception_handler(function (Throwable $ex) {
    error_log('[SILAB-RPL] ' . get_class($ex) . ': ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
    http_response_code(500);
    $isDb = $ex instanceof PDOException;
    $msg  = $isDb
        ? 'Terjadi kesalahan pada database. Pastikan MySQL di XAMPP aktif, database sudah diimpor, dan pengaturan pada <code>config/config.php</code> benar.'
        : 'Terjadi kesalahan pada aplikasi. Silakan coba lagi atau hubungi administrator.';
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Kesalahan</title><link rel="stylesheet" href="' . BASE_URL . '/assets/vendor/bootstrap.min.css"></head>'
       . '<body class="bg-light"><div class="container py-5" style="max-width:600px"><div class="card shadow-sm"><div class="card-body p-4">'
       . '<h1 class="h4 text-danger">Terjadi kesalahan</h1><p>' . $msg . '</p>'
       . '<a class="btn btn-primary" href="' . BASE_URL . '/">Kembali</a> '
       . '<a class="btn btn-outline-secondary" href="' . BASE_URL . '/install.php">Halaman instalasi</a>'
       . '</div></div></div></body></html>';
    exit;
});

if (!defined('PUBLIC_PAGE')) {
    require_login();
    refresh_loan_status();
}
