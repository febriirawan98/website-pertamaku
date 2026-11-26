<?php
require_once __DIR__ . '/../includes/init.php';
require_login();

$id = (int)get('id');
$loan = $id ? db_row('SELECT l.*, i.kode_barang, i.nama_barang, i.satuan FROM loans l JOIN items i ON i.id = l.item_id WHERE l.id = ?', [$id]) : null;
if (!$loan) {
    flash('error', 'Data peminjaman tidak ditemukan.');
    redirect('loans/index.php');
}
$here = 'loans/view.php?id=' . $id;

if (is_post()) {
    csrf_verify();
    $action = post('action');
    if ($action === 'return') {
        $qty = post('jumlah');
        $tgl = post('tanggal');
        $kond = post('kondisi');
        $note = post('catatan');
        if (!preg_match('/^[1-9]\d{0,5}$/', $qty)) {
            flash('error', 'Jumlah pengembalian harus bilangan bulat positif.');
        } elseif (!valid_date($tgl) || $tgl > today()) {
            flash('error', 'Tanggal pengembalian tidak valid atau melewati hari ini.');
        } elseif (!isset(kondisi_labels()[$kond])) {
            flash('error', 'Pilih kondisi barang setelah dikembalikan.');
        } else {
            try {
                $res = return_loan($id, (int)$qty, $tgl, $kond, $note);
                flash('success', 'Pengembalian ' . (int)$qty . ' ' . $loan['satuan'] . ' dicatat. ' . ($res['selesai'] ? 'Peminjaman selesai.' : 'Masih ada sisa yang belum kembali.'));
                if ($kond !== 'baik') {
                    flash('warning', 'Barang kembali dalam kondisi ' . kondisi_labels()[$kond] . '. Silakan catat pemeliharaan/perbaikannya.');
                    redirect('maintenance/form.php?' . http_build_query([
                        'item_id' => $loan['item_id'], 'jumlah' => (int)$qty, 'jenis' => 'perbaikan',
                        'deskripsi' => 'Rusak setelah dipinjam (' . $loan['no_peminjaman'] . ' - ' . $loan['nama_peminjam'] . ')',
                    ]));
                }
            } catch (DomainException $e) {
                flash('error', $e->getMessage());
            }
        }
    } elseif ($action === 'extend') {
        $tgl = post('tanggal_rencana_kembali');
        if ($loan['status'] === 'dikembalikan') {
            flash('error', 'Peminjaman sudah selesai.');
        } elseif (!valid_date($tgl) || $tgl < $loan['tanggal_pinjam']) {
            flash('error', 'Tanggal rencana kembali tidak valid.');
        } else {
            db_exec('UPDATE loans SET tanggal_rencana_kembali = ?, keterangan = ? WHERE id = ?', [$tgl, post('keterangan') ?: null, $id]);
            flash('success', 'Rencana pengembalian diperbarui.');
        }
    } elseif ($action === 'delete') {
        if (!is_admin()) {
            flash('error', 'Hanya administrator yang dapat menghapus catatan peminjaman.');
        } elseif ((int)$loan['jumlah_dikembalikan'] > 0) {
            flash('error', 'Peminjaman yang sudah memiliki pengembalian tidak dapat dihapus (riwayat dipertahankan).');
        } else {
            db_exec('DELETE FROM loans WHERE id = ?', [$id]);
            flash('success', 'Catatan peminjaman (salah input) dihapus.');
            redirect('loans/index.php');
        }
    }
    redirect($here);
}

$sisa = (int)$loan['jumlah'] - (int)$loan['jumlah_dikembalikan'];
$late = loan_effective_status($loan) === 'terlambat';
$pageTitle = 'Detail Peminjaman';
$active = 'loans';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1><?= e($loan['no_peminjaman']) ?> <?= loan_status_badge($loan) ?></h1>
    <a class="btn btn-light border" href="<?= url('loans/index.php') ?>"><i class="bi bi-arrow-left me-1"></i>Daftar</a>
</div>

<div class="row g-3">
    <div class="col-lg-7"><div class="card h-100"><div class="card-header">Informasi Peminjaman</div><div class="card-body">
        <dl class="row dl-grid mb-0">
            <dt class="col-sm-4">Peminjam</dt><dd class="col-sm-8"><?= e($loan['nama_peminjam']) ?> <span class="text-muted">(<?= e(jenis_peminjam_labels()[$loan['jenis_peminjam']]) ?>)</span></dd>
            <dt class="col-sm-4">NIS / NIP</dt><dd class="col-sm-8"><?= e($loan['nomor_induk']) ?: '-' ?></dd>
            <dt class="col-sm-4">Kelas / bagian</dt><dd class="col-sm-8"><?= e($loan['kelas_bagian']) ?: '-' ?></dd>
            <dt class="col-sm-4">Barang</dt><dd class="col-sm-8"><a href="<?= url('items/view.php?id=' . $loan['item_id']) ?>"><?= e($loan['nama_barang']) ?></a> <span class="text-muted">(<?= e($loan['kode_barang']) ?>)</span></dd>
            <dt class="col-sm-4">Jumlah</dt><dd class="col-sm-8"><?= (int)$loan['jumlah'] ?> <?= e($loan['satuan']) ?> &middot; kembali <?= (int)$loan['jumlah_dikembalikan'] ?> &middot; sisa <b><?= $sisa ?></b></dd>
            <dt class="col-sm-4">Tanggal pinjam</dt><dd class="col-sm-8"><?= e(fmt_date($loan['tanggal_pinjam'])) ?></dd>
            <dt class="col-sm-4">Rencana kembali</dt><dd class="col-sm-8"><?= e(fmt_date($loan['tanggal_rencana_kembali'])) ?>
                <?php if ($late): ?><span class="text-danger small">(terlambat <?= (int)((strtotime(today()) - strtotime($loan['tanggal_rencana_kembali'])) / 86400) ?> hari)</span><?php endif; ?></dd>
            <dt class="col-sm-4">Kembali (terakhir)</dt><dd class="col-sm-8"><?= e(fmt_date($loan['tanggal_kembali_aktual'])) ?><?= $loan['kondisi_kembali'] ? ' &middot; ' . kondisi_badge($loan['kondisi_kembali']) : '' ?></dd>
            <dt class="col-sm-4">Petugas</dt><dd class="col-sm-8"><?= e($loan['petugas']) ?></dd>
            <dt class="col-sm-4">Keterangan</dt><dd class="col-sm-8"><?= nl2br(e($loan['keterangan'])) ?: '-' ?></dd>
            <dt class="col-sm-4">Riwayat pengembalian</dt><dd class="col-sm-8"><?= nl2br(e($loan['catatan_kembali'])) ?: '-' ?></dd>
        </dl>
    </div></div></div>

    <div class="col-lg-5">
        <?php if ($sisa > 0): ?>
        <div class="card mb-3"><div class="card-header">Catat Pengembalian</div><div class="card-body">
            <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="return">
                <div class="row g-2">
                    <div class="col-6"><label class="form-label req">Jumlah kembali</label><input class="form-control" type="number" name="jumlah" min="1" max="<?= $sisa ?>" value="<?= $sisa ?>" required></div>
                    <div class="col-6"><label class="form-label req">Tanggal kembali</label><input class="form-control" type="date" name="tanggal" min="<?= e($loan['tanggal_pinjam']) ?>" max="<?= today() ?>" value="<?= today() ?>" required></div>
                    <div class="col-12"><label class="form-label req">Kondisi setelah dikembalikan</label><select class="form-select" name="kondisi"><?= options(kondisi_labels(), 'baik') ?></select></div>
                    <div class="col-12"><label class="form-label">Catatan</label><input class="form-control" name="catatan" maxlength="200"></div>
                    <div class="col-12"><button class="btn btn-success w-100"><i class="bi bi-box-arrow-in-down-left me-1"></i>Simpan Pengembalian</button></div>
                </div>
            </form>
        </div></div>
        <div class="card mb-3"><div class="card-header">Perpanjang / Ubah Keterangan</div><div class="card-body">
            <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="extend">
                <div class="mb-2"><label class="form-label">Rencana tanggal kembali</label><input class="form-control" type="date" name="tanggal_rencana_kembali" value="<?= e($loan['tanggal_rencana_kembali']) ?>" required></div>
                <div class="mb-2"><label class="form-label">Keterangan</label><input class="form-control" name="keterangan" value="<?= e($loan['keterangan']) ?>"></div>
                <button class="btn btn-outline-primary btn-sm">Simpan</button>
            </form>
        </div></div>
        <?php endif; ?>
        <?php if (is_admin() && (int)$loan['jumlah_dikembalikan'] === 0): ?>
        <form method="post" data-confirm="Hapus catatan peminjaman ini? Gunakan hanya untuk salah input.">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete">
            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Hapus catatan (salah input)</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
