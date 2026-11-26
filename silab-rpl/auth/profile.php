<?php
require_once __DIR__ . '/../includes/init.php';
require_login();

if (is_post()) {
    csrf_verify();
    $row = db_row('SELECT password_hash FROM users WHERE id = ?', [user_id()]);
    $old = (string)($_POST['password_lama'] ?? '');
    $new = (string)($_POST['password_baru'] ?? '');
    $new2 = (string)($_POST['password_baru2'] ?? '');
    if (!$row || !password_verify($old, $row['password_hash'])) {
        flash('error', 'Password lama salah.');
    } elseif (strlen($new) < 8) {
        flash('error', 'Password baru minimal 8 karakter.');
    } elseif ($new !== $new2) {
        flash('error', 'Konfirmasi password baru tidak sama.');
    } else {
        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), user_id()]);
        session_regenerate_id(true);
        flash('success', 'Password berhasil diganti.');
    }
    redirect('auth/profile.php');
}

$pageTitle = 'Profil';
$active = '';
require __DIR__ . '/../includes/header.php';
$u = current_user();
?>
<div class="page-head"><h1>Profil &amp; Ganti Password</h1></div>
<div class="row g-3">
    <div class="col-md-5"><div class="card"><div class="card-header">Akun</div><div class="card-body">
        <dl class="row dl-grid mb-0">
            <dt class="col-5">Nama</dt><dd class="col-7"><?= e($u['nama']) ?></dd>
            <dt class="col-5">Username</dt><dd class="col-7"><?= e($u['username']) ?></dd>
            <dt class="col-5">Peran</dt><dd class="col-7"><?= $u['role'] === 'admin' ? 'Administrator' : 'Laboran' ?></dd>
        </dl>
    </div></div></div>
    <div class="col-md-7"><div class="card"><div class="card-header">Ganti Password</div><div class="card-body">
        <form method="post" autocomplete="off">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label req">Password lama</label><input class="form-control" type="password" name="password_lama" required autocomplete="current-password"></div>
            <div class="mb-3"><label class="form-label req">Password baru (min. 8 karakter)</label><input class="form-control" type="password" name="password_baru" required minlength="8" autocomplete="new-password"></div>
            <div class="mb-3"><label class="form-label req">Ulangi password baru</label><input class="form-control" type="password" name="password_baru2" required minlength="8" autocomplete="new-password"></div>
            <button class="btn btn-primary"><i class="bi bi-key me-1"></i>Simpan Password</button>
        </form>
    </div></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
