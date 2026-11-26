<?php
require_once __DIR__ . '/../includes/init.php';
require_login();
require_role('admin');

/** Jumlah administrator aktif selain pengguna $exceptId. */
function other_active_admins(int $exceptId): int
{
    return (int)db_val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND id <> ?", [$exceptId]);
}

if (is_post()) {
    csrf_verify();
    $action = post('action');
    $me = user_id();
    if ($action === 'save') {
        $id = (int)post('id');
        $nama = post('nama_lengkap');
        $uname = post('username');
        $pw = (string)($_POST['password'] ?? '');
        $role = post('role');
        $aktif = isset($_POST['is_active']) ? 1 : 0;
        $old = $id ? db_row('SELECT * FROM users WHERE id = ?', [$id]) : null;
        $err = null;
        if ($id && !$old) $err = 'Pengguna tidak ditemukan.';
        elseif ($nama === '' || mb_strlen($nama) > 100) $err = 'Nama lengkap wajib diisi (maks. 100 karakter).';
        elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $uname)) $err = 'Username 3-50 karakter: huruf, angka, titik, garis bawah, atau strip.';
        elseif (!in_array($role, ['admin', 'laboran'], true)) $err = 'Peran tidak valid.';
        elseif (!$id && strlen($pw) < 8) $err = 'Password wajib diisi minimal 8 karakter untuk akun baru.';
        elseif ($pw !== '' && strlen($pw) < 8) $err = 'Password baru minimal 8 karakter.';
        elseif ($old && $id === $me && (!$aktif || $role !== 'admin')) $err = 'Anda tidak dapat menonaktifkan akun sendiri atau menurunkan peran sendiri.';
        elseif ($old && $old['role'] === 'admin' && $old['is_active'] && ($role !== 'admin' || !$aktif) && other_active_admins($id) === 0) $err = 'Tidak boleh menghilangkan satu-satunya administrator aktif.';
        if ($err) {
            flash('error', $err);
        } else {
            try {
                if ($id) {
                    db_exec('UPDATE users SET nama_lengkap = ?, username = ?, role = ?, is_active = ? WHERE id = ?', [$nama, $uname, $role, $aktif, $id]);
                    if ($pw !== '') {
                        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
                    }
                    flash('success', 'Pengguna diperbarui.' . ($pw !== '' ? ' Password diganti.' : ''));
                } else {
                    db_exec('INSERT INTO users (nama_lengkap, username, password_hash, role, is_active) VALUES (?,?,?,?,?)',
                        [$nama, $uname, password_hash($pw, PASSWORD_DEFAULT), $role, $aktif]);
                    flash('success', 'Pengguna baru ditambahkan.');
                }
            } catch (PDOException $e) {
                if (is_duplicate_error($e)) flash('error', 'Username "' . $uname . '" sudah dipakai.');
                else throw $e;
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)post('id');
        $u = db_row('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$u) flash('error', 'Pengguna tidak ditemukan.');
        elseif ($id === $me) flash('error', 'Anda tidak dapat menghapus akun yang sedang digunakan.');
        elseif ($u['role'] === 'admin' && $u['is_active'] && other_active_admins($id) === 0) flash('error', 'Tidak boleh menghapus satu-satunya administrator aktif.');
        else {
            db_exec('DELETE FROM users WHERE id = ?', [$id]);   // riwayat tetap utuh: nama petugas tersimpan di tiap transaksi
            flash('success', 'Pengguna dihapus. Riwayat transaksi tetap tersimpan atas nama petugas.');
        }
    }
    redirect('users/index.php');
}

$q = get('q');
$where = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE nama_lengkap LIKE ? OR username LIKE ?';
    $params = ["%$q%", "%$q%"];
}
$total = (int)db_val("SELECT COUNT(*) FROM users $where", $params);
$pg = paginate($total);
$rows = db_all("SELECT id, nama_lengkap, username, role, is_active, last_login, created_at FROM users $where ORDER BY role, nama_lengkap LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);

$pageTitle = 'Pengguna';
$active = 'users';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Pengelolaan Pengguna</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" data-title="Tambah Pengguna"><i class="bi bi-person-plus me-1"></i>Tambah Pengguna</button>
</div>

<div class="card mb-3"><div class="card-body py-2">
    <form class="row g-2" method="get">
        <div class="col-sm"><input class="form-control" name="q" placeholder="Cari nama atau username..." value="<?= e($q) ?>"></div>
        <div class="col-sm-auto"><button class="btn btn-outline-primary"><i class="bi bi-search me-1"></i>Cari</button> <?php if ($q !== ''): ?><a class="btn btn-light" href="?">Reset</a><?php endif; ?></div>
    </form>
</div></div>

<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Nama Lengkap</th><th>Username</th><th>Peran</th><th>Status</th><th>Dibuat</th><th>Login Terakhir</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $u): ?>
            <tr>
                <td class="fw-semibold"><?= e($u['nama_lengkap']) ?><?= (int)$u['id'] === user_id() ? ' <span class="badge text-bg-light border">Anda</span>' : '' ?></td>
                <td><?= e($u['username']) ?></td>
                <td><?= badge($u['role'] === 'admin' ? 'Administrator' : 'Laboran', $u['role'] === 'admin' ? 'primary' : 'secondary') ?></td>
                <td><?= $u['is_active'] ? badge('Aktif', 'success') : badge('Nonaktif', 'danger') ?></td>
                <td><?= e(fmt_datetime($u['created_at'])) ?></td>
                <td><?= e(fmt_datetime($u['last_login'])) ?></td>
                <td class="table-actions">
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#userModal" data-title="Ubah Pengguna"
                        data-fill="<?= json_attr(['id' => $u['id'], 'nama_lengkap' => $u['nama_lengkap'], 'username' => $u['username'], 'role' => $u['role'], 'is_active' => (int)$u['is_active']]) ?>"><i class="bi bi-pencil"></i></button>
                    <?php if ((int)$u['id'] !== user_id()): ?>
                    <form method="post" class="d-inline" data-confirm="Hapus pengguna &quot;<?= e($u['username']) ?>&quot;?">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
<?= render_pagination($pg) ?>

<div class="modal fade js-form-modal" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content" autocomplete="off">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="">
        <div class="modal-header"><h5 class="modal-title">Tambah Pengguna</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label req">Nama lengkap</label><input class="form-control" name="nama_lengkap" required maxlength="100"></div>
            <div class="mb-3"><label class="form-label req">Username</label><input class="form-control" name="username" required maxlength="50" autocomplete="off"></div>
            <div class="mb-3"><label class="form-label">Password <span data-show-edit class="text-muted fw-normal d-none">(kosongkan jika tidak diganti)</span><span data-show-new class="text-danger">*</span></label>
                <input class="form-control" type="password" name="password" minlength="8" autocomplete="new-password"><div class="form-text">Minimal 8 karakter. Disimpan sebagai hash.</div></div>
            <div class="row g-3">
                <div class="col-6"><label class="form-label req">Peran</label><select class="form-select" name="role"><option value="laboran">Laboran</option><option value="admin">Administrator</option></select></div>
                <div class="col-6 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="uAktif" value="1" data-default="1" checked><label class="form-check-label" for="uAktif">Akun aktif</label></div></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan</button></div>
    </form></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
