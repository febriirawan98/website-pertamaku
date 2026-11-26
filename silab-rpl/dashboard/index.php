<?php
require_once __DIR__ . '/../includes/init.php';
require_login();

$today = today();

// ---- Kartu ringkasan (semua dari database) ----
$sum = db_row(
    "SELECT COUNT(*) AS jenis,
            COALESCE(SUM(i.jumlah_total),0) AS unit,
            COALESCE(SUM(CASE WHEN i.kondisi='baik' THEN i.jumlah_total END),0) AS baik,
            COALESCE(SUM(CASE WHEN i.kondisi='rusak_ringan' THEN i.jumlah_total END),0) AS ringan,
            COALESCE(SUM(CASE WHEN i.kondisi='rusak_berat' THEN i.jumlah_total END),0) AS berat,
            COALESCE(SUM(s.jumlah_dipinjam),0) AS dipinjam,
            COALESCE(SUM(s.jumlah_pemeliharaan),0) AS pemeliharaan,
            COALESCE(SUM(s.jumlah_tersedia),0) AS tersedia
       FROM items i JOIN v_item_stock s ON s.item_id = i.id
      WHERE i.is_active = 1"
);
$terlambat = (int)db_val("SELECT COUNT(*) FROM loans WHERE status <> 'dikembalikan' AND tanggal_rencana_kembali < ?", [$today]);

$cards = [
    ['Total Jenis Barang', fmt_int($sum['jenis']), 'box-seam', 'blue'],
    ['Total Unit Barang', fmt_int($sum['unit']), 'boxes', 'blue'],
    ['Kondisi Baik', fmt_int($sum['baik']), 'check-circle', 'green'],
    ['Rusak Ringan', fmt_int($sum['ringan']), 'exclamation-triangle', 'yellow'],
    ['Rusak Berat', fmt_int($sum['berat']), 'x-octagon', 'red'],
    ['Sedang Dipinjam', fmt_int($sum['dipinjam']), 'box-arrow-up-right', 'teal'],
    ['Dalam Pemeliharaan', fmt_int($sum['pemeliharaan']), 'tools', 'gray'],
    ['Tersedia Dipinjam', fmt_int($sum['tersedia']), 'bag-check', 'green'],
];

// ---- Grafik ----
$catRows = db_all(
    "SELECT c.nama_kategori AS label, COALESCE(SUM(i.jumlah_total),0) AS total
       FROM categories c LEFT JOIN items i ON i.category_id = c.id AND i.is_active = 1
      GROUP BY c.id, c.nama_kategori HAVING total > 0 ORDER BY total DESC"
);
$kondData = [(int)$sum['baik'], (int)$sum['ringan'], (int)$sum['berat']];

$months = [];
for ($k = 5; $k >= 0; $k--) {
    $months[date('Y-m', strtotime("first day of -$k month"))] = ['masuk' => 0, 'keluar' => 0];
}
$first = array_key_first($months) . '-01';
foreach (db_all("SELECT DATE_FORMAT(tanggal,'%Y-%m') AS bln, arah, SUM(jumlah) AS total FROM stock_transactions WHERE tanggal >= ? GROUP BY bln, arah", [$first]) as $r) {
    if (isset($months[$r['bln']])) {
        $months[$r['bln']][$r['arah']] = (int)$r['total'];
    }
}
$monthLabels = [];
foreach (array_keys($months) as $ym) {
    $monthLabels[] = bulan_id((int)substr($ym, 5, 2)) . ' ' . substr($ym, 0, 4);
}

// ---- Aktivitas terbaru ----
$activity = db_all(
    "(SELECT t.created_at AS waktu, 'transaksi' AS tipe,
             CONCAT(t.no_transaksi, ': ', IF(t.arah='masuk','masuk ','keluar '), t.jumlah, ' ', i.satuan, ' ', i.nama_barang) AS teks, t.petugas AS oleh
        FROM stock_transactions t JOIN items i ON i.id = t.item_id ORDER BY t.id DESC LIMIT 8)
     UNION ALL
     (SELECT l.created_at, 'peminjaman',
             CONCAT(l.no_peminjaman, ': ', l.nama_peminjam, ' meminjam ', l.jumlah, ' ', i.satuan, ' ', i.nama_barang), l.petugas
        FROM loans l JOIN items i ON i.id = l.item_id ORDER BY l.id DESC LIMIT 8)
     UNION ALL
     (SELECT m.created_at, 'pemeliharaan',
             CONCAT(m.jenis, ' ', i.nama_barang, ' (', REPLACE(m.status,'_',' '), ')'), m.teknisi
        FROM maintenance m JOIN items i ON i.id = m.item_id ORDER BY m.id DESC LIMIT 8)
     ORDER BY waktu DESC LIMIT 8"
);
$tipeIcon = ['transaksi' => ['arrow-left-right', 'primary'], 'peminjaman' => ['box-arrow-up-right', 'info'], 'pemeliharaan' => ['tools', 'secondary']];

// ---- Perlu ditindaklanjuti ----
$todo = [];
foreach (db_all(
    "SELECT l.id, l.no_peminjaman, l.nama_peminjam, i.nama_barang, (l.jumlah - l.jumlah_dikembalikan) AS sisa, DATEDIFF(?, l.tanggal_rencana_kembali) AS hari
       FROM loans l JOIN items i ON i.id = l.item_id
      WHERE l.status <> 'dikembalikan' AND l.tanggal_rencana_kembali < ? ORDER BY l.tanggal_rencana_kembali LIMIT 5", [$today, $today]) as $r) {
    $todo[] = ['Terlambat', 'danger', $r['nama_peminjam'] . ' belum mengembalikan ' . $r['sisa'] . ' ' . $r['nama_barang'] . ' (' . $r['hari'] . ' hari)', 'loans/view.php?id=' . $r['id']];
}
foreach (db_all(
    "SELECT m.id, m.jenis, m.status, i.nama_barang FROM maintenance m JOIN items i ON i.id = m.item_id
      WHERE m.status IN ('menunggu','dalam_proses') ORDER BY m.tanggal_periksa LIMIT 5") as $r) {
    $todo[] = ['Pemeliharaan', 'primary', ucfirst($r['jenis']) . ' ' . $r['nama_barang'] . ' - ' . (maint_status_labels()[$r['status']]), 'maintenance/form.php?id=' . $r['id']];
}
foreach (db_all(
    "SELECT id, nama_barang, jumlah_total, satuan FROM items WHERE is_active = 1 AND kondisi = 'rusak_berat' ORDER BY nama_barang LIMIT 5") as $r) {
    $todo[] = ['Rusak Berat', 'dark', $r['nama_barang'] . ' (' . $r['jumlah_total'] . ' ' . $r['satuan'] . ') perlu diputuskan: perbaikan atau penghapusan', 'items/view.php?id=' . $r['id']];
}
foreach (db_all(
    "SELECT i.id, i.nama_barang, s.jumlah_tersedia, i.satuan FROM items i JOIN v_item_stock s ON s.item_id = i.id
      WHERE i.is_active = 1 AND i.kondisi <> 'rusak_berat' AND s.jumlah_tersedia <= i.stok_minimum ORDER BY s.jumlah_tersedia, i.nama_barang LIMIT 5") as $r) {
    $todo[] = ['Stok', 'warning', $r['nama_barang'] . ': tersedia ' . $r['jumlah_tersedia'] . ' ' . $r['satuan'] . ' (di bawah/sama dengan batas minimum)', 'items/view.php?id=' . $r['id']];
}

$pageTitle = 'Dashboard';
$active = 'dashboard';
$extraHead = '';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Dashboard</h1>
    <div class="text-muted small"><i class="bi bi-calendar3 me-1"></i><?= e(fmt_date_long($today)) ?></div>
</div>

<?php if ($terlambat > 0): ?>
    <div class="alert alert-danger d-flex align-items-center py-2">
        <i class="bi bi-alarm-fill me-2"></i>
        <div><b><?= $terlambat ?></b> peminjaman melewati batas pengembalian. <a href="<?= url('loans/index.php?status=terlambat') ?>" class="alert-link">Lihat daftar</a></div>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <?php foreach ($cards as [$label, $val, $icon, $color]): ?>
        <div class="col-6 col-md-4 col-xl-3">
            <div class="card stat-card">
                <div class="stat-icon bg-soft-<?= $color ?>"><i class="bi bi-<?= $icon ?>"></i></div>
                <div><div class="stat-value"><?= $val ?></div><div class="stat-label"><?= e($label) ?></div></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100"><div class="card-header">Jumlah Unit Barang per Kategori</div>
            <div class="card-body"><div class="chart-box"><canvas id="chCategory"></canvas></div></div></div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100"><div class="card-header">Perbandingan Kondisi Barang</div>
            <div class="card-body"><div class="chart-box"><canvas id="chKondisi"></canvas></div></div></div>
    </div>
    <div class="col-12">
        <div class="card"><div class="card-header">Barang Masuk &amp; Keluar per Bulan (6 bulan terakhir, dalam unit)</div>
            <div class="card-body"><div class="chart-box"><canvas id="chMonthly"></canvas></div></div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-header">Aktivitas Terbaru</div>
            <div class="card-body">
                <?php if (!$activity): ?><p class="text-muted mb-0">Belum ada aktivitas.</p><?php else: ?>
                <ul class="list-unstyled mb-0 activity">
                    <?php foreach ($activity as $a): [$ic, $cl] = $tipeIcon[$a['tipe']]; ?>
                        <li class="d-flex gap-2">
                            <span class="badge text-bg-<?= $cl ?> align-self-start mt-1"><i class="bi bi-<?= $ic ?>"></i></span>
                            <div><div><?= e($a['teks']) ?></div>
                                <div class="small text-muted"><?= e(fmt_datetime($a['waktu'])) ?> &middot; <?= e($a['oleh']) ?></div></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-header">Perlu Ditindaklanjuti</div>
            <div class="card-body">
                <?php if (!$todo): ?><p class="text-muted mb-0"><i class="bi bi-check2-circle text-success me-1"></i>Tidak ada yang perlu ditindaklanjuti.</p><?php else: ?>
                <ul class="list-unstyled mb-0 activity">
                    <?php foreach ($todo as [$jenis, $cl, $teks, $link]): ?>
                        <li class="d-flex gap-2 align-items-start">
                            <span class="badge text-bg-<?= $cl ?> mt-1" style="min-width:92px"><?= e($jenis) ?></span>
                            <div class="flex-grow-1"><?= e($teks) ?></div>
                            <a class="btn btn-sm btn-outline-primary py-0" href="<?= url($link) ?>">Lihat</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div></div>
    </div>
</div>

<?php
$chartData = [
    'cat' => ['labels' => array_column($catRows, 'label'), 'values' => array_map('intval', array_column($catRows, 'total'))],
    'kond' => $kondData,
    'month' => ['labels' => $monthLabels, 'masuk' => array_column($months, 'masuk'), 'keluar' => array_column($months, 'keluar')],
];
ob_start(); ?>
<script src="<?= url('assets/vendor/chartjs/chart.umd.min.js') ?>"></script>
<script>
(function () {
    var d = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.maintainAspectRatio = false;
    new Chart(document.getElementById('chCategory'), {
        type: 'bar',
        data: { labels: d.cat.labels, datasets: [{ label: 'Unit', data: d.cat.values, backgroundColor: '#1d4a94', borderRadius: 4 }] },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { ticks: { maxRotation: 45, minRotation: 0 } } } }
    });
    new Chart(document.getElementById('chKondisi'), {
        type: 'doughnut',
        data: { labels: ['Baik', 'Rusak Ringan', 'Rusak Berat'], datasets: [{ data: d.kond, backgroundColor: ['#198754', '#e0a800', '#c0392b'], borderWidth: 2 }] },
        options: { plugins: { legend: { position: 'bottom' } }, cutout: '58%' }
    });
    new Chart(document.getElementById('chMonthly'), {
        type: 'bar',
        data: { labels: d.month.labels, datasets: [
            { label: 'Masuk', data: d.month.masuk, backgroundColor: '#1d4a94', borderRadius: 4 },
            { label: 'Keluar', data: d.month.keluar, backgroundColor: '#9aa5b8', borderRadius: 4 }
        ] },
        options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
})();
</script>
<?php
$extraScripts = ob_get_clean();
require __DIR__ . '/../includes/footer.php';
