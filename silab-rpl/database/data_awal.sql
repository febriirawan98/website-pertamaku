-- =====================================================================
-- SILAB-RPL - Data awal (kategori, lokasi, dan data contoh untuk pengujian)
--
-- Jalankan SETELAH schema.sql (phpMyAdmin > Import).
-- Tidak ada akun pengguna di file ini: akun administrator dibuat lewat
-- install.php supaya password tidak pernah tertulis di dalam kode.
--
-- Tanggal pada transaksi, peminjaman, dan pemeliharaan dibuat relatif
-- terhadap hari impor (CURDATE) agar grafik dan status "terlambat" terlihat.
-- Hanya impor satu kali pada database yang masih kosong.
-- =====================================================================

SET NAMES utf8mb4;
USE inventaris_lab_rpl;

-- ---------------------------------------------------------------------
-- Kategori
-- ---------------------------------------------------------------------
INSERT INTO categories (id, name, description) VALUES
(1, 'Komputer dan Laptop', 'Komputer desktop, laptop, dan perangkat komputasi sejenis'),
(2, 'Monitor', 'Layar monitor untuk komputer'),
(3, 'Perangkat Input', 'Keyboard, mouse, dan perangkat masukan lain'),
(4, 'Perangkat Jaringan', 'Router, switch, access point, kabel jaringan'),
(5, 'Printer dan Scanner', 'Printer, scanner, dan mesin multifungsi'),
(6, 'Proyektor', 'Proyektor dan perlengkapannya'),
(7, 'Peralatan Listrik', 'Kabel roll, stabilizer, UPS, dan terminal listrik'),
(8, 'Perabot Laboratorium', 'Meja, kursi, lemari, dan rak'),
(9, 'Peralatan Lainnya', 'Alat bantu praktik dan perlengkapan lain');

-- ---------------------------------------------------------------------
-- Lokasi penyimpanan
-- ---------------------------------------------------------------------
INSERT INTO locations (id, name, description) VALUES
(1, 'Laboratorium RPL', 'Ruang praktik utama'),
(2, 'Ruang Penyimpanan', 'Gudang kecil di samping laboratorium'),
(3, 'Meja Praktik 1', 'Deretan meja praktik sisi kiri'),
(4, 'Meja Praktik 2', 'Deretan meja praktik sisi kanan'),
(5, 'Lemari Inventaris', 'Lemari kunci milik laboran'),
(6, 'Ruang Administrasi', 'Ruang kerja laboran dan administrasi');

-- ---------------------------------------------------------------------
-- Barang (qty_total = jumlah fisik, harus sama dengan hasil akhir transaksi)
-- ---------------------------------------------------------------------
INSERT INTO items
(id, code, name, category_id, location_id, brand, model, serial_number, qty_total, unit,
 item_condition, acquisition_date, acquisition_source, unit_price, description) VALUES
(1,  'PC-001',  'Komputer Desktop',            1, 1, 'Lenovo',  'ThinkCentre M70s',  NULL,            20, 'unit', 'baik',         '2022-07-18', 'Dana BOS',               6500000, 'Komputer praktik siswa RPL'),
(2,  'LPT-001', 'Laptop ASUS VivoBook 14',      1, 5, 'ASUS',    'VivoBook 14 X1404', NULL,            10, 'unit', 'baik',         '2023-02-10', 'Bantuan Pemerintah',     7800000, 'Laptop untuk dipinjam siswa dan guru'),
(3,  'LPT-002', 'Laptop Acer Aspire 3',         1, 2, 'Acer',    'Aspire 3 A314',     NULL,             2, 'unit', 'rusak_ringan', '2021-09-05', 'Dana BOS',               5900000, 'Layar bergaris, menunggu perbaikan'),
(4,  'MON-001', 'Monitor LED 19 inci',          2, 1, 'LG',      '19M38A',            NULL,            20, 'unit', 'baik',         '2022-07-18', 'Dana BOS',               1250000, NULL),
(5,  'MON-002', 'Monitor LED 22 inci',          2, 2, 'Dell',    'E2220H',            'SN-DL22-0091',   1, 'unit', 'rusak_berat',  '2020-11-02', 'Hibah Alumni',           1800000, 'Panel pecah, tidak dapat diperbaiki'),
(6,  'KBD-001', 'Keyboard USB',                 3, 3, 'Logitech','K120',              NULL,            25, 'unit', 'baik',         '2022-07-18', 'Dana BOS',                120000, NULL),
(7,  'MSE-001', 'Mouse Optik USB',              3, 4, 'Logitech','B100',              NULL,            25, 'unit', 'baik',         '2022-07-18', 'Dana BOS',                 75000, NULL),
(8,  'RTR-001', 'Router Wireless',              4, 5, 'TP-Link', 'TL-WR840N',         NULL,             5, 'unit', 'baik',         '2023-05-22', 'Dana BOS',                285000, 'Untuk praktik konfigurasi jaringan'),
(9,  'SWT-001', 'Switch 24 Port',               4, 1, 'TP-Link', 'TL-SG1024D',        NULL,             2, 'unit', 'baik',         '2023-05-22', 'Dana BOS',               1150000, NULL),
(10, 'PRN-001', 'Printer Multifungsi',          5, 6, 'Epson',   'L3210',             NULL,             2, 'unit', 'baik',         '2023-08-14', 'Dana BOS',               2350000, NULL),
(11, 'PRJ-001', 'Proyektor',                    6, 1, 'Epson',   'EB-X06',            NULL,             1, 'unit', 'baik',         '2021-03-30', 'Hibah Komite Sekolah',   5400000, NULL),
(12, 'LST-001', 'Kabel Roll 10 Meter',          7, 2, 'Brother', 'KR-4 Lubang',       NULL,             8, 'buah', 'baik',         '2023-01-16', 'Hibah Komite Sekolah',    110000, NULL),
(13, 'MEJ-001', 'Meja Praktik Komputer',        8, 1, NULL,      NULL,                NULL,            10, 'buah', 'baik',         '2022-07-18', 'Dana BOS',                850000, NULL),
(14, 'LNN-001', 'Toolkit Jaringan (LAN Tester dan Crimping)', 9, 5, 'Krisbow', 'KW-NT', NULL,           4, 'set',  'baik',         '2023-05-22', 'Dana BOS',                240000, NULL);

-- ---------------------------------------------------------------------
-- Transaksi stok (catatan awal untuk setiap barang, plus contoh keluar/penyesuaian)
-- ---------------------------------------------------------------------
INSERT INTO stock_transactions
(trx_no, trx_date, trx_type, item_id, qty_change, qty_before, qty_after, location_id, counterparty, officer_name, notes) VALUES
('TRX-AWAL-001', DATE_SUB(CURDATE(), INTERVAL 150 DAY), 'masuk_pengadaan', 1,  20, 0, 20, 1, 'Dana BOS',               'Laboran Contoh', 'Pencatatan stok awal'),
('TRX-AWAL-002', DATE_SUB(CURDATE(), INTERVAL 150 DAY), 'masuk_pengadaan', 4,  20, 0, 20, 1, 'Dana BOS',               'Laboran Contoh', 'Pencatatan stok awal'),
('TRX-AWAL-003', DATE_SUB(CURDATE(), INTERVAL 150 DAY), 'masuk_pengadaan', 13, 10, 0, 10, 1, 'Dana BOS',               'Laboran Contoh', 'Pencatatan stok awal'),
('TRX-AWAL-004', DATE_SUB(CURDATE(), INTERVAL 120 DAY), 'masuk_pengadaan', 6,  26, 0, 26, 3, 'Dana BOS',               'Laboran Contoh', 'Pencatatan stok awal'),
('TRX-AWAL-005', DATE_SUB(CURDATE(), INTERVAL 120 DAY), 'masuk_pengadaan', 7,  25, 0, 25, 4, 'Dana BOS',               'Laboran Contoh', 'Pencatatan stok awal'),
('TRX-AWAL-006', DATE_SUB(CURDATE(), INTERVAL 100 DAY), 'masuk_hibah',     2,  10, 0, 10, 5, 'Bantuan Pemerintah',     'Laboran Contoh', 'Pencatatan stok awal'),
('TRX-AWAL-007', DATE_SUB(CURDATE(), INTERVAL 100 DAY), 'masuk_pengadaan', 3,   2, 0,  2, 2, 'Dana BOS',               'Laboran Contoh', 'Pencatatan stok awal'),
('TRX-AWAL-008', DATE_SUB(CURDATE(), INTERVAL 100 DAY), 'masuk_hibah',     5,   1, 0,  1, 2, 'Hibah Alumni',           'Laboran Contoh', 'Pencatatan stok awal'),
('TRX-AWAL-009', DATE_SUB(CURDATE(), INTERVAL 75 DAY),  'masuk_pengadaan', 8,   5, 0,  5, 5, 'Dana BOS',               'Laboran Contoh', 'Pencatatan stok awal'),
('TRX-AWAL-010', DATE_SUB(CURDATE(), INTERVAL 75 DAY),  'masuk_pengadaan', 9,   2, 0,  2, 1, 'Dana BOS',               'Laboran Contoh', 'Pencatatan stok awal'),
('TRX-AWAL-011', DATE_SUB(CURDATE(), INTERVAL 75 DAY),  'masuk_pengadaan', 14,  4, 0,  4, 5, 'Dana BOS',               'Laboran Contoh', 'Pencatatan stok awal'),
('TRX-AWAL-012', DATE_SUB(CURDATE(), INTERVAL 50 DAY),  'masuk_pengadaan', 10,  2, 0,  2, 6, 'Dana BOS',               'Laboran Contoh', 'Pencatatan stok awal'),
('TRX-AWAL-013', DATE_SUB(CURDATE(), INTERVAL 50 DAY),  'masuk_hibah',     11,  1, 0,  1, 1, 'Hibah Komite Sekolah',   'Laboran Contoh', 'Pencatatan stok awal'),
('TRX-AWAL-014', DATE_SUB(CURDATE(), INTERVAL 50 DAY),  'masuk_hibah',     12, 10, 0, 10, 2, 'Hibah Komite Sekolah',   'Laboran Contoh', 'Pencatatan stok awal'),
('TRX-AWAL-015', DATE_SUB(CURDATE(), INTERVAL 30 DAY),  'keluar_pemindahan', 12, -2, 10, 8, 2, 'Laboratorium TKJ',      'Laboran Contoh', 'Dipindahkan ke laboratorium TKJ untuk kegiatan praktik'),
('TRX-AWAL-016', DATE_SUB(CURDATE(), INTERVAL 20 DAY),  'penyesuaian',     6,  -1, 26, 25, 3, 'Pemeriksaan fisik',     'Laboran Contoh', 'Hasil pemeriksaan fisik: satu keyboard tidak ditemukan');

-- ---------------------------------------------------------------------
-- Peminjaman (contoh: aktif, terlambat, sebagian dikembalikan, selesai)
-- ---------------------------------------------------------------------
INSERT INTO loans
(loan_no, borrower_name, borrower_id_no, borrower_type, class_dept, item_id, qty, qty_returned,
 loan_date, due_date, return_date, officer_name, status, return_condition, return_log, notes) VALUES
('PJM-AWAL-001', 'Rizky Pratama', '2425001', 'siswa', 'XII RPL 1', 2, 2, 0,
 DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 4 DAY), NULL,
 'Laboran Contoh', 'dipinjam', NULL, NULL, 'Untuk pengerjaan proyek akhir'),
('PJM-AWAL-002', 'Dewi Lestari, S.Pd.', '198705122010012003', 'guru', 'Guru Produktif RPL', 11, 1, 0,
 DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY), NULL,
 'Laboran Contoh', 'dipinjam', NULL, NULL, 'Dipakai untuk kegiatan di luar laboratorium'),
('PJM-AWAL-003', 'Ahmad Fauzi', '2425017', 'siswa', 'XI RPL 2', 6, 3, 3,
 DATE_SUB(CURDATE(), INTERVAL 14 DAY), DATE_SUB(CURDATE(), INTERVAL 7 DAY), DATE_SUB(CURDATE(), INTERVAL 8 DAY),
 'Laboran Contoh', 'dikembalikan', 'baik',
 CONCAT(DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '%d/%m/%Y'), ': 3 unit dikembalikan, kondisi baik'), NULL),
('PJM-AWAL-004', 'Salsa Nabila', '2425033', 'siswa', 'XI RPL 1', 7, 4, 2,
 DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY), DATE_SUB(CURDATE(), INTERVAL 1 DAY),
 'Laboran Contoh', 'dikembalikan_sebagian', 'baik',
 CONCAT(DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '%d/%m/%Y'), ': 2 unit dikembalikan, kondisi baik'), 'Sisa 2 unit masih dipakai');

-- ---------------------------------------------------------------------
-- Pemeliharaan dan kerusakan
-- ---------------------------------------------------------------------
INSERT INTO maintenance
(item_id, qty, check_date, activity_type, damage_desc, action_taken, technician, cost, status, finish_date, notes) VALUES
(3, 1, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'perbaikan',
 'Layar bergaris vertikal', 'Penggantian kabel fleksibel LCD', 'Teknisi Contoh', 350000, 'dalam_proses', NULL, 'Menunggu suku cadang'),
(5, 1, DATE_SUB(CURDATE(), INTERVAL 40 DAY), 'perbaikan',
 'Panel layar pecah', 'Pemeriksaan teknisi: biaya perbaikan melebihi nilai barang', 'Teknisi Contoh', 0, 'tidak_dapat_diperbaiki', DATE_SUB(CURDATE(), INTERVAL 38 DAY), 'Diusulkan untuk penghapusan'),
(1, 5, DATE_SUB(CURDATE(), INTERVAL 35 DAY), 'perawatan',
 'Debu menumpuk dan suhu prosesor tinggi', 'Pembersihan debu dan penggantian thermal paste', 'Laboran Contoh', 0, 'selesai', DATE_SUB(CURDATE(), INTERVAL 34 DAY), NULL),
(8, 5, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'pemeriksaan',
 NULL, 'Pemeriksaan berkala firmware dan port', 'Laboran Contoh', 0, 'menunggu', NULL, 'Dijadwalkan pekan ini');
