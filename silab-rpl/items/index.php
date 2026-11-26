<?php
require_once __DIR__ . '/../includes/init.php';
require_login();

$back = 'items/index.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '');

if (is_post()) {
    csrf_verify();
    $action = post('action');
    $id = (int)post('id');
    $item = $id ? item_with_stock($id) : null;
    if (!$item) {
        flash('error', 'Barang tidak ditemukan.');
    } elseif ($action === 'delete') {
        $hist = (int)db_val('SELECT (SELECT COUNT(*) FROM stock_transactions WHERE item_id = ?) + (SELECT COUNT(*) FROM loans WHERE item_id = ?) + (SELECT COUNT(*) FROM maintenance WHERE item_id = ?)', [$id, $id, $id]);
        if ($hist > 0) {
            flash('error', 'Barang memiliki riwayat transaksi/peminjaman/pemeliharaan sehingga tidak dapat dihapus permanen. Gunakan "Nonaktifkan" agar riwayat tetap tersimpan.');
        } else {
            db_exec('DELETE FROM items WHERE id = ?', [$id]);
            delete_item_photo($item['foto']);
            flash('success', 'Barang "' . $item['nama_barang'] . '" dihapus.');
        }
    } elseif ($action === 'deactivate') {
        if ((int)$item['jumlah_dipinjam'] > 0) {
            flash('error', 'Barang masih dipinjam (' . (int)$item['jumlah_dipinjam'] . ' unit). Selesaikan pengembalian terlebih dahulu.');
        } else {
            db_exec('UPDATE items SET is_active = 0 WHERE id = ?', [$id]);
            flash('success', 'Barang dinonaktifkan. Riwayatnya tetap tersimpan.');
        }
    } elseif ($action === 'activate') {
        db_exec('UPDATE items SET is_active = 1 WHERE id = ?', [$id]);
        flash('success', 'Barang diaktifkan kembali.');
    }
    redirect($back);
}

// ---- Filter ----
$q    = get('q');
$cat  = (int)get('category');
$loc  = (int)get('location');
$kond = get('kondisi');
$stat = get('status', 'aktif');
$stok = get('stok');

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(i.kode_barang LIKE ? OR i.nama_barang LIKE ? OR i.nomor_seri LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
if ($cat) { $where[] = 'i.category_id = ?'; $params[] = $cat; }
if ($loc) { $where[] = 'i.location_id = ?'; $params[] = $loc; }
if (isset(kondisi_labels()[$kond])) { $where[] = 'i.kondisi = ?'; $params[] = $kond; }
if ($stat === 'aktif') { $where[] = 'i.is_active = 1'; }
elseif ($stat === 'nonaktif') { $where[] = 'i.is_active = 0'; }
if ($stok === 'habis') { $where[] = 's.jumlah_tersedia <= 0'; }
elseif ($stok === 'menipis') { $where[] = 's.jumlah_tersedia > 0 AND s.jumlah_tersedia <= i.stok_minimum'; }
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$from = 'FROM items i JOIN categories c ON c.id = i.category_id JOIN locations l ON l.id = i.location_id JOIN v_item_stock s ON s.item_id = i.id';
$total = (int)db_val("SELECT COUNT(*) $from $w", $params);
$pg = paginate($total);
$rows = db_all(
    "SELECT i.*, c.nama_kategori, l.nama_lokasi, s.jumlah_dipinjam, s.jumlah_pemeliharaan, s.jumlah_tersedia
       $from $w ORDER BY i.kode_barang LIMIT {$pg['per']} OFFSET {$pg['offset']}",
    $params
);

$pageTitle = 'Data Barang';
$active = 'items';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Data Barang</h1>
    <a class="btn btn-primary" href="<?= url('items/form.php') ?>"><i class="bi bi-plus-lg me-1"></i>Tambah Barang</a>
</div>

<div class="card mb-3"><div class="card-body py-3">
    <form class="row g-2 filter-bar" method="get">
        <div class="col-12 col-lg-4"><input class="form-control" name="q" placeholder="Cari kode, nama, atau nomor seri..." value="<?= e($q) ?>"></div>
        <div class="col-6 col-lg-2"><select class="form-select" name="category"><?= options(rows_to_map(all_categories(), 'id', 'nama_kategori'), $cat, 'Semua kategori') ?></select></div>
        <div class="col-6 col-lg-2"><select class="form-select" name="kondisi"><?= options(kondisi_labels(), $kond, 'Semua kondisi') ?></select></div>
        <div class="col-6 col-lg-2"><select class="form-select" name="location"><?= options(rows_to_map(all_locations(), 'id', 'nama_lokasi'), $loc, 'Semua lokasi') ?></select></div>
        <div class="col-6 col-lg-2"><select class="form-select" name="stok"><?= options(['habis' => 'Stok habis', 'menipis' => 'Stok menipis'], $stok, 'Semua stok') ?></select></div>
        <div class="col-6 col-lg-2"><select class="form-select" name="status"><?= options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif', 'semua' => 'Semua status'], $stat) ?></select></div>
        <div class="col-auto"><button class="btn btn-outline-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button> <a class="btn btn-light" href="?">Reset</a></div>
    </form>
</div></div>

<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th></th><th>Kode</th><th>Nama Barang</th><th>Kategori</th><th>Lokasi</th><th class="text-end">Jumlah</th><th class="text-end">Tersedia</th><th>Kondisi</th><th>Stok</th><th></th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="10" class="text-center text-muted py-4">Tidak ada barang yang cocok.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr class="<?= $r['is_active'] ? '' : 'table-secondary' ?>">
                <td><?php if ($r['foto']): ?><img class="item-thumb" src="<?= url('uploads/items/' . e($r['foto'])) ?>" alt=""><?php else: ?><div class="thumb-empty"><i class="bi bi-image"></i></div><?php endif; ?></td>
                <td class="text-nowrap"><a href="<?= url('items/view.php?id=' . $r['id']) ?>" class="fw-semibold text-decoration-none"><?= e($r['kode_barang']) ?></a></td>
                <td><?= e($r['nama_barang']) ?><?= $r['is_active'] ? '' : ' ' . badge('Nonaktif', 'secondary') ?>
                    <div class="small text-muted"><?= e(trim($r['merek'] . ' ' . $r['model'])) ?></div></td>
                <td><?= e($r['nama_kategori']) ?></td>
                <td><?= e($r['nama_lokasi']) ?></td>
                <td class="text-end"><?= fmt_int($r['jumlah_total']) ?> <small class="text-muted"><?= e($r['satuan']) ?></small></td>
                <td class="text-end">
                    <b><?= fmt_int($r['jumlah_tersedia']) ?></b>
                    <?php if ($r['jumlah_dipinjam'] || $r['jumlah_pemeliharaan']): ?>
                        <div class="small text-muted"><?= $r['jumlah_dipinjam'] ? 'pinjam ' . (int)$r['jumlah_dipinjam'] : '' ?><?= $r['jumlah_dipinjam'] && $r['jumlah_pemeliharaan'] ? ', ' : '' ?><?= $r['jumlah_pemeliharaan'] ? 'rawat ' . (int)$r['jumlah_pemeliharaan'] : '' ?></div>
                    <?php endif; ?>
                </td>
                <td><?= kondisi_badge($r['kondisi']) ?></td>
                <td><?= stock_badge($r) ?></td>
                <td class="table-actions">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= url('items/view.php?id=' . $r['id']) ?>" title="Detail"><i class="bi bi-eye"></i></a>
                    <a class="btn btn-sm btn-outline-primary" href="<?= url('items/form.php?id=' . $r['id']) ?>" title="Ubah"><i class="bi bi-pencil"></i></a>
                    <?php if ($r['is_active']): ?>
                        <form method="post" class="d-inline" data-confirm="Hapus &quot;<?= e($r['nama_barang']) ?>&quot;? Jika sudah memiliki riwayat, barang tidak dapat dihapus dan sebaiknya dinonaktifkan.">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                        </form>
                        <form method="post" class="d-inline" data-confirm="Nonaktifkan &quot;<?= e($r['nama_barang']) ?>&quot;? Barang tidak muncul di pilihan transaksi/peminjaman, riwayat tetap tersimpan." data-confirm-ok="Ya, nonaktifkan">
                            <?= csrf_field() ?><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button class="btn btn-sm btn-outline-warning" title="Nonaktifkan"><i class="bi bi-slash-circle"></i></button>
                        </form>
                    <?php else: ?>
                        <form method="post" class="d-inline">
                            <?= csrf_field() ?><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button class="btn btn-sm btn-outline-success" title="Aktifkan"><i class="bi bi-check-circle"></i></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
<?= render_pagination($pg) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
