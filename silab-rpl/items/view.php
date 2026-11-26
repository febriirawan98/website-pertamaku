<?php
require_once __DIR__ . '/../includes/init.php';
require_login();

$id = (int)get('id');
$item = $id ? db_row(
    'SELECT i.*, c.nama_kategori, l.nama_lokasi, s.jumlah_dipinjam, s.jumlah_pemeliharaan, s.jumlah_tersedia
       FROM items i JOIN categories c ON c.id = i.category_id JOIN locations l ON l.id = i.location_id
       JOIN v_item_stock s ON s.item_id = i.id WHERE i.id = ?', [$id]) : null;
if (!$item) {
    flash('error', 'Barang tidak ditemukan.');
    redirect('items/index.php');
}

$trx = db_all('SELECT t.*, l.nama_lokasi FROM stock_transactions t LEFT JOIN locations l ON l.id = t.location_id WHERE t.item_id = ? ORDER BY t.tanggal DESC, t.id DESC', [$id]);
$loans = db_all('SELECT * FROM loans WHERE item_id = ? ORDER BY tanggal_pinjam DESC, id DESC', [$id]);
$mnt = db_all('SELECT * FROM maintenance WHERE item_id = ? ORDER BY tanggal_periksa DESC, id DESC', [$id]);
$jenisLbl = jenis_trx_labels();

$pageTitle = 'Detail Barang';
$active = 'items';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1><?= e($item['nama_barang']) ?> <small class="text-muted fs-6"><?= e($item['kode_barang']) ?></small>
        <?= $item['is_active'] ? '' : badge('Nonaktif', 'secondary') ?></h1>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-primary" href="<?= url('items/form.php?id=' . $id) ?>"><i class="bi bi-pencil me-1"></i>Ubah</a>
        <?php if ($item['is_active']): ?>
        <a class="btn btn-outline-primary" href="<?= url('transactions/create.php?item_id=' . $id) ?>"><i class="bi bi-arrow-left-right me-1"></i>Transaksi</a>
        <a class="btn btn-outline-primary" href="<?= url('loans/create.php?item_id=' . $id) ?>"><i class="bi bi-box-arrow-up-right me-1"></i>Pinjamkan</a>
        <a class="btn btn-outline-primary" href="<?= url('maintenance/form.php?item_id=' . $id) ?>"><i class="bi bi-tools me-1"></i>Pemeliharaan</a>
        <?php endif; ?>
        <a class="btn btn-light border" href="<?= url('items/index.php') ?>"><i class="bi bi-arrow-left me-1"></i>Daftar</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php foreach ([['Jumlah Fisik', $item['jumlah_total'], 'blue', 'boxes'], ['Sedang Dipinjam', $item['jumlah_dipinjam'], 'teal', 'box-arrow-up-right'],
                    ['Dalam Pemeliharaan', $item['jumlah_pemeliharaan'], 'gray', 'tools'], ['Tersedia', $item['jumlah_tersedia'], 'green', 'bag-check']] as [$l, $v, $c, $ic]): ?>
        <div class="col-6 col-lg-3"><div class="card stat-card"><div class="stat-icon bg-soft-<?= $c ?>"><i class="bi bi-<?= $ic ?>"></i></div>
            <div><div class="stat-value"><?= fmt_int($v) ?> <small class="fs-6 text-muted"><?= e($item['satuan']) ?></small></div><div class="stat-label"><?= $l ?></div></div></div></div>
    <?php endforeach; ?>
</div>
<?php if ($item['kondisi'] === 'rusak_berat'): ?>
    <div class="alert alert-warning py-2"><i class="bi bi-exclamation-triangle me-1"></i>Barang berkondisi <b>rusak berat</b>, sehingga tidak dihitung tersedia dan tidak dapat dipinjam.</div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-lg-8"><div class="card h-100"><div class="card-header">Informasi Barang</div><div class="card-body">
        <dl class="row dl-grid mb-0">
            <dt class="col-sm-4">Kategori</dt><dd class="col-sm-8"><?= e($item['nama_kategori']) ?></dd>
            <dt class="col-sm-4">Lokasi penyimpanan</dt><dd class="col-sm-8"><?= e($item['nama_lokasi']) ?></dd>
            <dt class="col-sm-4">Merek / model</dt><dd class="col-sm-8"><?= e(trim($item['merek'] . ' ' . $item['model'])) ?: '-' ?></dd>
            <dt class="col-sm-4">Nomor seri</dt><dd class="col-sm-8"><?= e($item['nomor_seri']) ?: '-' ?></dd>
            <dt class="col-sm-4">Kondisi</dt><dd class="col-sm-8"><?= kondisi_badge($item['kondisi']) ?> &nbsp; Stok: <?= stock_badge($item) ?> <small class="text-muted">(minimum <?= (int)$item['stok_minimum'] ?>)</small></dd>
            <dt class="col-sm-4">Tanggal pengadaan</dt><dd class="col-sm-8"><?= e(fmt_date($item['tanggal_pengadaan'])) ?></dd>
            <dt class="col-sm-4">Sumber pengadaan</dt><dd class="col-sm-8"><?= e($item['sumber_pengadaan']) ?: '-' ?></dd>
            <dt class="col-sm-4">Harga satuan</dt><dd class="col-sm-8"><?= e(fmt_rp($item['harga_satuan'])) ?> <small class="text-muted">(nilai total <?= e(fmt_rp($item['harga_satuan'] * $item['jumlah_total'])) ?>)</small></dd>
            <dt class="col-sm-4">Keterangan</dt><dd class="col-sm-8"><?= nl2br(e($item['keterangan'])) ?: '-' ?></dd>
        </dl>
    </div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-header">Foto</div><div class="card-body text-center">
        <?php if ($item['foto']): ?><img class="item-photo" src="<?= url('uploads/items/' . e($item['foto'])) ?>" alt="Foto <?= e($item['nama_barang']) ?>">
        <?php else: ?><div class="text-muted py-4"><i class="bi bi-image fs-1"></i><div>Belum ada foto</div></div><?php endif; ?>
    </div></div></div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabTrx" type="button">Transaksi (<?= count($trx) ?>)</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabLoan" type="button">Peminjaman (<?= count($loans) ?>)</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabMnt" type="button">Pemeliharaan (<?= count($mnt) ?>)</button></li>
</ul>
<div class="tab-content">
    <div class="tab-pane fade show active" id="tabTrx"><div class="card"><div class="table-responsive">
        <table class="table table-sm align-middle mb-0"><thead><tr><th>No. Transaksi</th><th>Tanggal</th><th>Jenis</th><th class="text-end">Jumlah</th><th class="text-end">Stok</th><th>Sumber/Tujuan</th><th>Petugas</th><th>Keterangan</th></tr></thead><tbody>
        <?php if (!$trx): ?><tr><td colspan="8" class="text-center text-muted py-3">Belum ada transaksi.</td></tr><?php endif; ?>
        <?php foreach ($trx as $t): ?><tr>
            <td class="text-nowrap"><?= e($t['no_transaksi']) ?></td><td><?= e(fmt_date($t['tanggal'])) ?></td><td><?= e($jenisLbl[$t['jenis']]) ?></td>
            <td class="text-end <?= $t['arah'] === 'masuk' ? 'text-success' : 'text-danger' ?>"><?= $t['arah'] === 'masuk' ? '+' : '-' ?><?= (int)$t['jumlah'] ?></td>
            <td class="text-end text-nowrap"><?= (int)$t['stok_sebelum'] ?> &rarr; <?= (int)$t['stok_sesudah'] ?></td>
            <td><?= e($t['sumber_tujuan']) ?></td><td><?= e($t['petugas']) ?></td><td><?= e($t['keterangan']) ?></td></tr>
        <?php endforeach; ?></tbody></table></div></div></div>

    <div class="tab-pane fade" id="tabLoan"><div class="card"><div class="table-responsive">
        <table class="table table-sm align-middle mb-0"><thead><tr><th>No.</th><th>Peminjam</th><th>Jumlah</th><th>Pinjam</th><th>Rencana kembali</th><th>Kembali</th><th>Status</th><th></th></tr></thead><tbody>
        <?php if (!$loans): ?><tr><td colspan="8" class="text-center text-muted py-3">Belum pernah dipinjam.</td></tr><?php endif; ?>
        <?php foreach ($loans as $l): ?><tr>
            <td class="text-nowrap"><?= e($l['no_peminjaman']) ?></td>
            <td><?= e($l['nama_peminjam']) ?><div class="small text-muted"><?= e(jenis_peminjam_labels()[$l['jenis_peminjam']]) ?> <?= e($l['kelas_bagian']) ?></div></td>
            <td><?= (int)$l['jumlah_dikembalikan'] ?>/<?= (int)$l['jumlah'] ?> kembali</td>
            <td><?= e(fmt_date($l['tanggal_pinjam'])) ?></td><td><?= e(fmt_date($l['tanggal_rencana_kembali'])) ?></td><td><?= e(fmt_date($l['tanggal_kembali_aktual'])) ?></td>
            <td><?= loan_status_badge($l) ?></td><td><a class="btn btn-sm btn-outline-secondary py-0" href="<?= url('loans/view.php?id=' . $l['id']) ?>">Detail</a></td></tr>
        <?php endforeach; ?></tbody></table></div></div></div>

    <div class="tab-pane fade" id="tabMnt"><div class="card"><div class="table-responsive">
        <table class="table table-sm align-middle mb-0"><thead><tr><th>Tanggal</th><th>Jenis</th><th>Unit</th><th>Kerusakan / tindakan</th><th>Teknisi</th><th class="text-end">Biaya</th><th>Status</th><th>Selesai</th><th></th></tr></thead><tbody>
        <?php if (!$mnt): ?><tr><td colspan="9" class="text-center text-muted py-3">Belum ada catatan pemeliharaan.</td></tr><?php endif; ?>
        <?php foreach ($mnt as $m): ?><tr>
            <td><?= e(fmt_date($m['tanggal_periksa'])) ?></td><td><?= e(maint_jenis_labels()[$m['jenis']]) ?></td><td><?= (int)$m['jumlah'] ?></td>
            <td><?= e($m['deskripsi_kerusakan']) ?><div class="small text-muted"><?= e($m['tindakan']) ?></div></td>
            <td><?= e($m['teknisi']) ?></td><td class="text-end"><?= e(fmt_rp($m['biaya'])) ?></td><td><?= maint_status_badge($m['status']) ?></td>
            <td><?= e(fmt_date($m['tanggal_selesai'])) ?></td><td><a class="btn btn-sm btn-outline-secondary py-0" href="<?= url('maintenance/form.php?id=' . $m['id']) ?>">Ubah</a></td></tr>
        <?php endforeach; ?></tbody></table></div></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
