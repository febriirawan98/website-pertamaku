<?php
/** Fungsi bantu umum: output aman, URL, flash, CSRF, auth, database, format, paginasi. */

// ---------- Output & URL ----------
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path)
{
    header('Location: ' . url($path));
    exit;
}

function today(): string
{
    return date('Y-m-d');
}

// ---------- Flash message ----------
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_render(): string
{
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $cls = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'][$f['type']] ?? 'info';
        $icon = ['success' => 'check-circle-fill', 'danger' => 'exclamation-octagon-fill', 'warning' => 'exclamation-triangle-fill', 'info' => 'info-circle-fill'][$cls];
        $out .= '<div class="alert alert-' . $cls . ' alert-dismissible fade show d-flex align-items-center" role="alert">'
              . '<i class="bi bi-' . $icon . ' me-2"></i><div>' . e($f['msg']) . '</div>'
              . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

// ---------- CSRF ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/** Wajib dipanggil pada setiap penanganan POST. */
function csrf_verify(): void
{
    $sent = $_POST['_token'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('<!DOCTYPE html><meta charset="utf-8"><body style="font-family:sans-serif;max-width:600px;margin:60px auto">'
           . '<h2>Sesi formulir tidak valid</h2><p>Token keamanan (CSRF) tidak cocok atau sudah kedaluwarsa. '
           . 'Silakan kembali, muat ulang halaman, lalu coba lagi.</p></body>');
    }
}

// ---------- Input ----------
function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function get(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function valid_date(?string $s): bool
{
    if ($s === null || $s === '') {
        return false;
    }
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s;
}

/** Angka rupiah dari input: menerima "1500000", "1.500.000", "1500000.50". */
function parse_money(string $s): ?float
{
    $s = trim($s);
    if ($s === '') {
        return 0.0;
    }
    if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $s)) {   // format Indonesia 1.500.000,50
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    }
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $s)) {
        return null;
    }
    return (float)$s;
}

// ---------- Database helper (prepared statements) ----------
function db_all(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function db_row(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $r = $st->fetch();
    return $r === false ? null : $r;
}

function db_val(string $sql, array $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
}

function db_exec(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

function is_duplicate_error(PDOException $e): bool
{
    return ($e->errorInfo[1] ?? 0) === 1062;
}

function is_fk_error(PDOException $e): bool
{
    return in_array($e->errorInfo[1] ?? 0, [1451, 1452], true);
}

// ---------- Autentikasi ----------
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function user_id(): ?int
{
    return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
}

/** Wajib login; juga memastikan akun masih aktif di database. */
function require_login(): void
{
    $u = current_user();
    if (!$u) {
        redirect('login.php');
    }
    $row = db_row('SELECT id, nama_lengkap, username, role, is_active FROM users WHERE id = ?', [$u['id']]);
    if (!$row || !(int)$row['is_active']) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('error', 'Akun Anda tidak aktif atau sudah dihapus.');
        redirect('login.php');
    }
    $_SESSION['user'] = [
        'id' => (int)$row['id'], 'nama' => $row['nama_lengkap'],
        'username' => $row['username'], 'role' => $row['role'],
    ];
}

function require_role(string ...$roles): void
{
    if (!in_array(current_user()['role'] ?? '', $roles, true)) {
        http_response_code(403);
        $pageTitle = 'Akses ditolak';
        $active = '';
        require __DIR__ . '/header.php';
        echo '<div class="alert alert-danger"><i class="bi bi-shield-lock me-2"></i>Anda tidak memiliki hak akses untuk halaman ini.</div>';
        require __DIR__ . '/footer.php';
        exit;
    }
}

// ---------- Format ----------
function fmt_date(?string $d): string
{
    if (!$d || $d === '0000-00-00') {
        return '-';
    }
    $t = strtotime($d);
    return $t ? date('d/m/Y', $t) : '-';
}

function fmt_datetime(?string $d): string
{
    $t = $d ? strtotime($d) : false;
    return $t ? date('d/m/Y H:i', $t) : '-';
}

function fmt_int($n): string
{
    return number_format((float)$n, 0, ',', '.');
}

function fmt_rp($n): string
{
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function bulan_id(int $m): string
{
    return ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][$m] ?? '';
}

function fmt_date_long(string $d): string
{
    $t = strtotime($d);
    return $t ? date('j', $t) . ' ' . bulan_id((int)date('n', $t)) . ' ' . date('Y', $t) : '-';
}

// ---------- Label & lencana ----------
function kondisi_labels(): array
{
    return ['baik' => 'Baik', 'rusak_ringan' => 'Rusak Ringan', 'rusak_berat' => 'Rusak Berat'];
}

function jenis_trx_labels(): array
{
    return [
        'masuk_pengadaan'    => 'Masuk - Pengadaan',
        'masuk_hibah'        => 'Masuk - Hibah/Bantuan',
        'keluar_pemindahan'  => 'Keluar - Pemindahan',
        'keluar_retur'       => 'Keluar - Retur ke Pemasok',
        'keluar_penghapusan' => 'Keluar - Penghapusan',
        'penyesuaian'        => 'Penyesuaian Stok',
    ];
}

function jenis_peminjam_labels(): array
{
    return ['siswa' => 'Siswa', 'guru' => 'Guru', 'petugas' => 'Petugas'];
}

function maint_jenis_labels(): array
{
    return ['pemeriksaan' => 'Pemeriksaan', 'perawatan' => 'Perawatan', 'perbaikan' => 'Perbaikan'];
}

function maint_status_labels(): array
{
    return ['menunggu' => 'Menunggu', 'dalam_proses' => 'Dalam Proses', 'selesai' => 'Selesai', 'tidak_dapat_diperbaiki' => 'Tidak Dapat Diperbaiki'];
}

function badge(string $text, string $color): string
{
    return '<span class="badge text-bg-' . e($color) . '">' . e($text) . '</span>';
}

function kondisi_badge(string $k): string
{
    $c = ['baik' => 'success', 'rusak_ringan' => 'warning', 'rusak_berat' => 'danger'][$k] ?? 'secondary';
    return badge(kondisi_labels()[$k] ?? $k, $c);
}

function stock_badge(array $item): string
{
    $tersedia = (int)$item['jumlah_tersedia'];
    if ($tersedia <= 0) {
        return badge('Habis', 'danger');
    }
    if ($tersedia <= (int)$item['stok_minimum']) {
        return badge('Menipis', 'warning');
    }
    return badge('Cukup', 'success');
}

/** Status efektif peminjaman, termasuk "terlambat" (dihitung saat tampil). */
function loan_effective_status(array $l): string
{
    if ($l['status'] !== 'dikembalikan' && $l['tanggal_rencana_kembali'] < today()) {
        return 'terlambat';
    }
    return $l['status'];
}

function loan_status_badge(array $l): string
{
    $s = loan_effective_status($l);
    $map = [
        'dipinjam' => ['Dipinjam', 'primary'], 'sebagian' => ['Dikembalikan Sebagian', 'info'],
        'dikembalikan' => ['Dikembalikan', 'success'], 'terlambat' => ['Terlambat', 'danger'],
    ];
    return badge($map[$s][0], $map[$s][1]);
}

function maint_status_badge(string $s): string
{
    $c = ['menunggu' => 'secondary', 'dalam_proses' => 'primary', 'selesai' => 'success', 'tidak_dapat_diperbaiki' => 'danger'][$s] ?? 'secondary';
    return badge(maint_status_labels()[$s] ?? $s, $c);
}

// ---------- Paginasi ----------
function paginate(int $total, int $perPage = PER_PAGE): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
    return ['page' => $page, 'pages' => $pages, 'per' => $perPage, 'offset' => ($page - 1) * $perPage, 'total' => $total];
}

function page_url(int $page): string
{
    $q = $_GET;
    $q['page'] = $page;
    return '?' . http_build_query($q);
}

function render_pagination(array $p): string
{
    if ($p['pages'] <= 1) {
        return '<div class="small text-muted mt-2">Total ' . fmt_int($p['total']) . ' data</div>';
    }
    $cur = $p['page'];
    $from = max(1, $cur - 2);
    $to = min($p['pages'], $cur + 2);
    $h = '<div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2">'
       . '<div class="small text-muted">Total ' . fmt_int($p['total']) . ' data &middot; Halaman ' . $cur . ' dari ' . $p['pages'] . '</div>'
       . '<nav><ul class="pagination pagination-sm mb-0">';
    $h .= '<li class="page-item' . ($cur <= 1 ? ' disabled' : '') . '"><a class="page-link" href="' . e(page_url(max(1, $cur - 1))) . '">&laquo;</a></li>';
    if ($from > 1) {
        $h .= '<li class="page-item"><a class="page-link" href="' . e(page_url(1)) . '">1</a></li>';
        if ($from > 2) {
            $h .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
    }
    for ($i = $from; $i <= $to; $i++) {
        $h .= '<li class="page-item' . ($i === $cur ? ' active' : '') . '"><a class="page-link" href="' . e(page_url($i)) . '">' . $i . '</a></li>';
    }
    if ($to < $p['pages']) {
        if ($to < $p['pages'] - 1) {
            $h .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
        $h .= '<li class="page-item"><a class="page-link" href="' . e(page_url($p['pages'])) . '">' . $p['pages'] . '</a></li>';
    }
    $h .= '<li class="page-item' . ($cur >= $p['pages'] ? ' disabled' : '') . '"><a class="page-link" href="' . e(page_url(min($p['pages'], $cur + 1))) . '">&raquo;</a></li>';
    return $h . '</ul></nav></div>';
}

// ---------- Unggah foto barang ----------
function item_photo_dir(): string
{
    return ROOT_PATH . '/uploads/items/';
}

/** Mengembalikan nama file baru, atau null bila tidak ada file. Melempar RuntimeException bila tidak valid. */
function save_item_photo(array $f): ?string
{
    $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($err !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Foto gagal diunggah (kode ' . (int)$err . '). Ukuran maksimum 2 MB.');
    }
    if ($f['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Ukuran foto maksimum 2 MB.');
    }
    $info = @getimagesize($f['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) {
        throw new RuntimeException('Foto harus berformat JPG, PNG, atau WEBP.');
    }
    if (!is_dir(item_photo_dir())) {
        mkdir(item_photo_dir(), 0755, true);
    }
    $name = bin2hex(random_bytes(12)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($f['tmp_name'], item_photo_dir() . $name)) {
        throw new RuntimeException('Foto tidak dapat disimpan di server.');
    }
    return $name;
}

function delete_item_photo(?string $name): void
{
    if ($name && preg_match('/^[a-f0-9]{24}\.(jpg|png|webp)$/', $name)) {
        @unlink(item_photo_dir() . $name);
    }
}

// ---------- Data referensi ----------
function all_categories(): array
{
    return db_all('SELECT id, nama_kategori FROM categories ORDER BY nama_kategori');
}

function all_locations(): array
{
    return db_all('SELECT id, nama_lokasi FROM locations ORDER BY nama_lokasi');
}

function json_attr($data): string
{
    return e(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
}

/** Pilihan <option> dari array id=>label. */
function options(array $map, $selected = '', string $placeholder = ''): string
{
    $h = $placeholder !== '' ? '<option value="">' . e($placeholder) . '</option>' : '';
    foreach ($map as $k => $v) {
        $h .= '<option value="' . e($k) . '"' . ((string)$k === (string)$selected ? ' selected' : '') . '>' . e($v) . '</option>';
    }
    return $h;
}

function rows_to_map(array $rows, string $k, string $v): array
{
    $m = [];
    foreach ($rows as $r) {
        $m[$r[$k]] = $r[$v];
    }
    return $m;
}
