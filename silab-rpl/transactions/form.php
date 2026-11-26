<?php
require __DIR__ . '/../includes/bootstrap.php';

$items = db_all('SELECT i.id, i.code, i.name, i.unit, i.location_id, s.stock_total, s.qty_available
                 FROM items i JOIN v_item_stock s ON s.id = i.id WHERE i.is_active = 1 ORDER BY i.code');
$locs = db_all('SELECT id, name FROM locations ORDER BY name');
$errors = [];

$f = [
    'trx_date' => date('Y-m-d'), 'trx_type' => 'masuk_pengadaan', 'item_id' => get('item_id'),
    'qty' => '', 'location_id' => '', 'party' => '', 'notes' => '',
];
if (is_post()) {
    csrf_verify();
    foreach ($f as $k => $_) {
        $f[$k] = post($k);
    }
    $type = $f['trx_type'];
    $itemId = (int) $f['item_id'];

    if (!valid_date($f['trx_date'])) { $errors[] = 'Tanggal transaksi tidak valid.'; }
    elseif ($f['trx_date'] > date('Y-m-d')) { $errors[] = 'Tanggal transaksi tidak boleh di masa depan.'; }
    if (!isset(TRX_TYPES[$type])) { $errors[] = 'Jenis transaksi tidak valid.'; }
    if (!db_val('SELECT id FROM items WHERE id = ? AND is_active = 1', [$itemId])) { $errors[] = 'Pilih barang yang valid dan aktif.'; }
    $qty = to_int($f['qty']);
    $isAdjust = $type === 'penyesuaian';
    if ($qty === null || (!$isAdjust && $qty < 1)) {
        $errors[] = $isAdjust ? 'Jumlah hasil pemeriksaan fisik harus bilangan bulat (0 atau lebih).' : 'Jumlah unit harus bilangan bulat positif.';
    }
    if ($f['location_id'] !== '' && !db_val('SELECT id FROM locations WHERE id = ?', [(int) $f['location_id']])) { $errors[] = 'Lokasi tidak valid.'; }
    if (mb_strlen($f['party']) > 150) { $errors[] = 'Sumber/tujuan maksimal 150 karakter.'; }
    if (!$isAdjust && $f['party'] === '') { $errors[] = 'Sumber / tujuan wajib diisi.'; }
    if ($isAdjust && $f['notes'] === '') { $errors[] = 'Alasan penyesuaian wajib diisi pada kolom keterangan.'; }

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            if ($isAdjust) {
                $cur = item_stock($pdo, $itemId, true);
                $delta = $qty - (int) $cur['stock_total'];
                if ($delta === 0) {
                    throw new RuntimeException('Hasil pemeriksaan sama dengan stok tercatat (' . $cur['stock_total'] . '). Tidak ada penyesuaian yang perlu dicatat.');
                }
            } else {
                $delta = str_starts_with($type, 'masuk') ? $qty : -$qty;
            }
            $no = record_stock_change($pdo, $itemId, $delta, $type, [
                'date' => $f['trx_date'], 'location_id' => (int) $f['location_id'], 'party' => $f['party'], 'notes' => $f['notes'],
            ]);
            $pdo->commit();
            flash('success', 'Transaksi ' . $no . ' berhasil disimpan dan stok diperbarui.');
            redirect('transactions/');
        } catch (RuntimeException $ex) {
            $pdo->rollBack();
            $errors[] = $ex->getMessage();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('[SILAB-RPL] ' . $ex->getMessage());
            $errors[] = 'Transaksi gagal disimpan. Silakan coba lagi.';
        }
    }
}

$pageTitle = 'Transaksi Stok Baru';
$active = 'transactions';
include __DIR__ . '/../includes/header.php';
$itemMeta = [];
foreach ($items as $it) {
    $itemMeta[$it['id']] = ['loc' => $it['location_id'], 'stock' => (int) $it['stock_total'], 'avail' => (int) $it['qty_available'], 'unit' => $it['unit']];
}
?>
<div class="row justify-content-center"><div class="col-xl-8">
<form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($er) ?></div><?php endforeach; ?>
    <div class="card">
        <div class="card-header">Data Transaksi</div>
        <div class="card-body row g-3">
            <div class="col-md-6"><label class="form-label">Nomor transaksi</label><input class="form-control" value="Dibuat otomatis saat disimpan" disabled></div>
            <div class="col-md-6"><label class="form-label required">Tanggal transaksi</label>
                <input type="date" class="form-control" name="trx_date" max="<?= date('Y-m-d') ?>" required value="<?= e($f['trx_date']) ?>"></div>
            <div class="col-md-6"><label class="form-label required">Jenis transaksi</label>
                <select class="form-select" name="trx_type" id="trxType" required><?= options_map(TRX_TYPES, $f['trx_type']) ?></select></div>
            <div class="col-md-6"><label class="form-label required">Barang</label>
                <select class="form-select" name="item_id" id="itemSel" required>
                    <option value="">- Pilih barang -</option>
                    <?php foreach ($items as $it): ?>
                        <option value="<?= (int) $it['id'] ?>"<?= sel($f['item_id'], $it['id']) ?>><?= e($it['code'] . ' - ' . $it['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text" id="stockInfo">&nbsp;</div></div>
            <div class="col-md-6"><label class="form-label required" id="qtyLabel">Jumlah unit</label>
                <input type="number" min="0" step="1" class="form-control" name="qty" id="qty" required value="<?= e($f['qty']) ?>">
                <div class="form-text" id="qtyHelp"></div></div>
            <div class="col-md-6"><label class="form-label">Lokasi terkait</label>
                <select class="form-select" name="location_id" id="locSel"><?= options($locs, 'id', 'name', $f['location_id'], '- Lokasi barang saat ini -') ?></select></div>
            <div class="col-12"><label class="form-label required" id="partyLabel">Sumber / tujuan</label>
                <input class="form-control" name="party" id="party" maxlength="150" value="<?= e($f['party']) ?>" placeholder="Contoh: CV Mitra Teknologi / Dinas Pendidikan"></div>
            <div class="col-12"><label class="form-label" id="notesLabel">Keterangan</label>
                <textarea class="form-control" name="notes" id="notes" rows="2"><?= e($f['notes']) ?></textarea></div>
            <div class="col-12"><div class="small text-muted"><i class="bi bi-person me-1"></i>Petugas: <strong><?= e(current_user()['full_name']) ?></strong></div></div>
        </div>
    </div>
    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan Transaksi</button>
        <a class="btn btn-light" href="<?= e(url('transactions/')) ?>">Batal</a>
    </div>
</form>
</div></div>
<?php
$extraScripts = '<script>window.ITEMS = ' . json_encode($itemMeta, JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>
<script>
(function(){
  var t=document.getElementById("trxType"), it=document.getElementById("itemSel"), info=document.getElementById("stockInfo"),
      ql=document.getElementById("qtyLabel"), qh=document.getElementById("qtyHelp"), q=document.getElementById("qty"),
      pl=document.getElementById("partyLabel"), p=document.getElementById("party"), nl=document.getElementById("notesLabel"), n=document.getElementById("notes"),
      loc=document.getElementById("locSel");
  function upd(){
    var adj=t.value==="penyesuaian", masuk=t.value.indexOf("masuk")===0, m=window.ITEMS[it.value];
    ql.textContent = adj ? "Jumlah fisik hasil pemeriksaan" : (masuk ? "Jumlah masuk" : "Jumlah keluar");
    q.min = adj ? 0 : 1;
    qh.textContent = adj ? "Isi stok sebenarnya hasil hitung fisik; sistem menghitung selisihnya." : (masuk ? "" : "Tidak boleh melebihi jumlah tersedia.");
    pl.textContent = adj ? "Sumber / tujuan (opsional)" : (masuk ? "Sumber (pemasok/pemberi)" : "Tujuan");
    pl.className = "form-label" + (adj ? "" : " required"); p.required = !adj;
    nl.textContent = adj ? "Alasan penyesuaian" : "Keterangan"; nl.className = "form-label" + (adj ? " required" : ""); n.required = adj;
    if (m) { info.textContent = "Stok fisik: " + m.stock + " " + m.unit + " | Tersedia: " + Math.max(0,m.avail) + " " + m.unit; }
    else { info.innerHTML = "&nbsp;"; }
  }
  it.addEventListener("change", function(){ var m=window.ITEMS[it.value]; if(m && !loc.value){ /* biarkan kosong = lokasi barang */ } upd(); });
  t.addEventListener("change", upd); upd();
})();
</script>';
include __DIR__ . '/../includes/footer.php';
