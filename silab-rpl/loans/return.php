<?php
require __DIR__ . '/../includes/bootstrap.php';

$id = (int) get('id', post('id', '0'));
$loan = db_one('SELECT l.*, i.name AS item_name, i.code, i.unit FROM loans l JOIN items i ON i.id = l.item_id WHERE l.id = ?', [$id]);
if (!$loan) {
    flash('error', 'Data peminjaman tidak ditemukan.');
    redirect('loans/');
}
$remain = (int) $loan['qty'] - (int) $loan['qty_returned'];
if ($remain <= 0) {
    flash('info', 'Peminjaman ini sudah dikembalikan seluruhnya.');
    redirect('loans/view.php?id=' . $id);
}

$errors = [];
$f = ['qty' => (string) $remain, 'return_date' => date('Y-m-d'), 'condition' => 'baik', 'notes' => ''];
if (is_post()) {
    csrf_verify();
    $f = ['qty' => post('qty'), 'return_date' => post('return_date'), 'condition' => post('condition'), 'notes' => post('notes')];
    $qty = to_int($f['qty']);
    if ($qty === null || $qty < 1) { $errors[] = 'Jumlah dikembalikan harus bilangan bulat positif.'; }
    if (!valid_date($f['return_date'])) { $errors[] = 'Tanggal pengembalian tidak valid.'; }
    elseif ($f['return_date'] > date('Y-m-d')) { $errors[] = 'Tanggal pengembalian tidak boleh di masa depan.'; }
    elseif ($f['return_date'] < $loan['loan_date']) { $errors[] = 'Tanggal pengembalian tidak boleh sebelum tanggal pinjam.'; }
    if (!isset(CONDITIONS[$f['condition']])) { $errors[] = 'Kondisi barang tidak valid.'; }
    if ($f['condition'] !== 'baik' && $f['notes'] === '') { $errors[] = 'Jelaskan kerusakan pada kolom keterangan.'; }

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $pdo->prepare('SELECT id FROM items WHERE id = ? FOR UPDATE')->execute([$loan['item_id']]);
            $cur = $pdo->prepare('SELECT qty, qty_returned, loan_date, due_date, notes FROM loans WHERE id = ? FOR UPDATE');
            $cur->execute([$id]);
            $c = $cur->fetch();
            $left = (int) $c['qty'] - (int) $c['qty_returned'];
            if ($qty > $left) {
                throw new RuntimeException('Jumlah dikembalikan melebihi sisa pinjaman (' . $left . ').');
            }
            $newRet = (int) $c['qty_returned'] + $qty;
            $status = loan_status_calc((int) $c['qty'], $newRet, $c['due_date']);
            $log = '[' . $f['return_date'] . '] Kembali ' . $qty . ' ' . $loan['unit'] . ' (' . CONDITIONS[$f['condition']] . ')'
                 . ($f['notes'] !== '' ? ': ' . $f['notes'] : '') . ' - dicatat oleh ' . current_user()['full_name'];
            $notes = trim((string) $c['notes'] . "\n" . $log);
            $pdo->prepare('UPDATE loans SET qty_returned = ?, return_date = ?, return_condition = ?, status = ?, notes = ? WHERE id = ?')
                ->execute([$newRet, $f['return_date'], $f['condition'], $status, $notes, $id]);

            $msg = 'Pengembalian ' . $qty . ' ' . $loan['unit'] . ' berhasil dicatat.';
            if ($f['condition'] !== 'baik') {
                // Barang rusak otomatis masuk antrean pemeliharaan sehingga tidak dihitung tersedia.
                $u = current_user();
                $pdo->prepare('INSERT INTO maintenance (item_id, qty, check_date, activity_type, damage_description, technician, status, notes, user_id)
                               VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$loan['item_id'], $qty, $f['return_date'], 'pemeriksaan',
                        CONDITIONS[$f['condition']] . ' setelah dipinjam (' . $loan['loan_number'] . '): ' . $f['notes'],
                        $u['full_name'], 'menunggu', 'Dibuat otomatis dari pengembalian ' . $loan['loan_number'], $u['id']]);
                $msg .= ' Unit rusak otomatis dicatat ke menu Pemeliharaan.';
            }
            $pdo->commit();
            flash('success', $msg);
            redirect('loans/view.php?id=' . $id);
        } catch (RuntimeException $ex) {
            $pdo->rollBack();
            $errors[] = $ex->getMessage();
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('[SILAB-RPL] ' . $ex->getMessage());
            $errors[] = 'Pengembalian gagal disimpan. Silakan coba lagi.';
        }
    }
}

$pageTitle = 'Catat Pengembalian';
$active = 'loans';
include __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-xl-8">
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between"><span><?= e($loan['loan_number']) ?></span><?= loan_badge($loan['status']) ?></div>
        <div class="card-body">
            <dl class="row detail-grid mb-0">
                <dt class="col-sm-4">Peminjam</dt><dd class="col-sm-8"><?= e($loan['borrower_name']) ?> <span class="text-muted small">(<?= e(BORROWER_TYPES[$loan['borrower_type']]) ?><?= $loan['class_dept'] ? ', ' . e($loan['class_dept']) : '' ?>)</span></dd>
                <dt class="col-sm-4">Barang</dt><dd class="col-sm-8"><?= e($loan['code'] . ' - ' . $loan['item_name']) ?></dd>
                <dt class="col-sm-4">Dipinjam / kembali</dt><dd class="col-sm-8"><?= num($loan['qty']) ?> / <?= num($loan['qty_returned']) ?> <?= e($loan['unit']) ?> &middot; <strong>sisa <?= num($remain) ?></strong></dd>
                <dt class="col-sm-4">Pinjam &rarr; jatuh tempo</dt><dd class="col-sm-8"><?= e(tgl($loan['loan_date'])) ?> &rarr; <?= e(tgl($loan['due_date'])) ?></dd>
            </dl>
        </div>
    </div>
    <form method="post" autocomplete="off">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
        <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($er) ?></div><?php endforeach; ?>
        <div class="card"><div class="card-header">Data Pengembalian</div>
            <div class="card-body row g-3">
                <div class="col-md-4"><label class="form-label required">Jumlah dikembalikan</label>
                    <input type="number" min="1" max="<?= $remain ?>" step="1" class="form-control" name="qty" required value="<?= e($f['qty']) ?>"></div>
                <div class="col-md-4"><label class="form-label required">Tanggal pengembalian</label>
                    <input type="date" class="form-control" name="return_date" min="<?= e($loan['loan_date']) ?>" max="<?= date('Y-m-d') ?>" required value="<?= e($f['return_date']) ?>"></div>
                <div class="col-md-4"><label class="form-label required">Kondisi saat kembali</label>
                    <select class="form-select" name="condition"><?= options_map(CONDITIONS, $f['condition']) ?></select></div>
                <div class="col-12"><label class="form-label">Keterangan</label>
                    <textarea class="form-control" name="notes" rows="2" placeholder="Wajib diisi bila barang rusak"><?= e($f['notes']) ?></textarea>
                    <div class="form-text">Barang yang kembali dalam kondisi rusak otomatis dicatat ke menu Pemeliharaan (status menunggu) dan tidak dihitung tersedia.</div></div>
            </div>
        </div>
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-success" type="submit"><i class="bi bi-check2-circle me-1"></i>Simpan Pengembalian</button>
            <a class="btn btn-light" href="<?= e(url('loans/')) ?>">Batal</a>
        </div>
    </form>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
