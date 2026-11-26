<?php
/**
 * SILAB-RPL - Instalasi aman.
 *  1) membuat database + tabel (bila belum ada) dari database/schema.sql,
 *  2) opsional memuat data contoh dari database/seed.sql,
 *  3) membuat akun administrator pertama dengan password pilihan Anda
 *     (disimpan sebagai hash; tidak ada password bawaan di dalam kode).
 * Halaman ini otomatis terkunci setelah ada akun pengguna. Hapus file ini
 * dari server setelah instalasi selesai.
 */
require_once __DIR__ . '/includes/init.php';

/** Pecah file SQL menjadi pernyataan-pernyataan (menghormati tanda kutip & komentar). */
function split_sql(string $sql): array
{
    $stmts = [];
    $buf = '';
    $quote = '';
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if ($quote !== '') {
            $buf .= $c;
            if ($c === '\\' && $i + 1 < $len) {
                $buf .= $sql[++$i];
            } elseif ($c === $quote) {
                if ($i + 1 < $len && $sql[$i + 1] === $quote) {
                    $buf .= $sql[++$i];
                } else {
                    $quote = '';
                }
            }
            continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') {
            $quote = $c;
            $buf .= $c;
        } elseif ($c === '-' && ($sql[$i + 1] ?? '') === '-' && in_array($sql[$i + 2] ?? ' ', [' ', "\t", "\r", "\n"], true)) {
            while ($i < $len && $sql[$i] !== "\n") {
                $i++;
            }
        } elseif ($c === ';') {
            if (trim($buf) !== '') {
                $stmts[] = trim($buf);
            }
            $buf = '';
        } else {
            $buf .= $c;
        }
    }
    if (trim($buf) !== '') {
        $stmts[] = trim($buf);
    }
    return $stmts;
}

function run_sql_file(PDO $pdo, string $file): void
{
    foreach (split_sql((string)file_get_contents($file)) as $stmt) {
        $pdo->exec($stmt);
    }
}

$errors = [];
$server = null;
$status = ['server' => false, 'db' => false, 'tables' => false, 'items' => 0, 'users' => 0];

try {
    $server = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $status['server'] = true;
    $st = $server->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
    $st->execute([DB_NAME]);
    $status['db'] = (int)$st->fetchColumn() > 0;
    if ($status['db']) {
        $st = $server->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (\'users\',\'categories\',\'locations\',\'items\',\'stock_transactions\',\'loans\',\'maintenance\')');
        $st->execute([DB_NAME]);
        $status['tables'] = (int)$st->fetchColumn() === 7;
        if ($status['tables']) {
            $server->exec('USE `' . str_replace('`', '', DB_NAME) . '`');
            $status['users'] = (int)$server->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $status['items'] = (int)$server->query('SELECT COUNT(*) FROM items')->fetchColumn();
        }
    }
} catch (PDOException $e) {
    error_log('install.php: ' . $e->getMessage());
    $errors[] = 'Tidak dapat terhubung ke server MySQL. Pastikan MySQL di XAMPP sudah di-Start dan periksa DB_HOST/DB_USER/DB_PASS di config/config.php.';
}

$locked = $status['tables'] && $status['users'] > 0;
$done = false;

if (!$locked && $status['server'] && is_post()) {
    csrf_verify();
    $nama = post('nama_lengkap');
    $username = post('username');
    $pw = (string)($_POST['password'] ?? '');
    $pw2 = (string)($_POST['password2'] ?? '');
    $withSeed = isset($_POST['seed']);

    if ($nama === '' || mb_strlen($nama) > 100) $errors[] = 'Nama lengkap wajib diisi (maks. 100 karakter).';
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) $errors[] = 'Username 3-50 karakter: huruf, angka, titik, garis bawah, atau strip.';
    if (strlen($pw) < 8) $errors[] = 'Password minimal 8 karakter.';
    if ($pw !== $pw2) $errors[] = 'Konfirmasi password tidak sama.';

    if (!$errors) {
        try {
            if (!$status['tables']) {
                run_sql_file($server, ROOT_PATH . '/database/schema.sql');
                $status['tables'] = true;
                $status['items'] = 0;
            }
            $server->exec('USE `' . str_replace('`', '', DB_NAME) . '`');
            if ($withSeed && $status['items'] === 0) {
                run_sql_file($server, ROOT_PATH . '/database/seed.sql');
            }
            $st = $server->prepare('INSERT INTO users (nama_lengkap, username, password_hash, role, is_active) VALUES (?,?,?,?,1)');
            $st->execute([$nama, $username, password_hash($pw, PASSWORD_DEFAULT), 'admin']);
            $done = true;
            flash('success', 'Instalasi selesai. Silakan login dengan akun administrator yang baru dibuat. Hapus file install.php demi keamanan.');
            redirect('login.php');
        } catch (Throwable $e) {
            error_log('install.php: ' . $e->getMessage());
            $errors[] = 'Instalasi gagal: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalasi - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body style="background:var(--gray-bg)">
<div class="container py-4" style="max-width:720px">
    <div class="text-center mb-3">
        <div class="login-logo"><i class="bi bi-gear"></i></div>
        <h1 class="h4 fw-bold" style="color:var(--navy)">Instalasi <?= e(APP_NAME) ?></h1>
        <div class="text-muted small"><?= e(SCHOOL_NAME) ?></div>
    </div>

    <div class="card mb-3"><div class="card-body">
        <h2 class="h6 fw-bold">Status sistem</h2>
        <ul class="list-unstyled mb-0 small">
            <?php
            $row = function (bool $ok, string $t) {
                return '<li><i class="bi bi-' . ($ok ? 'check-circle-fill text-success' : 'x-circle-fill text-danger') . ' me-2"></i>' . e($t) . '</li>';
            };
            echo $row($status['server'], 'Koneksi ke server MySQL');
            echo $row($status['db'], 'Database "' . DB_NAME . '"' . ($status['db'] ? ' tersedia' : ' belum dibuat (akan dibuat otomatis)'));
            echo $row($status['tables'], $status['tables'] ? 'Ke-7 tabel tersedia' : 'Tabel belum lengkap (akan dibuat dari database/schema.sql)');
            echo $row(version_compare(PHP_VERSION, '8.0.0', '>='), 'PHP ' . PHP_VERSION . ' (butuh 8.0+)');
            echo $row(extension_loaded('pdo_mysql'), 'Ekstensi pdo_mysql');
            ?>
        </ul>
    </div></div>

    <?php foreach ($errors as $er): ?>
        <div class="alert alert-danger py-2"><i class="bi bi-exclamation-octagon me-1"></i><?= e($er) ?></div>
    <?php endforeach; ?>

    <?php if ($locked): ?>
        <div class="alert alert-warning">
            <i class="bi bi-lock-fill me-1"></i><b>Aplikasi sudah terpasang</b> (sudah ada <?= (int)$status['users'] ?> akun pengguna), sehingga halaman instalasi dikunci.
            Hapus file <code>install.php</code> dari server.
        </div>
        <a class="btn btn-primary" href="<?= url('login.php') ?>">Ke halaman login</a>
    <?php elseif ($status['server']): ?>
        <div class="card"><div class="card-body">
            <h2 class="h6 fw-bold mb-3">Buat akun Administrator</h2>
            <form method="post" autocomplete="off">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label req">Nama lengkap</label>
                        <input class="form-control" name="nama_lengkap" required maxlength="100" value="<?= e(post('nama_lengkap')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label req">Username</label>
                        <input class="form-control" name="username" required maxlength="50" value="<?= e(post('username')) ?>">
                    </div>
                    <div class="col-md-6"></div>
                    <div class="col-md-6">
                        <label class="form-label req">Password (min. 8 karakter)</label>
                        <input class="form-control" type="password" name="password" required minlength="8">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label req">Ulangi password</label>
                        <input class="form-control" type="password" name="password2" required minlength="8">
                    </div>
                    <?php if ($status['items'] === 0): ?>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="seed" id="seed" value="1" checked>
                            <label class="form-check-label" for="seed">Muat data contoh (kategori, lokasi, barang, transaksi, peminjaman, pemeliharaan) untuk pengujian</label>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i>Pasang &amp; buat akun</button>
                    </div>
                </div>
            </form>
        </div></div>
    <?php endif; ?>
</div>
</body>
</html>
