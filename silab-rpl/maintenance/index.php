<?php
require_once __DIR__ . '/../includes/init.php';
require_login();

if (is_post()) {
    csrf_verify();
    if (post('action') === 'delete') {
        $id = (int)post('id');
        $m = db_row('SELECT status FROM maintenance WHERE id = ?', [$id]);
        if (!$m) {
            flash('error', 'Catatan tidak ditemukan.');
        } elseif ($m['status'] !== 'menunggu') {
            flash('error', 'Hanya catatan berstatus "Menunggu" (belum diproses) yang dapat dihapus. Catatan lain adalah riwayat pemeliharaan.');
        } else {
            db_exec('DELETE FROM maintenance WHERE id = ?', [$id]);
            flash('success', 'Catatan pemeliharaan dihapus.');
        }
    }
    redirect('maintenance/index.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

$q = get('q');
$status = get('status');
$jenis = get('jenis');
$dari = get('dari');
$sampai = get('sampai');

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(i.nama_barang LIKE ? OR i.kode_barang LIKE ? OR m.teknisi LIKE ? OR m.deskripsi_kerusakan LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
if ($status === 'aktif') { $where[] = "m.status IN ('menunggu','dalam_proses')"; }
elseif (isset(maint_status_labels()[$status])) { $where[] = 'm.status = ?'; $params[] = $status; }
else { $status = ''; }
if (isset(maint_jenis_labels()[$jenis])) { $where[] = 'm.jenis = ?'; $params[] = $jenis; }
if (valid_date($dari)) { $where[] = 'm.tanggal_periksa >= ?'; $params[] = $dari; }
if (valid_date($sampai)) { $where[] = 'm.tanggal_periksa <= ?'; $params[] = $sampai; }
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$from = 'FROM maintenance m JOIN items i ON i.id = m.item_id';
$total = (int)db_val("SELECT COUNT(*) $from $w", $params);
$pg = paginate($total);
$rows = db_all("SELECT m.*, i.kode_barang, i.nama_barang, i.satuan $from $w ORDER BY m.status IN ('selesai','tidak_dapat_diperbaiki'), m.tanggal_periksa DESC, m.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
$cntAktif = (int)db_val("SELECT COUNT(*) FROM maintenance WHERE status IN ('menunggu','dalam_proses')");

$pageTitle = 'Pemeliharaan';
$active = 'maintenance';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Pemeliharaan &amp; Kerusakan Barang</h1>
    <a class="btn btn-primary" href="<?= url('maintenance/form.php') ?>"><i class="bi bi-plus-lg me-1"></i>Catat Pemeliharaan</a>
</div>

<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link py-1 <?= $status === '' ? 'active' : '' ?>" href="?">Semua</a></li>
    <li class="nav-item"><a class="nav-link py-1 <?= $status === 'aktif' ? 'active' : '' ?>" href="?status=aktif">Belum Selesai <span class="badge text-bg-light"><?= $cntAktif ?></span></a></li>
    <li class="nav-item"><a class="nav-link py-1 <?= $status === 'selesai' ? 'active' : '' ?>" href="?status=selesai">Selesai</a></li>
</ul>

<div class="card mb-3"><div class="card-body py-3">
    <form class="row g-2 filter-bar" method="get">
        <div class="col-12 col-lg-3"><input class="form-control" name="q" placeholder="Cari barang, teknisi, kerusakan..." value="<?= e($q) ?>"></div>
        <div class="col-6 col-lg-2"><select class="form-select" name="status"><?= options(['aktif' => 'Belum selesai'] + maint_status_labels(), $status, 'Semua status') ?></select></div>
        <div class="col-6 col-lg-2"><select class="form-select" name="jenis"><?= options(maint_jenis_labels(), $jenis, 'Semua jenis') ?></select></div>
        <div class="col-6 col-lg-2"><input class="form-control" type="date" name="dari" value="<?= e($dari) ?>"></div>
        <div class="col-6 col-lg-2"><input class="form-control" type="date" name="sampai" value="<?= e($sampai) ?>"></div>
        <div class="col-auto"><button class="btn btn-outline-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button> <a class="btn btn-light" href="?">Reset</a></div>
    </form>
</div></div>

<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Tanggal</th><th>Barang</th><th>Jenis</th><th class="text-end">Unit</th><th>Kerusakan / Tindakan</th><th>Teknisi</th><th class="text-end">Biaya</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="9" class="text-center text-muted py-4">Belum ada catatan pemeliharaan.</td></tr><?php endif; ?>
        <?php foreach ($rows as $m): ?>
            <tr>
                <td class="text-nowrap"><?= e(fmt_date($m['tanggal_periksa'])) ?><?php if ($m['tanggal_selesai']): ?><div class="small text-muted">selesai <?= e(fmt_date($m['tanggal_selesai'])) ?></div><?php endif; ?></td>
                <td><a class="text-decoration-none" href="<?= url('items/view.php?id=' . $m['item_id']) ?>"><?= e($m['nama_barang']) ?></a><div class="small text-muted"><?= e($m['kode_barang']) ?></div></td>
                <td><?= e(maint_jenis_labels()[$m['jenis']]) ?></td>
                <td class="text-end"><?= (int)$m['jumlah'] ?> <small class="text-muted"><?= e($m['satuan']) ?></small></td>
                <td style="max-width:280px"><?= e($m['deskripsi_kerusakan']) ?><div class="small text-muted"><?= e($m['tindakan']) ?></div></td>
                <td><?= e($m['teknisi']) ?></td>
                <td class="text-end text-nowrap"><?= e(fmt_rp($m['biaya'])) ?></td>
                <td><?= maint_status_badge($m['status']) ?></td>
                <td class="table-actions">
                    <a class="btn btn-sm btn-outline-primary" href="<?= url('maintenance/form.php?id=' . $m['id']) ?>" title="Ubah / perbarui status"><i class="bi bi-pencil"></i></a>
                    <?php if ($m['status'] === 'menunggu'): ?>
                    <form method="post" class="d-inline" data-confirm="Hapus catatan pemeliharaan ini?">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $m['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
<?= render_pagination($pg) ?>
<div class="small text-muted mt-2"><i class="bi bi-info-circle me-1"></i>Unit pada catatan <b>perawatan/perbaikan</b> berstatus <b>Menunggu</b> atau <b>Dalam Proses</b> tidak dihitung sebagai barang tersedia untuk dipinjam.</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
