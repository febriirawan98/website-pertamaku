<?php
require_once __DIR__ . '/includes/init.php';

if (!is_post()) {
    redirect(current_user() ? 'dashboard/index.php' : 'login.php');
}
csrf_verify();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

// Mulai sesi baru hanya untuk pesan keberhasilan
session_name('silabrpl');
session_start();
flash('success', 'Anda berhasil keluar.');
redirect('login.php');
