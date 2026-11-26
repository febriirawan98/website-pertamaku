<?php
require_once __DIR__ . '/../includes/init.php';
require_login();

$id = (int)get('id');
$item = $id ? item_with_stock($id) : null;
if ($id && !$item) {
    flash('error', 'Barang tidak ditemukan.');
    redirect('items/index.php');
}
$isEdit = (bool)$item;

$f = $item ?: [
    'kode_barang' => '', 'nama_barang' => '', 'category_id' => '', 'merek' => '', 'model' => '', 'nomor_seri' => '',
    'satuan' => 'unit', 'kondisi' => 'baik', 'location_id' => '', 'tanggal_pengadaan' => today(), 'sumber_pengadaan' => '',
    'harga_satuan' => '0', 'stok_minimum' => '1', 'keterangan' => '', 'foto' => null,
];
$f['jumlah_awal'] = '1';
$f['jenis_awal'] = 'masuk_pengadaan';
$errors = [];

if (is_post()) {
    csrf_verify();
    foreach (['kode_barang', 'nama_barang', 'category_id', 'merek', 'model', 'nomor_seri', 'satuan', 'kondisi', 'location_id',
              'tanggal_pengadaan', 'sumber_pengadaan', 'harga_satuan', 'stok_minimum', 'keterangan', 'jumlah_awal', 'jenis_awal'] as $k) {
        $f[$k] = post($k, (string)($f[$k] ?? ''));
    }
    $harga = parse_money($f['harga_satuan']);

    if ($f['kode_barang'] === '' || !preg_match('/^[A-Za-z0-9._\/-]{2,30}$/', $f['kode_barang'])) $errors[] = 'Kode barang wajib diisi (2-30 karakter: huruf, angka, titik, strip, garis miring).';
    if ($f['nama_barang'] === '' || mb_strlen($f['nama_barang']) > 150) $errors[] = 'Nama barang wajib diisi (maks. 150 karakter).';
    if (!db_val('SELECT id FROM categories WHERE id = ?', [(int)$f['category_id']])) $errors[] = 'Pilih kategori yang valid.';
    if (!db_val('SELECT id FROM locations WHERE id = ?', [(int)$f['location_id']])) $errors[] = 'Pilih lokasi penyimpanan yang valid.';
    foreach (['merek' => 80, 'model' => 80, 'nomor_seri' => 80, 'satuan' => 20, 'sumber_pengadaan' => 100] as $k => $max) {
        if (mb_strlen($f[$k]) > $max) $errors[] = ucfirst(str_replace('_', ' ', $k)) . " maksimal $max karakter.";
    }
    if ($f['satuan'] === '') $errors[] = 'Satuan wajib diisi (mis. unit, buah, set).';
    if (!isset(kondisi_labels()[$f['kondisi']])) $errors[] = 'Kondisi tidak valid.';
    if ($f['tanggal_pengadaan'] !== '' && (!valid_date($f['tanggal_pengadaan']) || $f['tanggal_pengadaan'] > today())) $errors[] = 'Tanggal pengadaan tidak valid atau melewati hari ini.';
    if ($harga === null) $errors[] = 'Harga satuan harus berupa angka (mis. 1500000).';
    if (!preg_match('/^\d{1,6}$/', $f['stok_minimum'])) $errors[] = 'Stok minimum harus bilangan bulat 0 atau lebih.';
    if (!$isEdit) {
        if (!preg_match('/^\d{1,7}$/', $f['jumlah_awal'])) $errors[] = 'Jumlah awal harus bilangan bulat 0 atau lebih.';
        if (!in_array($f['jenis_awal'], ['masuk_pengadaan', 'masuk_hibah'], true)) $errors[] = 'Jenis penerimaan awal tidak valid.';
    }
    if (!$errors && db_val('SELECT id FROM items WHERE kode_barang = ? AND id <> ?', [$f['kode_barang'], $id])) {
        $errors[] = 'Kode barang "' . $f['kode_barang'] . '" sudah digunakan barang lain.';
    }

    $newPhoto = null;
    if (!$errors) {
        try {
            $newPhoto = save_item_photo($_FILES['foto'] ?? []);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        $removePhoto = $isEdit && isset($_POST['hapus_foto']);
        $foto = $isEdit ? $item['foto'] : null;
        if ($newPhoto) {
            $foto = $newPhoto;
        } elseif ($removePhoto) {
            $foto = null;
        }
        $data = [$f['kode_barang'], $f['nama_barang'], (int)$f['category_id'], $f['merek'] ?: null, $f['model'] ?: null,
                 $f['nomor_seri'] ?: null, $f['satuan'], $f['kondisi'], (int)$f['location_id'],
                 $f['tanggal_pengadaan'] ?: null, $f['sumber_pengadaan'] ?: null, $harga, (int)$f['stok_minimum'], $f['keterangan'] ?: null, $foto];
        try {
            if ($isEdit) {
                db_exec('UPDATE items SET kode_barang=?, nama_barang=?, category_id=?, merek=?, model=?, nomor_seri=?, satuan=?, kondisi=?,
                            location_id=?, tanggal_pengadaan=?, sumber_pengadaan=?, harga_satuan=?, stok_minimum=?, keterangan=?, foto=? WHERE id=?',
                        array_merge($data, [$id]));
                if (($newPhoto || $removePhoto) && $item['foto']) {
                    delete_item_photo($item['foto']);
                }
                flash('success', 'Data barang berhasil diperbarui.');
                redirect('items/view.php?id=' . $id);
            }
            $user = current_user();
            $newId = in_transaction(function () use ($data, $f, $user) {
                db_exec('INSERT INTO items (kode_barang, nama_barang, category_id, merek, model, nomor_seri, satuan, kondisi, location_id,
                            tanggal_pengadaan, sumber_pengadaan, harga_satuan, stok_minimum, keterangan, foto, jumlah_total)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)', $data);
                $nid = (int)db()->lastInsertId();
                if ((int)$f['jumlah_awal'] > 0) {   // setiap perubahan stok wajib punya catatan transaksi
                    record_stock_transaction([
                        'tanggal' => $f['tanggal_pengadaan'] ?: today(), 'jenis' => $f['jenis_awal'], 'item_id' => $nid,
                        'jumlah' => (int)$f['jumlah_awal'], 'location_id' => (int)$f['location_id'],
                        'sumber_tujuan' => $f['sumber_pengadaan'], 'keterangan' => 'Stok awal saat barang didaftarkan',
                    ], $user);
                }
                return $nid;
            });
            flash('success', 'Barang berhasil ditambahkan.');
            redirect('items/view.php?id=' . $newId);
        } catch (PDOException $e) {
            if ($newPhoto && (!$isEdit || $newPhoto !== ($item['foto'] ?? null))) {
                delete_item_photo($newPhoto);
            }
            if (is_duplicate_error($e)) {
                $errors[] = 'Kode barang sudah digunakan.';
            } else {
                throw $e;
            }
        } catch (DomainException $e) {
            delete_item_photo($newPhoto);
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Ubah Barang' : 'Tambah Barang';
$active = 'items';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1><?= e($pageTitle) ?></h1>
    <a class="btn btn-light border" href="<?= url($isEdit ? 'items/view.php?id=' . $id : 'items/index.php') ?>"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><b>Periksa kembali isian Anda:</b><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card"><div class="card-body">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label req">Kode barang</label><input class="form-control" name="kode_barang" required maxlength="30" value="<?= e($f['kode_barang']) ?>" placeholder="RPL-KOM-001"></div>
        <div class="col-md-8"><label class="form-label req">Nama barang</label><input class="form-control" name="nama_barang" required maxlength="150" value="<?= e($f['nama_barang']) ?>"></div>
        <div class="col-md-4"><label class="form-label req">Kategori</label>
            <select class="form-select" name="category_id" required><?= options(rows_to_map(all_categories(), 'id', 'nama_kategori'), $f['category_id'], '- Pilih kategori -') ?></select></div>
        <div class="col-md-4"><label class="form-label req">Lokasi penyimpanan</label>
            <select class="form-select" name="location_id" required><?= options(rows_to_map(all_locations(), 'id', 'nama_lokasi'), $f['location_id'], '- Pilih lokasi -') ?></select></div>
        <div class="col-md-4"><label class="form-label req">Kondisi</label>
            <select class="form-select" name="kondisi" required><?= options(kondisi_labels(), $f['kondisi']) ?></select></div>
        <div class="col-md-4"><label class="form-label">Merek</label><input class="form-control" name="merek" maxlength="80" value="<?= e($f['merek']) ?>"></div>
        <div class="col-md-4"><label class="form-label">Model / tipe</label><input class="form-control" name="model" maxlength="80" value="<?= e($f['model']) ?>"></div>
        <div class="col-md-4"><label class="form-label">Nomor seri</label><input class="form-control" name="nomor_seri" maxlength="80" value="<?= e($f['nomor_seri']) ?>"></div>

        <?php if ($isEdit): ?>
            <div class="col-md-4"><label class="form-label">Jumlah barang (fisik)</label>
                <input class="form-control" value="<?= (int)$item['jumlah_total'] ?>" disabled>
                <div class="form-text">Diubah melalui <a href="<?= url('transactions/create.php?item_id=' . $id) ?>">Transaksi Stok</a> agar tercatat.</div></div>
        <?php else: ?>
            <div class="col-md-2"><label class="form-label req">Jumlah awal</label><input class="form-control" type="number" min="0" name="jumlah_awal" required value="<?= e($f['jumlah_awal']) ?>"></div>
            <div class="col-md-2"><label class="form-label">Penerimaan</label><select class="form-select" name="jenis_awal"><?= options(['masuk_pengadaan' => 'Pengadaan', 'masuk_hibah' => 'Hibah/Bantuan'], $f['jenis_awal']) ?></select></div>
        <?php endif; ?>
        <div class="col-md-2"><label class="form-label req">Satuan</label><input class="form-control" name="satuan" required maxlength="20" value="<?= e($f['satuan']) ?>"></div>
        <div class="col-md-2"><label class="form-label">Stok minimum</label><input class="form-control" type="number" min="0" name="stok_minimum" value="<?= e($f['stok_minimum']) ?>"></div>
        <div class="col-md-2"><label class="form-label">Harga satuan (Rp)</label><input class="form-control" name="harga_satuan" inputmode="decimal" value="<?= e($f['harga_satuan']) ?>"></div>

        <div class="col-md-4"><label class="form-label">Tanggal pengadaan</label><input class="form-control" type="date" name="tanggal_pengadaan" max="<?= today() ?>" value="<?= e($f['tanggal_pengadaan']) ?>"></div>
        <div class="col-md-8"><label class="form-label">Sumber pengadaan</label><input class="form-control" name="sumber_pengadaan" maxlength="100" value="<?= e($f['sumber_pengadaan']) ?>" placeholder="Dana BOS, hibah, dsb."></div>
        <div class="col-12"><label class="form-label">Keterangan</label><textarea class="form-control" name="keterangan" rows="2"><?= e($f['keterangan']) ?></textarea></div>
        <div class="col-md-6">
            <label class="form-label">Foto barang (JPG/PNG/WEBP, maks. 2 MB)</label>
            <input class="form-control" type="file" name="foto" accept="image/jpeg,image/png,image/webp">
            <?php if ($isEdit && $item['foto']): ?>
                <div class="d-flex align-items-center gap-3 mt-2">
                    <img class="item-thumb" style="width:64px;height:64px" src="<?= url('uploads/items/' . e($item['foto'])) ?>" alt="">
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="hapus_foto" id="hapusFoto"><label class="form-check-label" for="hapusFoto">Hapus foto saat ini</label></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
        <a class="btn btn-light border" href="<?= url('items/index.php') ?>">Batal</a>
    </div>
</div></form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
