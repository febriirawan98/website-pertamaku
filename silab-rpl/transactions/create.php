<?php
require_once __DIR__ . '/../includes/init.php';
require_login();

$f = [
    'tanggal' => today(), 'jenis' => 'masuk_pengadaan', 'item_id' => (string)(int)get('item_id') ?: '', 'jumlah' => '1',
    'stok_baru' => '', 'location_id' => '', 'sumber_tujuan' => '', 'keterangan' => '',
];
$errors = [];

if (is_post()) {
    csrf_verify();
    foreach ($f as $k => $v) {
        $f[$k] = post($k, $v);
    }
    if (!valid_date($f['tanggal']) || $f['tanggal'] > today()) $errors[] = 'Tanggal transaksi tidak valid atau melewati hari ini.';
    if (!isset(jenis_trx_labels()[$f['jenis']])) $errors[] = 'Jenis transaksi tidak valid.';
    if (!(int)$f['item_id']) $errors[] = 'Pilih barang.';
    if ($f['jenis'] === 'penyesuaian') {
        if (!preg_match('/^\d{1,7}$/', $f['stok_baru'])) $errors[] = 'Stok fisik hasil pemeriksaan harus bilangan bulat 0 atau lebih.';
        if ($f['keterangan'] === '') $errors[] = 'Alasan penyesuaian wajib diisi pada kolom keterangan.';
    } elseif (!preg_match('/^[1-9]\d{0,6}$/', $f['jumlah'])) {
        $errors[] = 'Jumlah harus bilangan bulat positif.';
    }
    if ($f['location_id'] !== '' && !db_val('SELECT id FROM locations WHERE id = ?', [(int)$f['location_id']])) $errors[] = 'Lokasi tidak valid.';
    if (mb_strlen($f['sumber_tujuan']) > 150) $errors[] = 'Sumber/tujuan maksimal 150 karakter.';

    if (!$errors) {
        try {
            $it = item_with_stock((int)$f['item_id']);
            $no = record_stock_transaction([
                'tanggal' => $f['tanggal'], 'jenis' => $f['jenis'], 'item_id' => (int)$f['item_id'], 'jumlah' => (int)$f['jumlah'],
                'stok_baru' => (int)$f['stok_baru'], 'location_id' => $f['location_id'] !== '' ? (int)$f['location_id'] : ($it['location_id'] ?? null),
                'sumber_tujuan' => $f['sumber_tujuan'], 'keterangan' => $f['keterangan'],
            ], current_user());
            flash('success', "Transaksi $no berhasil dicatat dan stok diperbarui.");
            redirect('transactions/index.php?item_id=' . (int)$f['item_id']);
        } catch (DomainException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$items = db_all('SELECT i.id, i.kode_barang, i.nama_barang, i.satuan, s.jumlah_total, s.jumlah_dipinjam, s.jumlah_pemeliharaan, s.jumlah_tersedia
                   FROM items i JOIN v_item_stock s ON s.item_id = i.id WHERE i.is_active = 1 ORDER BY i.kode_barang');

$pageTitle = 'Transaksi Baru';
$active = 'transactions';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Transaksi Stok Baru</h1>
    <a class="btn btn-light border" href="<?= url('transactions/index.php') ?>"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><b>Transaksi belum disimpan:</b><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card" style="max-width:860px"><div class="card-body">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Nomor transaksi</label><input class="form-control" value="Otomatis saat disimpan" disabled></div>
        <div class="col-md-4"><label class="form-label req">Tanggal</label><input class="form-control" type="date" name="tanggal" required max="<?= today() ?>" value="<?= e($f['tanggal']) ?>"></div>
        <div class="col-md-4"><label class="form-label">Petugas</label><input class="form-control" value="<?= e(current_user()['nama']) ?>" disabled></div>

        <div class="col-md-6"><label class="form-label req">Jenis transaksi</label>
            <select class="form-select" name="jenis" id="jenisTrx" required><?= options(jenis_trx_labels(), $f['jenis']) ?></select></div>
        <div class="col-md-6"><label class="form-label req">Barang</label>
            <select class="form-select" name="item_id" required data-stock-info>
                <option value="">- Pilih barang -</option>
                <?php foreach ($items as $it): ?>
                    <option value="<?= $it['id'] ?>" <?= (string)$it['id'] === (string)$f['item_id'] ? 'selected' : '' ?>
                        data-total="<?= (int)$it['jumlah_total'] ?>" data-borrowed="<?= (int)$it['jumlah_dipinjam'] ?>" data-maint="<?= (int)$it['jumlah_pemeliharaan'] ?>"
                        data-avail="<?= (int)$it['jumlah_tersedia'] ?>" data-unit="<?= e($it['satuan']) ?>"><?= e($it['kode_barang'] . ' - ' . $it['nama_barang']) ?></option>
                <?php endforeach; ?>
            </select>
            <div id="stockInfo" class="form-text d-none"></div></div>

        <div class="col-md-4" data-when-normal><label class="form-label req">Jumlah unit</label>
            <input class="form-control" type="number" min="1" name="jumlah" id="jumlahInput" value="<?= e($f['jumlah']) ?>"></div>
        <div class="col-md-4 d-none" data-when-adjust><label class="form-label req">Stok fisik hasil pemeriksaan</label>
            <input class="form-control" type="number" min="0" name="stok_baru" id="stokBaruInput" value="<?= e($f['stok_baru']) ?>">
            <div class="form-text">Selisih dengan stok sistem dihitung otomatis.</div></div>
        <div class="col-md-4"><label class="form-label">Lokasi terkait</label>
            <select class="form-select" name="location_id"><?= options(rows_to_map(all_locations(), 'id', 'nama_lokasi'), $f['location_id'], '- Lokasi barang saat ini -') ?></select></div>
        <div class="col-md-4"><label class="form-label">Sumber / tujuan</label>
            <input class="form-control" name="sumber_tujuan" maxlength="150" value="<?= e($f['sumber_tujuan']) ?>" placeholder="Pemasok, hibah, tujuan pindah..."></div>
        <div class="col-12"><label class="form-label" id="ketLabel">Keterangan <span class="text-danger d-none" data-when-adjust>(wajib untuk penyesuaian)</span></label>
            <textarea class="form-control" name="keterangan" rows="2"><?= e($f['keterangan']) ?></textarea></div>
    </div>
    <div class="alert alert-light border mt-3 mb-0 small"><i class="bi bi-shield-check me-1"></i>Stok diperbarui dalam satu transaksi database. Stok tidak dapat menjadi negatif dan tidak boleh lebih kecil dari unit yang sedang dipinjam atau dalam pemeliharaan.</div>
    <div class="mt-3"><button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan Transaksi</button></div>
</div></form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
