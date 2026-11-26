<?php
/**
 * SILAB-RPL - Konfigurasi aplikasi dan koneksi database.
 * Ini adalah SATU-SATUNYA tempat konfigurasi koneksi database.
 * Ubah nilai DB_* di bawah bila pengaturan MySQL Anda berbeda
 * (bawaan XAMPP: user "root", tanpa password).
 */

// ---- Koneksi database ------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'inventaris_lab_rpl');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- Identitas aplikasi ----------------------------------------------
define('APP_NAME', 'SILAB-RPL');
define('APP_FULLNAME', 'Sistem Informasi Inventaris Laboratorium Rekayasa Perangkat Lunak');
define('SCHOOL_NAME', 'SMK Negeri 1 Ampelgading');
define('LAB_NAME', 'Laboratorium Rekayasa Perangkat Lunak (RPL)');
define('PER_PAGE', 10);
define('ROOT_PATH', dirname(__DIR__));

date_default_timezone_set('Asia/Jakarta');

/**
 * Alamat dasar aplikasi, terdeteksi otomatis (mis. "/silab-rpl").
 * Jika deteksi keliru, ganti manual: define('BASE_URL', '/silab-rpl');
 */
function detect_base_url(): string
{
    $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $app  = realpath(ROOT_PATH);
    if ($root && $app) {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $app  = str_replace('\\', '/', $app);
        if (stripos($app, $root) === 0) {
            return rtrim(substr($app, strlen($root)), '/');
        }
    }
    return '/silab-rpl';
}
define('BASE_URL', detect_base_url());

/** Koneksi PDO tunggal (singleton). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        error_log('SILAB-RPL koneksi database gagal: ' . $e->getMessage());
        http_response_code(500);
        echo '<!DOCTYPE html><meta charset="utf-8"><title>Database tidak tersambung</title>'
           . '<body style="font-family:sans-serif;max-width:600px;margin:60px auto">'
           . '<h2>Database tidak dapat diakses</h2>'
           . '<p>Pastikan <b>MySQL</b> pada XAMPP sudah berjalan dan database sudah dibuat. '
           . 'Jalankan <a href="' . htmlspecialchars(BASE_URL) . '/install.php">install.php</a> '
           . 'atau impor <code>database/schema.sql</code> lewat phpMyAdmin, lalu periksa '
           . '<code>config/config.php</code>.</p></body>';
        exit;
    }
    return $pdo;
}
