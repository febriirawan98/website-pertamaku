<?php
/** Pembangun laporan: semua data diambil dari database dengan prepared statements. */

function report_types(): array
{
    return [
        'inventaris'     => ['Daftar Seluruh Inventaris', ['tanggal', 'category', 'location', 'kondisi']],
        'rekap_kategori' => ['Rekap Barang Berdasarkan Kategori', ['location']],
        'rekap_kondisi'  => ['Rekap Kondisi Barang', ['category', 'location']],
        'masuk'          => ['Laporan Barang Masuk', ['tanggal', 'category', 'location', 'jenis']],
        'keluar'         => ['Laporan Barang Keluar', ['tanggal', 'category', 'location', 'jenis']],
        'pinjam_aktif'   => ['Laporan Peminjaman Aktif', ['tanggal', 'category']],
        'pengembalian'   => ['Laporan Pengembalian', ['tanggal', 'category']],
        'terlambat'      => ['Laporan Keterlambatan', ['tanggal', 'category']],
        'pemeliharaan'   => ['Laporan Pemeliharaan dan Kerusakan', ['tanggal', 'category']],
        'riwayat_barang' => ['Riwayat Transaksi per Barang', ['item']],
    ];
}

/**
 * @param array $f filter: dari, sampai, category, location, kondisi, jenis, item
 * @return array{title:string, headers:array, rows:array, num:array, footer:?array, periode:string}
 */
function build_report(string $type, array $f): array
{
    $today = today();
    $dari = valid_date($f['dari'] ?? '') ? $f['dari'] : '';
    $sampai = valid_date($f['sampai'] ?? '') ? $f['sampai'] : '';
    $cat = (int)($f['category'] ?? 0);
    $loc = (int)($f['location'] ?? 0);
    $kond = isset(kondisi_labels()[$f['kondisi'] ?? '']) ? $f['kondisi'] : '';
    $jenis = isset(jenis_trx_labels()[$f['jenis'] ?? '']) ? $f['jenis'] : '';
    $itemId = (int)($f['item'] ?? 0);

    $periode = 'Semua tanggal';
    if ($dari && $sampai) $periode = fmt_date_long($dari) . ' s.d. ' . fmt_date_long($sampai);
    elseif ($dari) $periode = 'Sejak ' . fmt_date_long($dari);
    elseif ($sampai) $periode = 'Sampai ' . fmt_date_long($sampai);

    $w = [];
    $p = [];
    $add = function (string $cond, $val = null) use (&$w, &$p) {
        $w[] = $cond;
        if ($val !== null) $p[] = $val;
    };
    $where = function () use (&$w) { return $w ? 'WHERE ' . implode(' AND ', $w) : ''; };
    $r = ['title' => report_types()[$type][0], 'footer' => null, 'periode' => $periode, 'num' => []];

    switch ($type) {
        case 'inventaris':
            if ($dari) $add('i.tanggal_pengadaan >= ?', $dari);
            if ($sampai) $add('i.tanggal_pengadaan <= ?', $sampai);
            if ($cat) $add('i.category_id = ?', $cat);
            if ($loc) $add('i.location_id = ?', $loc);
            if ($kond) $add('i.kondisi = ?', $kond);
            $add('i.is_active = 1');
            $rows = db_all("SELECT i.*, c.nama_kategori, l.nama_lokasi, s.jumlah_dipinjam, s.jumlah_pemeliharaan, s.jumlah_tersedia
                FROM items i JOIN categories c ON c.id = i.category_id JOIN locations l ON l.id = i.location_id JOIN v_item_stock s ON s.item_id = i.id
                " . $where() . " ORDER BY i.kode_barang", $p);
            $r['headers'] = ['No', 'Kode', 'Nama Barang', 'Kategori', 'Merek / Model', 'No. Seri', 'Jumlah', 'Dipinjam', 'Dirawat', 'Tersedia', 'Satuan', 'Kondisi', 'Lokasi', 'Tgl Pengadaan', 'Harga Satuan', 'Nilai Total'];
            $r['num'] = [6, 7, 8, 9, 14, 15];
            $tot = [0, 0, 0, 0, 0.0];
            foreach ($rows as $n => $i) {
                $nilai = $i['harga_satuan'] * $i['jumlah_total'];
                $r['rows'][] = [$n + 1, $i['kode_barang'], $i['nama_barang'], $i['nama_kategori'], trim($i['merek'] . ' ' . $i['model']), $i['nomor_seri'], (int)$i['jumlah_total'],
                    (int)$i['jumlah_dipinjam'], (int)$i['jumlah_pemeliharaan'], (int)$i['jumlah_tersedia'], $i['satuan'], kondisi_labels()[$i['kondisi']], $i['nama_lokasi'],
                    fmt_date($i['tanggal_pengadaan']), fmt_int($i['harga_satuan']), fmt_int($nilai)];
                $tot[0] += $i['jumlah_total']; $tot[1] += $i['jumlah_dipinjam']; $tot[2] += $i['jumlah_pemeliharaan']; $tot[3] += $i['jumlah_tersedia']; $tot[4] += $nilai;
            }
            $r['footer'] = ['', '', '', '', '', 'TOTAL', fmt_int($tot[0]), fmt_int($tot[1]), fmt_int($tot[2]), fmt_int($tot[3]), '', '', '', '', '', fmt_int($tot[4])];
            break;

        case 'rekap_kategori':
            $join = 'i.category_id = c.id AND i.is_active = 1' . ($loc ? ' AND i.location_id = ?' : '');
            if ($loc) $p[] = $loc;
            $rows = db_all("SELECT c.nama_kategori, COUNT(i.id) AS jenis, COALESCE(SUM(i.jumlah_total),0) AS unit,
                    COALESCE(SUM(CASE WHEN i.kondisi='baik' THEN i.jumlah_total END),0) AS baik,
                    COALESCE(SUM(CASE WHEN i.kondisi='rusak_ringan' THEN i.jumlah_total END),0) AS ringan,
                    COALESCE(SUM(CASE WHEN i.kondisi='rusak_berat' THEN i.jumlah_total END),0) AS berat,
                    COALESCE(SUM(i.jumlah_total * i.harga_satuan),0) AS nilai
                FROM categories c LEFT JOIN items i ON $join GROUP BY c.id, c.nama_kategori ORDER BY c.nama_kategori", $p);
            $r['headers'] = ['No', 'Kategori', 'Jenis Barang', 'Total Unit', 'Baik', 'Rusak Ringan', 'Rusak Berat', 'Nilai Total (Rp)'];
            $r['num'] = [2, 3, 4, 5, 6, 7];
            $t = [0, 0, 0, 0, 0, 0.0];
            foreach ($rows as $n => $x) {
                $r['rows'][] = [$n + 1, $x['nama_kategori'], (int)$x['jenis'], (int)$x['unit'], (int)$x['baik'], (int)$x['ringan'], (int)$x['berat'], fmt_int($x['nilai'])];
                foreach (['jenis', 'unit', 'baik', 'ringan', 'berat', 'nilai'] as $k => $key) $t[$k] += $x[$key];
            }
            $r['footer'] = ['', 'TOTAL', fmt_int($t[0]), fmt_int($t[1]), fmt_int($t[2]), fmt_int($t[3]), fmt_int($t[4]), fmt_int($t[5])];
            break;

        case 'rekap_kondisi':
            $add('i.is_active = 1');
            if ($cat) $add('i.category_id = ?', $cat);
            if ($loc) $add('i.location_id = ?', $loc);
            $rows = db_all("SELECT i.kondisi, COUNT(*) AS jenis, SUM(i.jumlah_total) AS unit, SUM(i.jumlah_total * i.harga_satuan) AS nilai FROM items i " . $where() . " GROUP BY i.kondisi", $p);
            $by = [];
            foreach ($rows as $x) $by[$x['kondisi']] = $x;
            $totalUnit = array_sum(array_map(fn($x) => (int)$x['unit'], $by));
            $r['headers'] = ['No', 'Kondisi', 'Jenis Barang', 'Total Unit', 'Persentase', 'Nilai Total (Rp)'];
            $r['num'] = [2, 3, 4, 5];
            $n = 0; $t = [0, 0, 0.0];
            foreach (kondisi_labels() as $k => $lbl) {
                $x = $by[$k] ?? ['jenis' => 0, 'unit' => 0, 'nilai' => 0];
                $r['rows'][] = [++$n, $lbl, (int)$x['jenis'], (int)$x['unit'], $totalUnit ? number_format($x['unit'] / $totalUnit * 100, 1, ',', '.') . '%' : '0%', fmt_int($x['nilai'])];
                $t[0] += $x['jenis']; $t[1] += $x['unit']; $t[2] += $x['nilai'];
            }
            $r['footer'] = ['', 'TOTAL', fmt_int($t[0]), fmt_int($t[1]), $totalUnit ? '100%' : '0%', fmt_int($t[2])];
            break;

        case 'masuk':
        case 'keluar':
            $add('t.arah = ?', $type);
            if ($dari) $add('t.tanggal >= ?', $dari);
            if ($sampai) $add('t.tanggal <= ?', $sampai);
            if ($cat) $add('i.category_id = ?', $cat);
            if ($loc) $add('t.location_id = ?', $loc);
            if ($jenis) $add('t.jenis = ?', $jenis);
            $rows = db_all("SELECT t.*, i.kode_barang, i.nama_barang, i.satuan, l.nama_lokasi FROM stock_transactions t JOIN items i ON i.id = t.item_id
                LEFT JOIN locations l ON l.id = t.location_id " . $where() . " ORDER BY t.tanggal, t.id", $p);
            $r['headers'] = ['No', 'No. Transaksi', 'Tanggal', 'Jenis', 'Kode', 'Nama Barang', 'Jumlah', 'Satuan', 'Lokasi', 'Sumber / Tujuan', 'Petugas', 'Keterangan'];
            $r['num'] = [6];
            $sum = 0;
            foreach ($rows as $n => $t) {
                $r['rows'][] = [$n + 1, $t['no_transaksi'], fmt_date($t['tanggal']), jenis_trx_labels()[$t['jenis']], $t['kode_barang'], $t['nama_barang'], (int)$t['jumlah'], $t['satuan'], $t['nama_lokasi'], $t['sumber_tujuan'], $t['petugas'], $t['keterangan']];
                $sum += $t['jumlah'];
            }
            $r['footer'] = ['', '', '', '', '', 'TOTAL UNIT', fmt_int($sum), '', '', '', '', ''];
            break;

        case 'pinjam_aktif':
        case 'pengembalian':
        case 'terlambat':
            if ($type === 'pinjam_aktif') {
                $add("l.status <> 'dikembalikan'");
                $dateCol = 'l.tanggal_pinjam';
            } elseif ($type === 'terlambat') {
                $add("l.status <> 'dikembalikan' AND l.tanggal_rencana_kembali < ?", $today);
                $dateCol = 'l.tanggal_rencana_kembali';
            } else {
                $add('l.jumlah_dikembalikan > 0');
                $dateCol = 'l.tanggal_kembali_aktual';
            }
            if ($dari) $add("$dateCol >= ?", $dari);
            if ($sampai) $add("$dateCol <= ?", $sampai);
            if ($cat) $add('i.category_id = ?', $cat);
            $rows = db_all("SELECT l.*, i.kode_barang, i.nama_barang, i.satuan FROM loans l JOIN items i ON i.id = l.item_id " . $where() . " ORDER BY l.tanggal_pinjam, l.id", $p);
            $r['headers'] = ['No', 'No. Pinjam', 'Peminjam', 'NIS/NIP', 'Kelas/Bagian', 'Barang', 'Jumlah', 'Kembali', 'Sisa', 'Tgl Pinjam', 'Rencana Kembali', 'Tgl Kembali', 'Kondisi Kembali', $type === 'terlambat' ? 'Hari Terlambat' : 'Status'];
            $r['num'] = [6, 7, 8];
            foreach ($rows as $n => $l) {
                $sisa = $l['jumlah'] - $l['jumlah_dikembalikan'];
                $last = $type === 'terlambat'
                    ? (int)((strtotime($today) - strtotime($l['tanggal_rencana_kembali'])) / 86400)
                    : ['dipinjam' => 'Dipinjam', 'sebagian' => 'Dikembalikan sebagian', 'dikembalikan' => 'Dikembalikan'][$l['status']] . (loan_effective_status($l) === 'terlambat' ? ' (terlambat)' : '');
                $r['rows'][] = [$n + 1, $l['no_peminjaman'], $l['nama_peminjam'], $l['nomor_induk'], trim(jenis_peminjam_labels()[$l['jenis_peminjam']] . ' ' . $l['kelas_bagian']),
                    $l['kode_barang'] . ' - ' . $l['nama_barang'], (int)$l['jumlah'], (int)$l['jumlah_dikembalikan'], (int)$sisa, fmt_date($l['tanggal_pinjam']),
                    fmt_date($l['tanggal_rencana_kembali']), fmt_date($l['tanggal_kembali_aktual']), $l['kondisi_kembali'] ? kondisi_labels()[$l['kondisi_kembali']] : '-', $last];
            }
            break;

        case 'pemeliharaan':
            if ($dari) $add('m.tanggal_periksa >= ?', $dari);
            if ($sampai) $add('m.tanggal_periksa <= ?', $sampai);
            if ($cat) $add('i.category_id = ?', $cat);
            $rows = db_all("SELECT m.*, i.kode_barang, i.nama_barang FROM maintenance m JOIN items i ON i.id = m.item_id " . $where() . " ORDER BY m.tanggal_periksa, m.id", $p);
            $r['headers'] = ['No', 'Tanggal', 'Kode', 'Nama Barang', 'Jenis', 'Unit', 'Kerusakan', 'Tindakan', 'Teknisi', 'Biaya (Rp)', 'Status', 'Selesai'];
            $r['num'] = [5, 9];
            $sum = 0;
            foreach ($rows as $n => $m) {
                $r['rows'][] = [$n + 1, fmt_date($m['tanggal_periksa']), $m['kode_barang'], $m['nama_barang'], maint_jenis_labels()[$m['jenis']], (int)$m['jumlah'],
                    $m['deskripsi_kerusakan'], $m['tindakan'], $m['teknisi'], fmt_int($m['biaya']), maint_status_labels()[$m['status']], fmt_date($m['tanggal_selesai'])];
                $sum += $m['biaya'];
            }
            $r['footer'] = ['', '', '', '', '', '', '', '', 'TOTAL BIAYA', fmt_int($sum), '', ''];
            break;

        case 'riwayat_barang':
            $it = $itemId ? db_row('SELECT kode_barang, nama_barang FROM items WHERE id = ?', [$itemId]) : null;
            $r['title'] .= $it ? ': ' . $it['kode_barang'] . ' - ' . $it['nama_barang'] : '';
            $r['headers'] = ['No', 'Tanggal', 'Kategori Riwayat', 'Referensi', 'Keterangan', 'Jumlah', 'Petugas'];
            $r['num'] = [5];
            $ev = [];
            if ($it) {
                foreach (db_all('SELECT * FROM stock_transactions WHERE item_id = ?', [$itemId]) as $t) {
                    $ev[] = [$t['tanggal'], $t['id'], 'Transaksi stok', $t['no_transaksi'], jenis_trx_labels()[$t['jenis']] . " (stok {$t['stok_sebelum']} -> {$t['stok_sesudah']})" . ($t['keterangan'] ? ': ' . $t['keterangan'] : ''), ($t['arah'] === 'masuk' ? '+' : '-') . $t['jumlah'], $t['petugas']];
                }
                foreach (db_all('SELECT * FROM loans WHERE item_id = ?', [$itemId]) as $l) {
                    $ev[] = [$l['tanggal_pinjam'], $l['id'], 'Peminjaman', $l['no_peminjaman'], $l['nama_peminjam'] . ' (' . $l['jumlah_dikembalikan'] . '/' . $l['jumlah'] . ' kembali, ' . loan_effective_status($l) . ')', '-' . $l['jumlah'], $l['petugas']];
                }
                foreach (db_all('SELECT * FROM maintenance WHERE item_id = ?', [$itemId]) as $m) {
                    $ev[] = [$m['tanggal_periksa'], $m['id'], 'Pemeliharaan', maint_jenis_labels()[$m['jenis']], trim(($m['deskripsi_kerusakan'] ?? '') . ' [' . maint_status_labels()[$m['status']] . ']'), (string)$m['jumlah'], $m['teknisi']];
                }
                usort($ev, fn($a, $b) => [$a[0], $a[2], $a[1]] <=> [$b[0], $b[2], $b[1]]);
            }
            foreach ($ev as $n => $e) {
                $r['rows'][] = [$n + 1, fmt_date($e[0]), $e[2], $e[3], $e[4], $e[5], $e[6]];
            }
            $r['periode'] = 'Seluruh riwayat';
            break;
    }
    $r['rows'] = $r['rows'] ?? [];
    return $r;
}
