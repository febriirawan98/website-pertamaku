<?php
/**
 * Autentikasi dan otorisasi berbasis session.
 */

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    if (!empty($_SESSION['uid'])) {
        $row = db_one('SELECT id, full_name, username, role, is_active FROM users WHERE id = ?', [(int) $_SESSION['uid']]);
        if ($row && (int) $row['is_active'] === 1) {
            $user = $row;
        } else {
            unset($_SESSION['uid']);   // akun dinonaktifkan/dihapus: paksa keluar
        }
    }
    return $user;
}

function is_admin(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === 'admin';
}

function require_login(): void
{
    if (!current_user()) {
        redirect('login.php');
    }
}

function forbidden(): void
{
    http_response_code(403);
    $pageTitle = 'Akses Ditolak';
    $active = '';
    include ROOT_PATH . '/includes/header.php';
    echo '<div class="card"><div class="card-body text-center py-5"><i class="bi bi-shield-lock display-4 text-danger"></i>'
       . '<h1 class="h4 mt-3">Akses ditolak</h1><p class="text-muted">Anda tidak memiliki hak untuk membuka halaman atau melakukan tindakan ini.</p>'
       . '<a class="btn btn-primary" href="' . e(url('dashboard/')) . '">Kembali ke Dashboard</a></div></div>';
    include ROOT_PATH . '/includes/footer.php';
    exit;
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        forbidden();
    }
}

/** Proses login. Mengembalikan pesan kesalahan atau null bila berhasil. */
function attempt_login(string $username, string $password): ?string
{
    $until = (int) ($_SESSION['login_lock'] ?? 0);
    if ($until > time()) {
        return 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . ($until - time()) . ' detik.';
    }
    $u = db_one('SELECT id, password_hash, is_active FROM users WHERE username = ?', [$username]);
    // password_verify tetap dijalankan agar waktu respons seragam
    $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringforsaltuQ2G0O7m6lJp0o5n0wHj1c0nOe1S';
    $ok   = password_verify($password, $hash);
    if (!$u || !$ok) {
        $_SESSION['login_fail'] = (int) ($_SESSION['login_fail'] ?? 0) + 1;
        if ($_SESSION['login_fail'] >= 5) {
            $_SESSION['login_lock'] = time() + 60;
            $_SESSION['login_fail'] = 0;
        }
        return 'Username atau password salah.';
    }
    if ((int) $u['is_active'] !== 1) {
        return 'Akun Anda dinonaktifkan. Hubungi administrator.';
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $u['id'];
    unset($_SESSION['login_fail'], $_SESSION['login_lock']);
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        db_run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    return null;
}
