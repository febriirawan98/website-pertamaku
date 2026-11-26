<?php
require_once __DIR__ . '/includes/init.php';

if (current_user()) {
    redirect('dashboard/index.php');
}

// Jika belum ada akun sama sekali, arahkan ke instalasi.
try {
    $adaUser = (int)db_val('SELECT COUNT(*) FROM users') > 0;
} catch (PDOException $e) {
    redirect('install.php');
}
if (!$adaUser) {
    redirect('install.php');
}

$error = '';
if (is_post()) {
    csrf_verify();
    $username = post('username');
    $password = (string)($_POST['password'] ?? '');

    // Pembatasan percobaan login sederhana (per sesi): 5 gagal -> tunggu 30 detik
    $lockUntil = (int)($_SESSION['login_lock'] ?? 0);
    if ($lockUntil > time()) {
        $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . ($lockUntil - time()) . ' detik.';
    } else {
        $u = db_row('SELECT * FROM users WHERE username = ?', [$username]);
        // password_verify selalu dijalankan agar waktu respons seragam
        $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringforsaltuQ8tY9p2kP3o9Tq1m0y7a8xv3n5B6C7D8E9F0G';
        $ok = password_verify($password, $hash) && $u;
        if ($ok && !(int)$u['is_active']) {
            $error = 'Akun Anda dinonaktifkan. Hubungi administrator.';
        } elseif ($ok) {
            session_regenerate_id(true);
            $_SESSION = [];
            $_SESSION['user'] = ['id' => (int)$u['id'], 'nama' => $u['nama_lengkap'], 'username' => $u['username'], 'role' => $u['role']];
            db_exec('UPDATE users SET last_login = NOW() WHERE id = ?', [$u['id']]);
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
            }
            redirect('dashboard/index.php');
        } else {
            $_SESSION['login_fail'] = (int)($_SESSION['login_fail'] ?? 0) + 1;
            if ($_SESSION['login_fail'] >= 5) {
                $_SESSION['login_lock'] = time() + 30;
                $_SESSION['login_fail'] = 0;
            }
            $error = 'Username atau password salah.';
        }
    }
}
$flash = flash_render();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="text-center mb-3">
            <div class="login-logo"><i class="bi bi-pc-display-horizontal"></i></div>
            <h1 class="h4 fw-bold mb-0" style="color:var(--navy)"><?= e(APP_NAME) ?></h1>
            <div class="small text-muted"><?= e(APP_FULLNAME) ?></div>
            <div class="small text-muted"><?= e(SCHOOL_NAME) ?></div>
        </div>
        <?= $flash ?>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><i class="bi bi-exclamation-octagon me-1"></i><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input class="form-control" id="username" name="username" required autofocus value="<?= e(post('username')) ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input class="form-control" id="password" type="password" name="password" required>
                </div>
            </div>
            <button class="btn btn-primary w-100 py-2" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i>Masuk</button>
        </form>
    </div>
</div>
</body>
</html>
