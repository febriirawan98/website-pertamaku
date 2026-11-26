<?php
require_once __DIR__ . '/../includes/init.php';
require_login();

$f = [
    'nama_peminjam' => '', 'jenis_peminjam' => 'siswa', 'nomor_induk' => '', 'kelas_bagian' => '',
    'item_id' => (string)(int)get('item_id') ?: '', 'jumlah' => '1', 'tanggal_pinjam' => today(),
    'tanggal_rencana_kembali' => date('Y-m-d', strtotime('+7 days')), 'keterangan' => '',
];
$errors = [];

if (is_post()) {
    csrf_verify();
    foreach ($f as $k => $v) {
        $f[$k] = post($k, $v);
    }
    if ($f['nama_peminjam'] === '' || mb_strlen($f['nama_peminjam']) > 100) $errors[] = 'Nama peminjam wajib diisi (maks. 100 karakter).';
    if (!isset(jenis_peminjam_labels()[$f['jenis_peminjam']])) $errors[] = 'Jenis peminjam tidak valid.';
    if (mb_strlen($f['nomor_induk']) > 30) $errors[] = 'NIS/NIP maksimal 30 karakter.';
    if (mb_strlen($f['kelas_bagian']) > 60) $errors[] = 'Kelas/bagian maksimal 60 karakter.';
    if (!(int)$f['item_id']) $errors[] = 'Pilih barang yang dipinjam.';
    if (!preg_match('/^[1-9]\d{0,5}$/', $f['jumlah'])) $errors[] = 'Jumlah harus bilangan bulat positif.';
    if (!valid_date($f['tanggal_pinjam']) || $f['tanggal_pinjam'] > today()) $errors[] = 'Tanggal pinjam tidak valid atau melewati hari ini.';
    if (!valid_date($f['tanggal_rencana_kembali'])) $errors[] = 'Rencana tanggal kembali tidak valid.';
    elseif (valid_date($f['tanggal_pinjam']) && $f['tanggal_rencana_kembali'] < $f['tanggal_pinjam']) $errors[] = 'Rencana tanggal kembali tidak boleh sebelum tanggal pinjam.';

    if (!$errors) {
        try {
            $no = create_loan([
                'nama_peminjam' => $f['nama_peminjam'], 'nomor_induk' => $f['nomor_induk'], 'jenis_peminjam' => $f['jenis_peminjam'],
                'kelas_bagian' => $f['kelas_bagian'], 'item_id' => (int)$f['item_id'], 'jumlah' => (int)$f['jumlah'],
                'tanggal_pinjam' => $f['tanggal_pinjam'], 'tanggal_rencana_kembali' => $f['tanggal_rencana_kembali'], 'keterangan' => $f['keterangan'],
            ], current_user());
            flash('success', "Peminjaman $no berhasil dicatat.");
            redirect('loans/index.php?status=aktif');
        } catch (DomainException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// Hanya barang aktif, tidak rusak berat, dan masih tersedia yang bisa dipilih
$items = db_all('SELECT i.id, i.kode_barang, i.nama_barang, i.satuan, s.jumlah_total, s.jumlah_dipinjam, s.jumlah_pemeliharaan, s.jumlah_tersedia
                   FROM items i JOIN v_item_stock s ON s.item_id = i.id
                  WHERE i.is_active = 1 AND i.kondisi <> \'rusak_berat\' AND (s.jumlah_tersedia > 0 OR i.id = ?) ORDER BY i.kode_barang', [(int)$f['item_id']]);

$pageTitle = 'Peminjaman Baru';
$active = 'loans';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Peminjaman Baru</h1>
    <a class="btn btn-light border" href="<?= url('loans/index.php') ?>"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>
<?php if ($errors): ?>
    <div class="alert alert-danger"><b>Peminjaman belum disimpan:</b><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card" style="max-width:860px"><div class="card-body">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label req">Nama peminjam</label><input class="form-control" name="nama_peminjam" required maxlength="100" value="<?= e($f['nama_peminjam']) ?>"></div>
        <div class="col-md-3"><label class="form-label req">Jenis peminjam</label><select class="form-select" name="jenis_peminjam"><?= options(jenis_peminjam_labels(), $f['jenis_peminjam']) ?></select></div>
        <div class="col-md-3"><label class="form-label">NIS / NIP</label><input class="form-control" name="nomor_induk" maxlength="30" value="<?= e($f['nomor_induk']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Kelas / bagian</label><input class="form-control" name="kelas_bagian" maxlength="60" value="<?= e($f['kelas_bagian']) ?>" placeholder="XII RPL 1 / Guru Produktif"></div>
        <div class="col-md-6"><label class="form-label">Petugas yang melayani</label><input class="form-control" value="<?= e(current_user()['nama']) ?>" disabled></div>

        <div class="col-md-8"><label class="form-label req">Barang</label>
            <select class="form-select" name="item_id" required data-stock-info>
                <option value="">- Pilih barang yang tersedia -</option>
                <?php foreach ($items as $it): ?>
                    <option value="<?= $it['id'] ?>" <?= (string)$it['id'] === (string)$f['item_id'] ? 'selected' : '' ?>
                        data-total="<?= (int)$it['jumlah_total'] ?>" data-borrowed="<?= (int)$it['jumlah_dipinjam'] ?>" data-maint="<?= (int)$it['jumlah_pemeliharaan'] ?>"
                        data-avail="<?= (int)$it['jumlah_tersedia'] ?>" data-unit="<?= e($it['satuan']) ?>"><?= e($it['kode_barang'] . ' - ' . $it['nama_barang'] . ' (tersedia ' . $it['jumlah_tersedia'] . ')') ?></option>
                <?php endforeach; ?>
            </select>
            <div id="stockInfo" class="form-text d-none"></div></div>
        <div class="col-md-4"><label class="form-label req">Jumlah</label><input class="form-control" type="number" min="1" name="jumlah" required data-max-from-stock="avail" value="<?= e($f['jumlah']) ?>"></div>
        <div class="col-md-6"><label class="form-label req">Tanggal pinjam</label><input class="form-control" type="date" name="tanggal_pinjam" required max="<?= today() ?>" value="<?= e($f['tanggal_pinjam']) ?>"></div>
        <div class="col-md-6"><label class="form-label req">Rencana tanggal kembali</label><input class="form-control" type="date" name="tanggal_rencana_kembali" required value="<?= e($f['tanggal_rencana_kembali']) ?>"></div>
        <div class="col-12"><label class="form-label">Keterangan / keperluan</label><textarea class="form-control" name="keterangan" rows="2"><?= e($f['keterangan']) ?></textarea></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan Peminjaman</button></div>
</div></form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
