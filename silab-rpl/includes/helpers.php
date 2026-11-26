<?php
declare(strict_types=1);

/** Kesalahan yang pesannya aman ditampilkan kepada pengguna. */
class UserError extends RuntimeException
{
}

// ---- Daftar nilai tetap (label Bahasa Indonesia) ---------------------------
const CONDITIONS = [
    'baik'         => 'Baik',
    'rusak_ringan' => 'Rusak Ringan',
    'rusak_berat'  => 'Rusak Berat',
];
const TRX_TYPES = [
    'masuk_pengadaan'             => 'Barang masuk - pengadaan',
    'masuk_hibah'                 => 'Barang masuk - hibah/bantuan',
    'keluar_pemindahan'           => 'Barang keluar - pemindahan',
    'keluar_pengembalian_pemasok' => 'Barang keluar - pengembalian ke pemasok',
    'keluar_penghapusan'          => 'Barang keluar - penghapusan (telah disetujui)',
    'penyesuaian'                 => 'Penyesuaian stok (pemeriksaan fisik)',
];
const BORROWER_TYPES = ['siswa' => 'Siswa', 'guru' => 'Guru', 'petugas' => 'Petugas'];
const LOAN_STATUS = [
    'dipinjam'              => 'Dipinjam',
    'dikembalikan_sebagian' => 'Dikembalikan sebagian',
    'dikembalikan'          => 'Dikembalikan',
    'terlambat'             => 'Terlambat',
];
const MAINT_TYPES = ['pemeriksaan' => 'Pemeriksaan', 'perawatan' => 'Perawatan', 'perbaikan' => 'Perbaikan'];
const MAINT_STATUS = [
    'menunggu'               => 'Menunggu',
    'dalam_proses'           => 'Dalam proses',
    'selesai'                => 'Selesai',
    'tidak_dapat_diperbaiki' => 'Tidak dapat diperbaiki',
];
const ROLES = ['admin' => 'Administrator', 'laboran' => 'Laboran'];

// ---- Output & URL ------------------------------------------------------------
function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

// ---- Pesan flash -------------------------------------------------------------
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function render_flash(): string
{
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $icon = match ($f['type']) {
            'success' => 'check-circle-fill',
            'danger'  => 'exclamation-triangle-fill',
            'warning' => 'exclamation-circle-fill',
            default   => 'info-circle-fill',
        };
        $out .= '<div class="alert alert-' . e($f['type']) . ' alert-dismissible fade show no-print" role="alert">'
              . '<i class="bi bi-' . $icon . ' me-2"></i>' . e($f['msg'])
              . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

function render_errors(array $errors): string
{
    if (!$errors) {
        return '';
    }
    $li = '';
    foreach ($errors as $m) {
        $li .= '<li>' . e($m) . '</li>';
    }
    return '<div class="alert alert-danger" role="alert"><strong>Data belum dapat disimpan:</strong><ul class="mb-0 mt-1">' . $li . '</ul></div>';
}

// ---- CSRF --------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Wajib dipanggil di awal setiap pemrosesan formulir POST. */
function csrf_check(): void
{
    $t = $_POST['csrf_token'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        abort_page(419, 'Sesi formulir kedaluwarsa atau tidak valid. Muat ulang halaman lalu coba lagi.');
    }
}

function abort_page(int $code, string $msg): never
{
    http_response_code($code);
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Akses ditolak</title></head><body style="font-family:system-ui,sans-serif;max-width:560px;margin:12vh auto;padding:0 1rem;color:#1f2937">'
       . '<h1 style="color:#0b2a5b;font-size:1.4rem">' . e((string) $code) . '</h1><p>' . e($msg) . '</p>'
       . '<p><a href="' . e(url('')) . '">Kembali ke beranda</a></p></body></html>';
    exit;
}

// ---- Input -------------------------------------------------------------------
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

function post_int(string $key, int $default = 0): int
{
    $v = post($key);
    return preg_match('/^-?\d{1,12}$/', $v) ? (int) $v : $default;
}

/** String kosong -> NULL (untuk kolom opsional). */
function nn(string $s): ?string
{
    return $s === '' ? null : $s;
}

function escape_like(string $s): string
{
    return addcslashes($s, '%_\\');
}

function valid_date(string $d): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt !== false && $dt->format('Y-m-d') === $d;
}

/** Angka uang >= 0 dengan maksimal 2 desimal. */
function valid_money(string $s): bool
{
    return preg_match('/^\d{1,13}(\.\d{1,2})?$/', $s) === 1;
}

// ---- Format tampilan -----------------------------------------------------------
function rupiah(float|int|string|null $n): string
{
    return 'Rp ' . number_format((float) $n, 0, ',', '.');
}

function fdate(?string $d): string
{
    if ($d === null || $d === '' || $d === '0000-00-00') {
        return '-';
    }
    $t = strtotime($d);
    return $t ? date('d/m/Y', $t) : '-';
}

function fdatetime(?string $d): string
{
    $t = $d ? strtotime($d) : false;
    return $t ? date('d/m/Y H:i', $t) : '-';
}

function fnum(int|float|string|null $n): string
{
    return number_format((float) $n, 0, ',', '.');
}

function select_options(array $pairs, mixed $selected = null, ?string $placeholder = null): string
{
    $out = $placeholder !== null ? '<option value="">' . e($placeholder) . '</option>' : '';
    foreach ($pairs as $k => $label) {
        $sel = ((string) $k === (string) $selected && $selected !== null && $selected !== '') ? ' selected' : '';
        $out .= '<option value="' . e($k) . '"' . $sel . '>' . e($label) . '</option>';
    }
    return $out;
}

/** Hasil query [ ['id'=>..,'name'=>..], ... ] -> [id => name]. */
function pluck(array $rows, string $key = 'id', string $val = 'name'): array
{
    $out = [];
    foreach ($rows as $r) {
        $out[$r[$key]] = $r[$val];
    }
    return $out;
}

// ---- Lencana (badge) -------------------------------------------------------------
function badge(string $text, string $color): string
{
    return '<span class="badge text-bg-' . e($color) . '">' . e($text) . '</span>';
}

function condition_badge(string $c): string
{
    $color = ['baik' => 'success', 'rusak_ringan' => 'warning', 'rusak_berat' => 'danger'][$c] ?? 'secondary';
    return badge(CONDITIONS[$c] ?? $c, $color);
}

function loan_status_badge(string $s): string
{
    $color = ['dipinjam' => 'primary', 'dikembalikan_sebagian' => 'info', 'dikembalikan' => 'success', 'terlambat' => 'danger'][$s] ?? 'secondary';
    return badge(LOAN_STATUS[$s] ?? $s, $color);
}

function maint_status_badge(string $s): string
{
    $color = ['menunggu' => 'secondary', 'dalam_proses' => 'primary', 'selesai' => 'success', 'tidak_dapat_diperbaiki' => 'danger'][$s] ?? 'secondary';
    return badge(MAINT_STATUS[$s] ?? $s, $color);
}

function trx_badge(string $t): string
{
    $color = str_starts_with($t, 'masuk_') ? 'success' : (str_starts_with($t, 'keluar_') ? 'warning' : 'info');
    return badge(TRX_TYPES[$t] ?? $t, $color);
}

/** Indikator stok berdasarkan jumlah tersedia dibanding jumlah fisik. */
function stock_badge(int $available, int $physical): string
{
    if ($physical <= 0) {
        return badge('Kosong', 'secondary');
    }
    if ($available <= 0) {
        return badge('Habis', 'danger');
    }
    if ($available <= max(1, (int) ceil($physical * 0.2))) {
        return badge('Menipis', 'warning');
    }
    return badge('Tersedia', 'success');
}

// ---- Pagination -------------------------------------------------------------------
function paginate(int $total, int $perPage = PER_PAGE): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);
    return [
        'page'   => $page,
        'pages'  => $pages,
        'per'    => $perPage,
        'offset' => ($page - 1) * $perPage,
        'total'  => $total,
    ];
}

function render_pagination(array $pg): string
{
    $from = $pg['total'] === 0 ? 0 : $pg['offset'] + 1;
    $to   = min($pg['offset'] + $pg['per'], $pg['total']);
    $info = '<div class="small text-muted">Menampilkan ' . fnum($from) . '&ndash;' . fnum($to) . ' dari ' . fnum($pg['total']) . ' data</div>';
    if ($pg['pages'] <= 1) {
        return '<div class="d-flex justify-content-between align-items-center mt-3 no-print">' . $info . '</div>';
    }
    $q = $_GET;
    unset($q['page']);
    $item = function (int $p, string $label, bool $active = false, bool $disabled = false) use ($q): string {
        $href = '?' . http_build_query($q + ['page' => $p]);
        return '<li class="page-item' . ($active ? ' active' : '') . ($disabled ? ' disabled' : '') . '">'
             . '<a class="page-link" href="' . e($href) . '">' . $label . '</a></li>';
    };
    $html = '<nav aria-label="Navigasi halaman"><ul class="pagination pagination-sm mb-0">';
    $html .= $item($pg['page'] - 1, '&laquo;', false, $pg['page'] <= 1);
    $start = max(1, $pg['page'] - 2);
    $end   = min($pg['pages'], $pg['page'] + 2);
    if ($start > 1) {
        $html .= $item(1, '1');
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        $html .= $item($i, (string) $i, $i === $pg['page']);
    }
    if ($end < $pg['pages']) {
        if ($end < $pg['pages'] - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
        $html .= $item($pg['pages'], (string) $pg['pages']);
    }
    $html .= $item($pg['page'] + 1, '&raquo;', false, $pg['page'] >= $pg['pages']);
    $html .= '</ul></nav>';
    return '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 no-print">' . $info . $html . '</div>';
}

// ---- Penomoran otomatis ---------------------------------------------------------------
/**
 * Nomor berurutan per bulan, contoh: TRX-202610-0001.
 * $table/$col hanya berasal dari kode (bukan input pengguna).
 * Keunikan dijamin juga oleh UNIQUE KEY di database.
 */
function next_number(string $prefix, string $table, string $col): string
{
    $pre = $prefix . '-' . date('Ym') . '-';
    $max = fetch_value("SELECT MAX($col) FROM $table WHERE $col LIKE ?", [$pre . '%']);
    $n = $max ? ((int) substr((string) $max, -4)) + 1 : 1;
    return $pre . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
}

// ---- Foto barang -------------------------------------------------------------------------
/** Mengembalikan nama file baru, atau null jika tidak ada file diunggah. */
function save_uploaded_photo(string $field): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new UserError('Unggah foto gagal (kode ' . (int) $f['error'] . '). Pastikan ukuran file tidak melebihi 2 MB.');
    }
    if ($f['size'] > MAX_PHOTO_BYTES) {
        throw new UserError('Ukuran foto maksimal 2 MB.');
    }
    if (!is_uploaded_file($f['tmp_name'])) {
        throw new UserError('File foto tidak valid.');
    }
    $info = @getimagesize($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
    if ($info === false || $ext === null) {
        throw new UserError('Foto harus berformat JPG, PNG, atau WEBP.');
    }
    $dir = APP_ROOT . '/assets/images/items';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new UserError('Folder foto tidak dapat dibuat.');
    }
    $name = bin2hex(random_bytes(10)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        throw new UserError('Foto tidak dapat disimpan. Periksa izin tulis folder assets/images/items.');
    }
    return $name;
}

function delete_photo(?string $name): void
{
    if ($name && preg_match('/^[a-f0-9]{20}\.(jpg|png|webp)$/', $name)) {
        @unlink(APP_ROOT . '/assets/images/items/' . $name);
    }
}
