<?php
require_once __DIR__ . '/../includes/init.php';
require_login();

$id = (int)get('id');
$rec = $id ? db_row('SELECT * FROM maintenance WHERE id = ?', [$id]) : null;
if ($id && !$rec) {
    flash('error', 'Catatan pemeliharaan tidak ditemukan.');
    redirect('maintenance/index.php');
}
$isEdit = (bool)$rec;

$f = $rec ?: [
    'item_id' => (string)(int)get('item_id') ?: '', 'jumlah' => get('jumlah', '1'), 'tanggal_periksa' => today(),
    'jenis' => isset(maint_jenis_labels()[get('jenis')]) ? get('jenis') : 'pemeriksaan', 'deskripsi_kerusakan' => get('deskripsi'), 'tindakan' => '',
    'teknisi' => current_user()['nama'], 'biaya' => '0', 'status' => 'menunggu', 'tanggal_selesai' => '', 'keterangan' => '',
];
$errors = [];

if (is_post()) {
    csrf_verify();
    foreach (['item_id', 'jumlah', 'tanggal_periksa', 'jenis', 'deskripsi_kerusakan', 'tindakan', 'teknisi', 'biaya', 'status', 'tanggal_selesai', 'keterangan'] as $k) {
        $f[$k] = post($k, '');
    }
    $biaya = parse_money($f['biaya']);
    if (!(int)$f['item_id'] || !db_val('SELECT id FROM items WHERE id = ?', [(int)$f['item_id']])) $errors[] = 'Pilih barang.';
    if (!preg_match('/^[1-9]\d{0,6}$/', $f['jumlah'])) $errors[] = 'Jumlah unit harus bilangan bulat positif.';
    if (!valid_date($f['tanggal_periksa']) || $f['tanggal_periksa'] > today()) $errors[] = 'Tanggal pemeriksaan tidak valid atau melewati hari ini.';
    if (!isset(maint_jenis_labels()[$f['jenis']])) $errors[] = 'Jenis kegiatan tidak valid.';
    if (!isset(maint_status_labels()[$f['status']])) $errors[] = 'Status tidak valid.';
    if ($f['teknisi'] === '' || mb_strlen($f['teknisi']) > 100) $errors[] = 'Nama teknisi/petugas wajib diisi (maks. 100 karakter).';
    if ($biaya === null) $errors[] = 'Biaya harus berupa angka.';
    if ($f['jenis'] === 'perbaikan' && $f['deskripsi_kerusakan'] === '') $errors[] = 'Deskripsi kerusakan wajib diisi untuk perbaikan.';
    if ($f['tanggal_selesai'] !== '') {
        if (!valid_date($f['tanggal_selesai']) || $f['tanggal_selesai'] > today()) $errors[] = 'Tanggal selesai tidak valid atau melewati hari ini.';
        elseif (valid_date($f['tanggal_periksa']) && $f['tanggal_selesai'] < $f['tanggal_periksa']) $errors[] = 'Tanggal selesai tidak boleh sebelum tanggal pemeriksaan.';
    }
    if (!$errors) {
        try {
            $newId = save_maintenance($isEdit ? $id : null, [
                'item_id' => (int)$f['item_id'], 'jumlah' => (int)$f['jumlah'], 'tanggal_periksa' => $f['tanggal_periksa'], 'jenis' => $f['jenis'],
                'deskripsi_kerusakan' => $f['deskripsi_kerusakan'], 'tindakan' => $f['tindakan'], 'teknisi' => $f['teknisi'], 'biaya' => $biaya,
                'status' => $f['status'], 'tanggal_selesai' => $f['tanggal_selesai'], 'keterangan' => $f['keterangan'],
            ], current_user());
            flash('success', $isEdit ? 'Catatan pemeliharaan diperbarui.' : 'Catatan pemeliharaan disimpan.');
            if ($f['status'] === 'tidak_dapat_diperbaiki') {
                flash('info', 'Barang dinyatakan tidak dapat diperbaiki. Ubah kondisi barang menjadi Rusak Berat dan, bila disetujui, catat transaksi keluar (penghapusan).');
            }
            redirect('maintenance/index.php');
        } catch (DomainException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$items = db_all('SELECT i.id, i.kode_barang, i.nama_barang, i.jumlah_total FROM items i WHERE i.is_active = 1 OR i.id = ? ORDER BY i.kode_barang', [(int)$f['item_id']]);
$pageTitle = $isEdit ? 'Ubah Pemeliharaan' : 'Catat Pemeliharaan';
$active = 'maintenance';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1><?= e($pageTitle) ?></h1>
    <a class="btn btn-light border" href="<?= url('maintenance/index.php') ?>"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>
<?php if ($errors): ?>
    <div class="alert alert-danger"><b>Belum dapat disimpan:</b><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card" style="max-width:900px"><div class="card-body">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-8"><label class="form-label req">Barang yang diperiksa</label>
            <select class="form-select" name="item_id" required>
                <option value="">- Pilih barang -</option>
                <?php foreach ($items as $it): ?><option value="<?= $it['id'] ?>" <?= (string)$it['id'] === (string)$f['item_id'] ? 'selected' : '' ?>><?= e($it['kode_barang'] . ' - ' . $it['nama_barang'] . ' (total ' . $it['jumlah_total'] . ')') ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-4"><label class="form-label req">Jumlah unit</label><input class="form-control" type="number" min="1" name="jumlah" required value="<?= e($f['jumlah']) ?>"></div>
        <div class="col-md-4"><label class="form-label req">Tanggal pemeriksaan</label><input class="form-control" type="date" name="tanggal_periksa" required max="<?= today() ?>" value="<?= e($f['tanggal_periksa']) ?>"></div>
        <div class="col-md-4"><label class="form-label req">Jenis kegiatan</label><select class="form-select" name="jenis"><?= options(maint_jenis_labels(), $f['jenis']) ?></select></div>
        <div class="col-md-4"><label class="form-label req">Status</label><select class="form-select" name="status"><?= options(maint_status_labels(), $f['status']) ?></select></div>
        <div class="col-12"><label class="form-label">Deskripsi kerusakan</label><textarea class="form-control" name="deskripsi_kerusakan" rows="2"><?= e($f['deskripsi_kerusakan']) ?></textarea></div>
        <div class="col-12"><label class="form-label">Tindakan yang dilakukan</label><textarea class="form-control" name="tindakan" rows="2"><?= e($f['tindakan']) ?></textarea></div>
        <div class="col-md-5"><label class="form-label req">Teknisi / petugas</label><input class="form-control" name="teknisi" required maxlength="100" value="<?= e($f['teknisi']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Biaya (Rp)</label><input class="form-control" name="biaya" inputmode="decimal" value="<?= e($f['biaya']) ?>"></div>
        <div class="col-md-4"><label class="form-label">Tanggal selesai</label><input class="form-control" type="date" name="tanggal_selesai" max="<?= today() ?>" value="<?= e($f['tanggal_selesai']) ?>"><div class="form-text">Kosong = hari ini bila status selesai.</div></div>
        <div class="col-12"><label class="form-label">Keterangan</label><input class="form-control" name="keterangan" value="<?= e($f['keterangan']) ?>"></div>
    </div>
    <div class="alert alert-light border mt-3 mb-0 small"><i class="bi bi-info-circle me-1"></i>Perawatan/perbaikan berstatus Menunggu atau Dalam Proses menahan unit tersebut sehingga tidak tersedia untuk dipinjam. Ubah status menjadi Selesai/Tidak dapat diperbaiki untuk melepasnya.</div>
    <div class="mt-3"><button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button></div>
</div></form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
