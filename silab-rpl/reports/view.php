<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/reports.php';
require_login();

$type = get('type');
if (!isset(report_types()[$type])) {
    flash('error', 'Pilih jenis laporan.');
    redirect('reports/index.php');
}
$filters = [
    'dari' => get('dari'), 'sampai' => get('sampai'), 'category' => get('category'), 'location' => get('location'),
    'kondisi' => get('kondisi'), 'jenis' => get('jenis'), 'item' => get('item'),
];
if ($type === 'riwayat_barang' && !(int)$filters['item']) {
    flash('error', 'Pilih barang untuk laporan riwayat.');
    redirect('reports/index.php');
}
if (valid_date($filters['dari']) && valid_date($filters['sampai']) && $filters['dari'] > $filters['sampai']) {
    flash('error', 'Tanggal "dari" tidak boleh setelah tanggal "sampai".');
    redirect('reports/index.php');
}
$rep = build_report($type, $filters);
$petugas = current_user()['nama'];
$cetak = fmt_date_long(today()) . ' ' . date('H:i');

// ---- Ekspor CSV (Excel Indonesia memakai pemisah titik koma) ----
if (get('format') === 'csv') {
    $name = 'laporan_' . $type . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    echo "\xEF\xBB\xBF" . "sep=;\r\n";
    $out = fopen('php://output', 'w');
    $safe = function ($v) {   // cegah formula injection di Excel
        $v = (string)$v;
        return strlen($v) > 1 && in_array($v[0], ['=', '+', '-', '@'], true) && !is_numeric($v) ? "'" . $v : $v;
    };
    foreach ([[SCHOOL_NAME], [LAB_NAME], [$rep['title']], ['Periode: ' . $rep['periode']], ['Dicetak: ' . $cetak . ' oleh ' . $petugas], []] as $line) {
        fputcsv($out, $line, ';');
    }
    fputcsv($out, $rep['headers'], ';');
    foreach ($rep['rows'] as $row) fputcsv($out, array_map($safe, $row), ';');
    if ($rep['footer']) fputcsv($out, $rep['footer'], ';');
    fclose($out);
    exit;
}

$pageTitle = $rep['title'];
$active = 'reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head no-print">
    <h1><?= e($rep['title']) ?></h1>
    <div class="d-flex gap-2">
        <a class="btn btn-light border" href="<?= url('reports/index.php') ?>"><i class="bi bi-arrow-left me-1"></i>Ubah filter</a>
        <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Cetak</button>
        <a class="btn btn-outline-success" href="<?= e('?' . http_build_query(array_merge($_GET, ['format' => 'csv']))) ?>"><i class="bi bi-filetype-csv me-1"></i>CSV (Excel)</a>
    </div>
</div>

<div class="card"><div class="card-body">
    <div class="report-head">
        <h2><?= e(strtoupper(SCHOOL_NAME)) ?></h2>
        <h3><?= e(LAB_NAME) ?></h3>
        <div class="mt-2 fw-bold"><?= e(strtoupper($rep['title'])) ?></div>
    </div>
    <div class="report-meta d-flex flex-wrap justify-content-between">
        <div>Periode: <b><?= e($rep['periode']) ?></b></div>
        <div>Dicetak: <?= e($cetak) ?> &middot; Petugas: <?= e($petugas) ?></div>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-sm report-table align-middle mb-0">
            <thead><tr><?php foreach ($rep['headers'] as $i => $h): ?><th class="<?= in_array($i, $rep['num'], true) ? 'text-end' : '' ?>"><?= e($h) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php if (!$rep['rows']): ?><tr><td colspan="<?= count($rep['headers']) ?>" class="text-center text-muted py-3">Tidak ada data untuk filter ini.</td></tr><?php endif; ?>
            <?php foreach ($rep['rows'] as $row): ?>
                <tr><?php foreach ($row as $i => $c): ?><td class="<?= in_array($i, $rep['num'], true) ? 'text-end' : '' ?>"><?= e($c) ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
            </tbody>
            <?php if ($rep['footer'] && $rep['rows']): ?>
            <tfoot><tr class="fw-bold table-light"><?php foreach ($rep['footer'] as $i => $c): ?><td class="<?= in_array($i, $rep['num'], true) ? 'text-end' : '' ?>"><?= e($c) ?></td><?php endforeach; ?></tr></tfoot>
            <?php endif; ?>
        </table>
    </div>
    <div class="small text-muted mt-2">Jumlah baris: <?= count($rep['rows']) ?></div>
    <div class="sign-box"><div class="text-center">Ampelgading, <?= e(fmt_date_long(today())) ?><br>Petugas Laboratorium<br><br><br><br><u><?= e($petugas) ?></u></div></div>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
