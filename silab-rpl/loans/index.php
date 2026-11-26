<?php
require_once __DIR__ . '/../includes/init.php';
require_login();

$q      = get('q');
$status = get('status');
$jp     = get('jenis_peminjam');
$dari   = get('dari');
$sampai = get('sampai');
$today  = today();

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(l.no_peminjaman LIKE ? OR l.nama_peminjam LIKE ? OR l.nomor_induk LIKE ? OR i.nama_barang LIKE ? OR i.kode_barang LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%");
}
switch ($status) {
    case 'aktif':        $where[] = "l.status <> 'dikembalikan'"; break;
    case 'dipinjam':     $where[] = "l.status = 'dipinjam'"; break;
    case 'sebagian':     $where[] = "l.status = 'sebagian'"; break;
    case 'dikembalikan': $where[] = "l.status = 'dikembalikan'"; break;
    case 'terlambat':    $where[] = "l.status <> 'dikembalikan' AND l.tanggal_rencana_kembali < ?"; $params[] = $today; break;
    default: $status = '';
}
if (isset(jenis_peminjam_labels()[$jp])) { $where[] = 'l.jenis_peminjam = ?'; $params[] = $jp; }
if (valid_date($dari)) { $where[] = 'l.tanggal_pinjam >= ?'; $params[] = $dari; }
if (valid_date($sampai)) { $where[] = 'l.tanggal_pinjam <= ?'; $params[] = $sampai; }
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$from = 'FROM loans l JOIN items i ON i.id = l.item_id';
$total = (int)db_val("SELECT COUNT(*) $from $w", $params);
$pg = paginate($total);
$rows = db_all("SELECT l.*, i.kode_barang, i.nama_barang, i.satuan $from $w ORDER BY (l.status = 'dikembalikan'), l.tanggal_rencana_kembali, l.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);

$cntAktif = (int)db_val("SELECT COUNT(*) FROM loans WHERE status <> 'dikembalikan'");
$cntLate  = (int)db_val("SELECT COUNT(*) FROM loans WHERE status <> 'dikembalikan' AND tanggal_rencana_kembali < ?", [$today]);

$pageTitle = 'Peminjaman';
$active = 'loans';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Peminjaman &amp; Pengembalian</h1>
    <a class="btn btn-primary" href="<?= url('loans/create.php') ?>"><i class="bi bi-plus-lg me-1"></i>Peminjaman Baru</a>
</div>

<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link py-1 <?= $status === '' ? 'active' : '' ?>" href="?">Semua</a></li>
    <li class="nav-item"><a class="nav-link py-1 <?= $status === 'aktif' ? 'active' : '' ?>" href="?status=aktif">Pinjaman Aktif <span class="badge text-bg-light"><?= $cntAktif ?></span></a></li>
    <li class="nav-item"><a class="nav-link py-1 <?= $status === 'terlambat' ? 'active' : '' ?>" href="?status=terlambat">Terlambat <span class="badge text-bg-danger"><?= $cntLate ?></span></a></li>
    <li class="nav-item"><a class="nav-link py-1 <?= $status === 'dikembalikan' ? 'active' : '' ?>" href="?status=dikembalikan">Dikembalikan</a></li>
</ul>

<div class="card mb-3"><div class="card-body py-3">
    <form class="row g-2 filter-bar" method="get">
        <div class="col-12 col-lg-3"><input class="form-control" name="q" placeholder="Cari no., peminjam, NIS/NIP, barang..." value="<?= e($q) ?>"></div>
        <div class="col-6 col-lg-2"><select class="form-select" name="status"><?= options(['aktif' => 'Aktif (belum kembali)', 'dipinjam' => 'Dipinjam', 'sebagian' => 'Dikembalikan sebagian', 'dikembalikan' => 'Dikembalikan', 'terlambat' => 'Terlambat'], $status, 'Semua status') ?></select></div>
        <div class="col-6 col-lg-2"><select class="form-select" name="jenis_peminjam"><?= options(jenis_peminjam_labels(), $jp, 'Semua peminjam') ?></select></div>
        <div class="col-6 col-lg-2"><input class="form-control" type="date" name="dari" value="<?= e($dari) ?>" title="Dipinjam dari"></div>
        <div class="col-6 col-lg-2"><input class="form-control" type="date" name="sampai" value="<?= e($sampai) ?>" title="Dipinjam sampai"></div>
        <div class="col-auto"><button class="btn btn-outline-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button> <a class="btn btn-light" href="?">Reset</a></div>
    </form>
</div></div>

<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>No. Pinjam</th><th>Peminjam</th><th>Barang</th><th class="text-end">Jumlah</th><th>Tgl Pinjam</th><th>Rencana Kembali</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data peminjaman.</td></tr><?php endif; ?>
        <?php foreach ($rows as $l): $sisa = (int)$l['jumlah'] - (int)$l['jumlah_dikembalikan']; ?>
            <tr class="<?= loan_effective_status($l) === 'terlambat' ? 'table-danger' : '' ?>">
                <td class="text-nowrap fw-semibold"><a class="text-decoration-none" href="<?= url('loans/view.php?id=' . $l['id']) ?>"><?= e($l['no_peminjaman']) ?></a></td>
                <td><?= e($l['nama_peminjam']) ?><div class="small text-muted"><?= e(jenis_peminjam_labels()[$l['jenis_peminjam']]) ?><?= $l['nomor_induk'] ? ' &middot; ' . e($l['nomor_induk']) : '' ?><?= $l['kelas_bagian'] ? ' &middot; ' . e($l['kelas_bagian']) : '' ?></div></td>
                <td><?= e($l['nama_barang']) ?><div class="small text-muted"><?= e($l['kode_barang']) ?></div></td>
                <td class="text-end"><?= (int)$l['jumlah'] ?> <small class="text-muted"><?= e($l['satuan']) ?></small><?php if ($sisa > 0 && $l['jumlah_dikembalikan'] > 0): ?><div class="small text-muted">sisa <?= $sisa ?></div><?php endif; ?></td>
                <td class="text-nowrap"><?= e(fmt_date($l['tanggal_pinjam'])) ?></td>
                <td class="text-nowrap"><?= e(fmt_date($l['tanggal_rencana_kembali'])) ?></td>
                <td><?= loan_status_badge($l) ?></td>
                <td class="table-actions"><a class="btn btn-sm btn-outline-primary" href="<?= url('loans/view.php?id=' . $l['id']) ?>"><?= $sisa > 0 ? '<i class="bi bi-box-arrow-in-down-left me-1"></i>Kembalikan' : '<i class="bi bi-eye me-1"></i>Detail' ?></a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
<?= render_pagination($pg) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
