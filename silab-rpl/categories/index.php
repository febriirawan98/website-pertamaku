<?php
require_once __DIR__ . '/../includes/init.php';
require_login();

// Hak akses: administrator & laboran dapat melihat, menambah, dan mengubah.
// Hanya administrator yang dapat menghapus kategori.
if (is_post()) {
    csrf_verify();
    $action = post('action');
    if ($action === 'save') {
        $id   = (int)post('id');
        $nama = post('nama_kategori');
        $ket  = post('keterangan');
        if ($nama === '' || mb_strlen($nama) > 100) {
            flash('error', 'Nama kategori wajib diisi (maks. 100 karakter).');
        } elseif (mb_strlen($ket) > 255) {
            flash('error', 'Keterangan maksimal 255 karakter.');
        } else {
            try {
                if ($id > 0) {
                    db_exec('UPDATE categories SET nama_kategori = ?, keterangan = ? WHERE id = ?', [$nama, $ket ?: null, $id]);
                    flash('success', 'Kategori berhasil diperbarui.');
                } else {
                    db_exec('INSERT INTO categories (nama_kategori, keterangan) VALUES (?, ?)', [$nama, $ket ?: null]);
                    flash('success', 'Kategori berhasil ditambahkan.');
                }
            } catch (PDOException $e) {
                if (is_duplicate_error($e)) {
                    flash('error', 'Nama kategori "' . $nama . '" sudah ada.');
                } else {
                    throw $e;
                }
            }
        }
    } elseif ($action === 'delete') {
        if (!is_admin()) {
            flash('error', 'Hanya administrator yang dapat menghapus kategori.');
        } else {
            $id = (int)post('id');
            $used = (int)db_val('SELECT COUNT(*) FROM items WHERE category_id = ?', [$id]);
            if ($used > 0) {
                flash('error', "Kategori tidak dapat dihapus karena dipakai oleh $used barang.");
            } else {
                db_exec('DELETE FROM categories WHERE id = ?', [$id]);
                flash('success', 'Kategori berhasil dihapus.');
            }
        }
    }
    redirect('categories/index.php' . (get('q') !== '' ? '?q=' . urlencode(get('q')) : ''));
}

$q = get('q');
$where = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE c.nama_kategori LIKE ? OR c.keterangan LIKE ?';
    $params = ["%$q%", "%$q%"];
}
$total = (int)db_val("SELECT COUNT(*) FROM categories c $where", $params);
$pg = paginate($total);
$rows = db_all(
    "SELECT c.*, (SELECT COUNT(*) FROM items i WHERE i.category_id = c.id) AS jml_jenis,
            (SELECT COALESCE(SUM(i.jumlah_total),0) FROM items i WHERE i.category_id = c.id AND i.is_active = 1) AS jml_unit
       FROM categories c $where ORDER BY c.nama_kategori LIMIT {$pg['per']} OFFSET {$pg['offset']}",
    $params
);

$pageTitle = 'Kategori Barang';
$active = 'categories';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Kategori Barang</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#catModal" data-title="Tambah Kategori"><i class="bi bi-plus-lg me-1"></i>Tambah Kategori</button>
</div>

<div class="card mb-3"><div class="card-body py-2">
    <form class="row g-2 filter-bar" method="get">
        <div class="col-sm"><input class="form-control" name="q" placeholder="Cari nama atau keterangan kategori..." value="<?= e($q) ?>"></div>
        <div class="col-sm-auto"><button class="btn btn-outline-primary"><i class="bi bi-search me-1"></i>Cari</button>
            <?php if ($q !== ''): ?><a class="btn btn-light" href="?">Reset</a><?php endif; ?></div>
    </form>
</div></div>

<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th style="width:50px">No</th><th>Nama Kategori</th><th>Keterangan</th><th class="text-end">Jenis Barang</th><th class="text-end">Total Unit</th><th></th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data.</td></tr><?php endif; ?>
        <?php foreach ($rows as $n => $r): ?>
            <tr>
                <td><?= $pg['offset'] + $n + 1 ?></td>
                <td class="fw-semibold"><?= e($r['nama_kategori']) ?></td>
                <td class="text-muted"><?= e($r['keterangan']) ?></td>
                <td class="text-end"><a href="<?= url('items/index.php?category=' . $r['id']) ?>"><?= fmt_int($r['jml_jenis']) ?></a></td>
                <td class="text-end"><?= fmt_int($r['jml_unit']) ?></td>
                <td class="table-actions">
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#catModal" data-title="Ubah Kategori"
                            data-fill="<?= json_attr(['id' => $r['id'], 'nama_kategori' => $r['nama_kategori'], 'keterangan' => $r['keterangan']]) ?>"><i class="bi bi-pencil"></i></button>
                    <?php if (is_admin()): ?>
                    <form method="post" class="d-inline" data-confirm="Hapus kategori &quot;<?= e($r['nama_kategori']) ?>&quot;?">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" <?= $r['jml_jenis'] > 0 ? 'disabled title="Dipakai oleh barang"' : '' ?>><i class="bi bi-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
<?= render_pagination($pg) ?>

<div class="modal fade js-form-modal" id="catModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="">
        <div class="modal-header"><h5 class="modal-title">Tambah Kategori</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label req">Nama kategori</label><input class="form-control" name="nama_kategori" required maxlength="100"></div>
            <div><label class="form-label">Keterangan</label><input class="form-control" name="keterangan" maxlength="255"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan</button></div>
    </form></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
