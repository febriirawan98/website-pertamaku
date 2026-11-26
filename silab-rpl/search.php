<?php
require_once __DIR__ . '/includes/init.php';
require_login();

$q = get('q');
$items = $loans = [];
if ($q !== '') {
    $like = "%$q%";
    $items = db_all(
        'SELECT i.id, i.kode_barang, i.nama_barang, i.nomor_seri, i.kondisi, i.is_active, i.satuan, c.nama_kategori, l.nama_lokasi, s.jumlah_total, s.jumlah_tersedia
           FROM items i JOIN categories c ON c.id = i.category_id JOIN locations l ON l.id = i.location_id JOIN v_item_stock s ON s.item_id = i.id
          WHERE i.kode_barang LIKE ? OR i.nama_barang LIKE ? OR i.nomor_seri LIKE ? ORDER BY i.kode_barang LIMIT 50', [$like, $like, $like]);
    $loans = db_all(
        'SELECT l.*, i.nama_barang, i.kode_barang FROM loans l JOIN items i ON i.id = l.item_id
          WHERE l.nama_peminjam LIKE ? OR l.nomor_induk LIKE ? OR l.no_peminjaman LIKE ? ORDER BY l.tanggal_pinjam DESC, l.id DESC LIMIT 50', [$like, $like, $like]);
}

$pageTitle = 'Pencarian';
$active = '';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><h1>Pencarian</h1></div>
<form class="card mb-3" method="get"><div class="card-body py-2"><div class="input-group">
    <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Kode barang, nama barang, nomor seri, atau nama peminjam" autofocus>
    <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Cari</button></div></div></form>

<?php if ($q === ''): ?>
    <p class="text-muted">Ketik kata kunci untuk mencari barang (kode, nama, nomor seri) dan peminjaman (nama peminjam, NIS/NIP, nomor pinjam).</p>
<?php else: ?>
    <div class="card mb-3"><div class="card-header">Barang (<?= count($items) ?>)</div><div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0"><thead><tr><th>Kode</th><th>Nama</th><th>No. Seri</th><th>Kategori</th><th>Lokasi</th><th class="text-end">Jumlah</th><th class="text-end">Tersedia</th><th>Kondisi</th></tr></thead><tbody>
        <?php if (!$items): ?><tr><td colspan="8" class="text-center text-muted py-3">Tidak ada barang yang cocok.</td></tr><?php endif; ?>
        <?php foreach ($items as $i): ?><tr>
            <td><a href="<?= url('items/view.php?id=' . $i['id']) ?>"><?= e($i['kode_barang']) ?></a></td><td><?= e($i['nama_barang']) ?><?= $i['is_active'] ? '' : ' ' . badge('Nonaktif', 'secondary') ?></td>
            <td><?= e($i['nomor_seri']) ?></td><td><?= e($i['nama_kategori']) ?></td><td><?= e($i['nama_lokasi']) ?></td>
            <td class="text-end"><?= (int)$i['jumlah_total'] ?> <?= e($i['satuan']) ?></td><td class="text-end"><?= (int)$i['jumlah_tersedia'] ?></td><td><?= kondisi_badge($i['kondisi']) ?></td></tr>
        <?php endforeach; ?></tbody></table></div></div>

    <div class="card"><div class="card-header">Peminjaman (<?= count($loans) ?>)</div><div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0"><thead><tr><th>No.</th><th>Peminjam</th><th>NIS/NIP</th><th>Barang</th><th>Jumlah</th><th>Tgl Pinjam</th><th>Status</th></tr></thead><tbody>
        <?php if (!$loans): ?><tr><td colspan="7" class="text-center text-muted py-3">Tidak ada peminjaman yang cocok.</td></tr><?php endif; ?>
        <?php foreach ($loans as $l): ?><tr>
            <td><a href="<?= url('loans/view.php?id=' . $l['id']) ?>"><?= e($l['no_peminjaman']) ?></a></td><td><?= e($l['nama_peminjam']) ?></td><td><?= e($l['nomor_induk']) ?></td>
            <td><?= e($l['nama_barang']) ?></td><td><?= (int)$l['jumlah'] ?></td><td><?= e(fmt_date($l['tanggal_pinjam'])) ?></td><td><?= loan_status_badge($l) ?></td></tr>
        <?php endforeach; ?></tbody></table></div></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
