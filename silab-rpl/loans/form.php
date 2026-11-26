<?php
require __DIR__ . '/../includes/bootstrap.php';

$id = (int) get('id', post('id', '0'));
$loan = $id ? db_one('SELECT l.*, i.name AS item_name, i.code, i.unit FROM loans l JOIN items i ON i.id = l.item_id WHERE l.id = ?', [$id]) : null;
if ($id && !$loan) {
    flash('error', 'Data peminjaman tidak ditemukan.');
    redirect('loans/');
}
if ($loan && (int) $loan['qty_returned'] >= (int) $loan['qty']) {
    flash('error', 'Peminjaman yang sudah dikembalikan seluruhnya tidak dapat diubah.');
    redirect('loans/view.php?id=' . $id);
}
$isEdit = (bool) $loan;

$items = db_all('SELECT i.id, i.code, i.name, i.unit, i.item_condition, s.qty_available
                 FROM items i JOIN v_item_stock s ON s.id = i.id WHERE i.is_active = 1 ORDER BY i.code');
$errors = [];

$f = $loan ?: [
    'borrower_name' => '', 'borrower_id_no' => '', 'borrower_type' => 'siswa', 'class_dept' => '', 'item_id' => get('item_id'),
    'qty' => '1', 'loan_date' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime('+7 days')), 'notes' => '',
];

if (is_post()) {
    csrf_verify();
    foreach (['borrower_name', 'borrower_id_no', 'borrower_type', 'class_dept', 'loan_date', 'due_date', 'notes'] as $k) {
        if ($isEdit && $k === 'loan_date') { continue; }
        $f[$k] = post($k);
    }
    if (!$isEdit) {
        $f['item_id'] = post('item_id');
        $f['qty'] = post('qty');
    }
    if ($f['borrower_name'] === '' || mb_strlen($f['borrower_name']) > 100) { $errors[] = 'Nama peminjam wajib diisi (maks. 100 karakter).'; }
    if (mb_strlen($f['borrower_id_no']) > 30) { $errors[] = 'NIS/NIP maksimal 30 karakter.'; }
    if (mb_strlen($f['class_dept']) > 60) { $errors[] = 'Kelas/bagian maksimal 60 karakter.'; }
    if (!isset(BORROWER_TYPES[$f['borrower_type']])) { $errors[] = 'Jenis peminjam tidak valid.'; }
    if (!valid_date((string) $f['loan_date'])) { $errors[] = 'Tanggal pinjam tidak valid.'; }
    elseif (!$isEdit && $f['loan_date'] > date('Y-m-d')) { $errors[] = 'Tanggal pinjam tidak boleh di masa depan.'; }
    if (!valid_date((string) $f['due_date'])) { $errors[] = 'Rencana tanggal kembali tidak valid.'; }
    elseif (valid_date((string) $f['loan_date']) && $f['due_date'] < $f['loan_date']) { $errors[] = 'Rencana tanggal kembali tidak boleh sebelum tanggal pinjam.'; }
    $qty = null;
    if (!$isEdit) {
        $qty = to_int((string) $f['qty']);
        if ($qty === null || $qty < 1) { $errors[] = 'Jumlah barang harus bilangan bulat positif.'; }
        if (!db_val('SELECT id FROM items WHERE id = ? AND is_active = 1', [(int) $f['item_id']])) { $errors[] = 'Pilih barang yang valid.'; }
    }

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            if ($isEdit) {
                $pdo->prepare('UPDATE loans SET borrower_name=?, borrower_id_no=?, borrower_type=?, class_dept=?, due_date=?, notes=?, status=? WHERE id=?')
                    ->execute([$f['borrower_name'], $f['borrower_id_no'] ?: null, $f['borrower_type'], $f['class_dept'] ?: null, $f['due_date'],
                        $f['notes'] ?: null, loan_status_calc((int) $loan['qty'], (int) $loan['qty_returned'], $f['due_date']), $id]);
                $pdo->commit();
                flash('success', 'Data peminjaman diperbarui.');
                redirect('loans/view.php?id=' . $id);
            }
            // Peminjaman baru: kunci barang lalu periksa ketersediaan (tidak mengurangi stok fisik)
            $s = item_stock($pdo, (int) $f['item_id'], true);
            $cond = (string) $pdo->query('SELECT item_condition FROM items WHERE id = ' . (int) $f['item_id'])->fetchColumn();
            if ($cond === 'rusak_berat') {
                throw new RuntimeException('Barang berkondisi rusak berat tidak dapat dipinjamkan.');
            }
            if ($qty > (int) $s['qty_available']) {
                throw new RuntimeException('Jumlah melebihi stok tersedia. Tersedia: ' . max(0, (int) $s['qty_available'])
                    . ' dari fisik ' . $s['stock_total'] . ' (dipinjam ' . $s['qty_borrowed'] . ', pemeliharaan/rusak ' . ((int) $s['qty_maintenance'] + (int) $s['qty_broken']) . ').');
            }
            $no = next_number($pdo, 'loans', 'loan_number', 'PJM-' . date('Ym', strtotime($f['loan_date'])) . '-');
            $u = current_user();
            $pdo->prepare('INSERT INTO loans (loan_number, borrower_name, borrower_id_no, borrower_type, class_dept, item_id, qty, loan_date, due_date,
                           officer_name, user_id, status, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$no, $f['borrower_name'], $f['borrower_id_no'] ?: null, $f['borrower_type'], $f['class_dept'] ?: null, (int) $f['item_id'], $qty,
                    $f['loan_date'], $f['due_date'], $u['full_name'], $u['id'], loan_status_calc($qty, 0, $f['due_date']), $f['notes'] ?: null]);
            $newId = (int) $pdo->lastInsertId();
            $pdo->commit();
            flash('success', 'Peminjaman ' . $no . ' berhasil dicatat.');
            redirect('loans/view.php?id=' . $newId);
        } catch (RuntimeException $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $errors[] = $ex->getMessage();
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('[SILAB-RPL] ' . $ex->getMessage());
            $errors[] = 'Data gagal disimpan. Silakan coba lagi.';
        }
    }
}

$pageTitle = $isEdit ? 'Ubah Peminjaman' : 'Peminjaman Baru';
$active = 'loans';
include __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-xl-8">
<form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($er) ?></div><?php endforeach; ?>
    <div class="card mb-3">
        <div class="card-header">Data Peminjam</div>
        <div class="card-body row g-3">
            <div class="col-md-6"><label class="form-label required">Nama peminjam</label>
                <input class="form-control" name="borrower_name" maxlength="100" required value="<?= e($f['borrower_name']) ?>"></div>
            <div class="col-md-6"><label class="form-label required">Jenis peminjam</label>
                <select class="form-select" name="borrower_type"><?= options_map(BORROWER_TYPES, $f['borrower_type']) ?></select></div>
            <div class="col-md-6"><label class="form-label">NIS / NIP</label>
                <input class="form-control" name="borrower_id_no" maxlength="30" value="<?= e($f['borrower_id_no']) ?>"></div>
            <div class="col-md-6"><label class="form-label">Kelas / bagian</label>
                <input class="form-control" name="class_dept" maxlength="60" value="<?= e($f['class_dept']) ?>" placeholder="Contoh: XI RPL 1"></div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">Data Peminjaman</div>
        <div class="card-body row g-3">
            <?php if ($isEdit): ?>
                <div class="col-md-8"><label class="form-label">Barang</label>
                    <input class="form-control" disabled value="<?= e($loan['code'] . ' - ' . $loan['item_name']) ?>"></div>
                <div class="col-md-4"><label class="form-label">Jumlah</label><input class="form-control" disabled value="<?= num($loan['qty']) . ' ' . e($loan['unit']) ?>"></div>
                <div class="col-md-6"><label class="form-label">Tanggal pinjam</label><input class="form-control" disabled value="<?= e(tgl($loan['loan_date'])) ?>"></div>
            <?php else: ?>
                <div class="col-md-8"><label class="form-label required">Barang</label>
                    <select class="form-select" name="item_id" required>
                        <option value="">- Pilih barang -</option>
                        <?php foreach ($items as $it): $av = max(0, (int) $it['qty_available']); ?>
                            <option value="<?= (int) $it['id'] ?>"<?= sel($f['item_id'], $it['id']) ?><?= ($av < 1 || $it['item_condition'] === 'rusak_berat') ? ' disabled' : '' ?>>
                                <?= e($it['code'] . ' - ' . $it['name'] . ' (tersedia ' . $av . ' ' . $it['unit'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="col-md-4"><label class="form-label required">Jumlah</label>
                    <input type="number" min="1" step="1" class="form-control" name="qty" required value="<?= e($f['qty']) ?>"></div>
                <div class="col-md-6"><label class="form-label required">Tanggal pinjam</label>
                    <input type="date" class="form-control" name="loan_date" max="<?= date('Y-m-d') ?>" required value="<?= e($f['loan_date']) ?>"></div>
            <?php endif; ?>
            <div class="col-md-6"><label class="form-label required">Rencana tanggal kembali</label>
                <input type="date" class="form-control" name="due_date" required value="<?= e($f['due_date']) ?>"></div>
            <div class="col-12"><label class="form-label">Keterangan</label>
                <textarea class="form-control" name="notes" rows="2"><?= e($f['notes']) ?></textarea></div>
            <div class="col-12 small text-muted"><i class="bi bi-person me-1"></i>Petugas yang melayani: <strong><?= e($isEdit ? $loan['officer_name'] : current_user()['full_name']) ?></strong></div>
        </div>
    </div>
    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
        <a class="btn btn-light" href="<?= e(url('loans/')) ?>">Batal</a>
    </div>
</form>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
