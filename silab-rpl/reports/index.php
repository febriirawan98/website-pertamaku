<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/reports.php';
require_login();

$items = db_all('SELECT id, kode_barang, nama_barang FROM items ORDER BY kode_barang');
$itemMap = [];
foreach ($items as $i) $itemMap[$i['id']] = $i['kode_barang'] . ' - ' . $i['nama_barang'];

$pageTitle = 'Laporan';
$active = 'reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Laporan Inventaris</h1></div>

<form method="get" action="<?= url('reports/view.php') ?>" class="card" style="max-width:900px"><div class="card-body">
    <div class="row g-3">
        <div class="col-12"><label class="form-label req">Jenis laporan</label>
            <select class="form-select" name="type" id="reportType" required>
                <?php foreach (report_types() as $k => [$label, $filters]): ?>
                    <option value="<?= $k ?>" data-filters="<?= e(implode(',', $filters)) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-md-3" data-filter="tanggal"><label class="form-label">Dari tanggal</label><input class="form-control" type="date" name="dari"></div>
        <div class="col-md-3" data-filter="tanggal"><label class="form-label">Sampai tanggal</label><input class="form-control" type="date" name="sampai"></div>
        <div class="col-md-6" data-filter="category"><label class="form-label">Kategori</label><select class="form-select" name="category"><?= options(rows_to_map(all_categories(), 'id', 'nama_kategori'), '', 'Semua kategori') ?></select></div>
        <div class="col-md-6" data-filter="location"><label class="form-label">Lokasi</label><select class="form-select" name="location"><?= options(rows_to_map(all_locations(), 'id', 'nama_lokasi'), '', 'Semua lokasi') ?></select></div>
        <div class="col-md-6" data-filter="kondisi"><label class="form-label">Kondisi</label><select class="form-select" name="kondisi"><?= options(kondisi_labels(), '', 'Semua kondisi') ?></select></div>
        <div class="col-md-6" data-filter="jenis"><label class="form-label">Jenis transaksi</label><select class="form-select" name="jenis"><?= options(jenis_trx_labels(), '', 'Semua jenis') ?></select></div>
        <div class="col-12" data-filter="item"><label class="form-label req">Barang</label><select class="form-select" name="item"><?= options($itemMap, '', '- Pilih barang -') ?></select></div>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit"><i class="bi bi-file-earmark-text me-1"></i>Tampilkan Laporan</button>
        <button class="btn btn-outline-success" type="submit" name="format" value="csv"><i class="bi bi-filetype-csv me-1"></i>Ekspor CSV (Excel)</button>
    </div>
</div></form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
