<?php
require_once __DIR__ . '/../includes/init.php';
require_login();

$q     = get('q');
$jenis = get('jenis');
$dari  = get('dari');
$sampai = get('sampai');
$item  = (int)get('item_id');

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(t.no_transaksi LIKE ? OR i.nama_barang LIKE ? OR i.kode_barang LIKE ? OR t.sumber_tujuan LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
if (isset(jenis_trx_labels()[$jenis])) { $where[] = 't.jenis = ?'; $params[] = $jenis; }
if (valid_date($dari)) { $where[] = 't.tanggal >= ?'; $params[] = $dari; }
if (valid_date($sampai)) { $where[] = 't.tanggal <= ?'; $params[] = $sampai; }
if ($item) { $where[] = 't.item_id = ?'; $params[] = $item; }
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$from = 'FROM stock_transactions t JOIN items i ON i.id = t.item_id LEFT JOIN locations l ON l.id = t.location_id';
$total = (int)db_val("SELECT COUNT(*) $from $w", $params);
$pg = paginate($total);
$rows = db_all("SELECT t.*, i.kode_barang, i.nama_barang, i.satuan, l.nama_lokasi $from $w ORDER BY t.tanggal DESC, t.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
$lbl = jenis_trx_labels();

$pageTitle = 'Transaksi Stok';
$active = 'transactions';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Transaksi Barang Masuk &amp; Keluar</h1>
    <a class="btn btn-primary" href="<?= url('transactions/create.php' . ($item ? '?item_id=' . $item : '')) ?>"><i class="bi bi-plus-lg me-1"></i>Transaksi Baru</a>
</div>

<div class="card mb-3"><div class="card-body py-3">
    <form class="row g-2 filter-bar" method="get">
        <?php if ($item): ?><input type="hidden" name="item_id" value="<?= $item ?>"><?php endif; ?>
        <div class="col-12 col-lg-3"><input class="form-control" name="q" placeholder="Cari no. transaksi, barang, sumber..." value="<?= e($q) ?>"></div>
        <div class="col-12 col-lg-3"><select class="form-select" name="jenis"><?= options($lbl, $jenis, 'Semua jenis transaksi') ?></select></div>
        <div class="col-6 col-lg-2"><input class="form-control" type="date" name="dari" value="<?= e($dari) ?>" title="Dari tanggal"></div>
        <div class="col-6 col-lg-2"><input class="form-control" type="date" name="sampai" value="<?= e($sampai) ?>" title="Sampai tanggal"></div>
        <div class="col-auto"><button class="btn btn-outline-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button> <a class="btn btn-light" href="?">Reset</a></div>
    </form>
</div></div>

<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>No. Transaksi</th><th>Tanggal</th><th>Jenis</th><th>Barang</th><th class="text-end">Jumlah</th><th class="text-end">Stok</th><th>Lokasi</th><th>Sumber / Tujuan</th><th>Petugas</th><th>Keterangan</th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="10" class="text-center text-muted py-4">Belum ada transaksi.</td></tr><?php endif; ?>
        <?php foreach ($rows as $t): ?>
            <tr>
                <td class="text-nowrap fw-semibold"><?= e($t['no_transaksi']) ?></td>
                <td class="text-nowrap"><?= e(fmt_date($t['tanggal'])) ?></td>
                <td><?= badge($lbl[$t['jenis']], $t['jenis'] === 'penyesuaian' ? 'info' : ($t['arah'] === 'masuk' ? 'success' : 'danger')) ?></td>
                <td><a class="text-decoration-none" href="<?= url('items/view.php?id=' . $t['item_id']) ?>"><?= e($t['nama_barang']) ?></a><div class="small text-muted"><?= e($t['kode_barang']) ?></div></td>
                <td class="text-end fw-semibold <?= $t['arah'] === 'masuk' ? 'text-success' : 'text-danger' ?>"><?= $t['arah'] === 'masuk' ? '+' : '-' ?><?= fmt_int($t['jumlah']) ?> <small class="text-muted"><?= e($t['satuan']) ?></small></td>
                <td class="text-end text-nowrap"><?= (int)$t['stok_sebelum'] ?> &rarr; <?= (int)$t['stok_sesudah'] ?></td>
                <td><?= e($t['nama_lokasi']) ?></td>
                <td><?= e($t['sumber_tujuan']) ?></td>
                <td><?= e($t['petugas']) ?></td>
                <td class="small"><?= e($t['keterangan']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
<?= render_pagination($pg) ?>
<div class="small text-muted mt-2"><i class="bi bi-info-circle me-1"></i>Transaksi bersifat permanen sebagai jejak audit. Kesalahan input dikoreksi dengan transaksi penyesuaian.</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
