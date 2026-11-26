-- ============================================================
-- SILAB-RPL : Data Awal / Data Contoh untuk pengujian
-- Jalankan SETELAH schema.sql. Akun pengguna TIDAK dibuat di sini;
-- akun administrator dibuat aman melalui install.php.
-- Tanggal dibuat relatif terhadap hari impor supaya grafik terisi.
-- Catatan konsistensi stok: items.jumlah_total = hasil akhir
-- seluruh stock_transactions barang tersebut.
-- ============================================================
USE `inventaris_lab_rpl`;

SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM `maintenance`; DELETE FROM `loans`; DELETE FROM `stock_transactions`;
DELETE FROM `items`; DELETE FROM `locations`; DELETE FROM `categories`;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO `categories` (`id`,`nama_kategori`,`keterangan`) VALUES
 (1,'Komputer dan Laptop','PC desktop, laptop, dan perangkat komputasi'),
 (2,'Monitor','Layar monitor'),
 (3,'Perangkat Input','Keyboard, mouse, dan sejenisnya'),
 (4,'Perangkat Jaringan','Router, switch, access point, kabel'),
 (5,'Printer dan Scanner','Perangkat cetak dan pindai'),
 (6,'Proyektor','Proyektor dan layar proyeksi'),
 (7,'Peralatan Listrik','Stop kontak, stabilizer, UPS'),
 (8,'Perabot Laboratorium','Meja, kursi, lemari'),
 (9,'Peralatan Lainnya','Peralatan pendukung lain');

INSERT INTO `locations` (`id`,`nama_lokasi`,`keterangan`) VALUES
 (1,'Laboratorium RPL','Ruang praktik utama'),
 (2,'Ruang Penyimpanan','Gudang barang cadangan dan barang rusak'),
 (3,'Meja Praktik 1','Deretan meja praktik sisi kiri'),
 (4,'Meja Praktik 2','Deretan meja praktik sisi kanan'),
 (5,'Lemari Inventaris','Lemari terkunci milik laboran'),
 (6,'Ruang Administrasi','Ruang administrasi laboratorium');

INSERT INTO `items`
 (`id`,`kode_barang`,`nama_barang`,`category_id`,`merek`,`model`,`nomor_seri`,`jumlah_total`,`satuan`,`kondisi`,`location_id`,`tanggal_pengadaan`,`sumber_pengadaan`,`harga_satuan`,`stok_minimum`,`keterangan`) VALUES
 (1,'RPL-KOM-001','PC Desktop Praktik',1,'Lenovo','ThinkCentre M70s',NULL,20,'unit','baik',1,'2023-07-15','Dana BOS',6500000,2,'PC siswa untuk praktik pemrograman'),
 (2,'RPL-KOM-002','Laptop Praktik',1,'ASUS','Vivobook 14',NULL,10,'unit','baik',5,DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'Hibah Dinas Pendidikan',7200000,2,'Dapat dipinjam untuk kegiatan di luar lab'),
 (3,'RPL-KOM-003','PC Desktop Lama',1,'Acer','Veriton S',NULL,6,'unit','rusak_ringan',4,'2019-08-10','Dana BOS',4800000,1,'Spesifikasi rendah, sebagian perlu perbaikan'),
 (4,'RPL-MON-001','Monitor LED 19 inci',2,'Dell','E1920H',NULL,20,'unit','baik',1,'2023-07-15','Dana BOS',1350000,2,NULL),
 (5,'RPL-MON-002','Monitor LED 17 inci',2,'LG','17M38',NULL,4,'unit','rusak_berat',2,'2018-06-01','Dana BOS',950000,0,'Menunggu proses penghapusan'),
 (6,'RPL-INP-001','Keyboard USB',3,'Logitech','K120',NULL,28,'unit','baik',1,DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'Dana BOS',95000,5,NULL),
 (7,'RPL-INP-002','Mouse Optik USB',3,'Logitech','B100',NULL,30,'unit','baik',1,DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'Dana BOS',65000,5,NULL),
 (8,'RPL-JAR-001','Router Wireless',4,'TP-Link','Archer C6','TPL-C6-2301',5,'unit','baik',5,DATE_SUB(CURDATE(), INTERVAL 3 MONTH),'Dana BOS',650000,1,'Untuk praktik jaringan'),
 (9,'RPL-JAR-002','Switch 24 Port',4,'D-Link','DGS-1024D','DL-1024-8841',2,'unit','baik',2,DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'Dana BOS',1450000,2,NULL),
 (10,'RPL-PRN-001','Printer Inkjet',5,'Epson','L3210','EPS-L3210-0097',2,'unit','baik',6,'2022-03-10','Dana BOS',2400000,1,NULL),
 (11,'RPL-PRJ-001','Proyektor',6,'Epson','EB-X06','EPS-X06-5521',1,'unit','baik',5,'2022-03-10','Dana BOS',5200000,1,NULL),
 (12,'RPL-LIS-001','Stop Kontak 5 Lubang',7,'Broco','5L',NULL,11,'unit','baik',3,DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'Dana BOS',85000,3,NULL),
 (13,'RPL-PRB-001','Meja Praktik',8,NULL,NULL,NULL,10,'unit','baik',1,'2023-07-15','Dana BOS',1200000,1,NULL),
 (14,'RPL-LAN-001','Toolkit Jaringan (Crimping + LAN Tester)',9,'Krisbow','Set Jaringan',NULL,6,'set','baik',5,DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'Dana BOS',275000,1,NULL);

-- Transaksi stok. Kolom: no, tanggal, jenis, arah, item, jumlah, sebelum, sesudah, lokasi, sumber/tujuan, petugas, ket
INSERT INTO `stock_transactions`
 (`no_transaksi`,`tanggal`,`jenis`,`arah`,`item_id`,`jumlah`,`stok_sebelum`,`stok_sesudah`,`location_id`,`sumber_tujuan`,`petugas`,`keterangan`) VALUES
 ('TRX-2026-00001','2023-07-15','masuk_pengadaan','masuk',1,20,0,20,1,'Dana BOS','Petugas Lab RPL','Saldo awal pengadaan'),
 ('TRX-2026-00002',DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'masuk_hibah','masuk',2,10,0,10,5,'Hibah Dinas Pendidikan','Petugas Lab RPL','Hibah laptop'),
 ('TRX-2026-00003','2019-08-10','masuk_pengadaan','masuk',3,8,0,8,4,'Dana BOS','Petugas Lab RPL','Saldo awal pengadaan'),
 ('TRX-2026-00004',DATE_SUB(CURDATE(), INTERVAL 4 MONTH),'keluar_penghapusan','keluar',3,2,8,6,2,'Dihapus dari inventaris','Petugas Lab RPL','Rusak total, penghapusan disetujui kepala sekolah'),
 ('TRX-2026-00005','2023-07-15','masuk_pengadaan','masuk',4,20,0,20,1,'Dana BOS','Petugas Lab RPL','Saldo awal pengadaan'),
 ('TRX-2026-00006','2018-06-01','masuk_pengadaan','masuk',5,4,0,4,2,'Dana BOS','Petugas Lab RPL','Saldo awal pengadaan'),
 ('TRX-2026-00007',DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'masuk_pengadaan','masuk',6,30,0,30,1,'Dana BOS','Petugas Lab RPL','Pengadaan keyboard'),
 ('TRX-2026-00008',DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'keluar_penghapusan','keluar',6,2,30,28,2,'Dihapus dari inventaris','Petugas Lab RPL','Tombol rusak, tidak layak pakai'),
 ('TRX-2026-00009',DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'masuk_pengadaan','masuk',7,30,0,30,1,'Dana BOS','Petugas Lab RPL','Pengadaan mouse'),
 ('TRX-2026-00010',DATE_SUB(CURDATE(), INTERVAL 3 MONTH),'masuk_pengadaan','masuk',8,5,0,5,5,'Dana BOS','Petugas Lab RPL','Pengadaan router'),
 ('TRX-2026-00011',DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'masuk_pengadaan','masuk',9,2,0,2,2,'Dana BOS','Petugas Lab RPL','Pengadaan switch'),
 ('TRX-2026-00012','2022-03-10','masuk_pengadaan','masuk',10,2,0,2,6,'Dana BOS','Petugas Lab RPL','Saldo awal pengadaan'),
 ('TRX-2026-00013','2022-03-10','masuk_pengadaan','masuk',11,1,0,1,5,'Dana BOS','Petugas Lab RPL','Saldo awal pengadaan'),
 ('TRX-2026-00014',DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'masuk_pengadaan','masuk',12,12,0,12,3,'Dana BOS','Petugas Lab RPL','Pengadaan stop kontak'),
 ('TRX-2026-00015',DATE_SUB(CURDATE(), INTERVAL 10 DAY),'penyesuaian','keluar',12,1,12,11,3,'Stock opname','Petugas Lab RPL','Hasil pemeriksaan fisik: 1 unit tidak ditemukan'),
 ('TRX-2026-00016','2023-07-15','masuk_pengadaan','masuk',13,10,0,10,1,'Dana BOS','Petugas Lab RPL','Saldo awal pengadaan'),
 ('TRX-2026-00017',DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'masuk_pengadaan','masuk',14,6,0,6,5,'Dana BOS','Petugas Lab RPL','Pengadaan toolkit jaringan');

-- Peminjaman
INSERT INTO `loans`
 (`no_peminjaman`,`nama_peminjam`,`nomor_induk`,`jenis_peminjam`,`kelas_bagian`,`item_id`,`jumlah`,`jumlah_dikembalikan`,`tanggal_pinjam`,`tanggal_rencana_kembali`,`tanggal_kembali_aktual`,`kondisi_kembali`,`status`,`petugas`,`keterangan`) VALUES
 ('PJM-2026-00001','Rizky Pratama','2324001','siswa','XII RPL 1',2,2,0,DATE_SUB(CURDATE(), INTERVAL 3 DAY),DATE_ADD(CURDATE(), INTERVAL 4 DAY),NULL,NULL,'dipinjam','Petugas Lab RPL','Untuk lomba LKS'),
 ('PJM-2026-00002','Budi Santoso, S.Kom.','198503122010011005','guru','Produktif RPL',11,1,0,DATE_SUB(CURDATE(), INTERVAL 10 DAY),DATE_SUB(CURDATE(), INTERVAL 5 DAY),NULL,NULL,'dipinjam','Petugas Lab RPL','Dipakai di kelas X TKJ'),
 ('PJM-2026-00003','Siti Aminah','2324017','siswa','XI RPL 2',6,4,2,DATE_SUB(CURDATE(), INTERVAL 8 DAY),DATE_SUB(CURDATE(), INTERVAL 4 DAY),DATE_SUB(CURDATE(), INTERVAL 5 DAY),'baik','sebagian','Petugas Lab RPL','Dikembalikan 2 dari 4 unit'),
 ('PJM-2026-00004','Dewi Lestari','2224033','siswa','XII RPL 2',7,5,5,DATE_SUB(CURDATE(), INTERVAL 20 DAY),DATE_SUB(CURDATE(), INTERVAL 15 DAY),DATE_SUB(CURDATE(), INTERVAL 15 DAY),'baik','dikembalikan','Petugas Lab RPL',NULL),
 ('PJM-2026-00005','Agus Setiawan','2324009','siswa','XI RPL 1',2,1,1,DATE_SUB(CURDATE(), INTERVAL 30 DAY),DATE_SUB(CURDATE(), INTERVAL 25 DAY),DATE_SUB(CURDATE(), INTERVAL 24 DAY),'rusak_ringan','dikembalikan','Petugas Lab RPL','Dikembalikan terlambat 1 hari, engsel longgar');

-- Pemeliharaan
INSERT INTO `maintenance`
 (`item_id`,`jumlah`,`tanggal_periksa`,`jenis`,`deskripsi_kerusakan`,`tindakan`,`teknisi`,`biaya`,`status`,`tanggal_selesai`,`keterangan`) VALUES
 (3,2,DATE_SUB(CURDATE(), INTERVAL 6 DAY),'perbaikan','Power supply mati, PC tidak menyala','Penggantian power supply','Hendra Wijaya',350000,'dalam_proses',NULL,'Menunggu suku cadang'),
 (5,4,DATE_SUB(CURDATE(), INTERVAL 40 DAY),'perbaikan','Panel monitor pecah dan muncul garis vertikal','Pengecekan panel dan kabel','Hendra Wijaya',0,'tidak_dapat_diperbaiki',DATE_SUB(CURDATE(), INTERVAL 38 DAY),'Biaya perbaikan melebihi nilai barang'),
 (8,1,DATE_SUB(CURDATE(), INTERVAL 20 DAY),'perawatan','Perawatan rutin','Pembaruan firmware dan pembersihan perangkat','Hendra Wijaya',0,'selesai',DATE_SUB(CURDATE(), INTERVAL 20 DAY),NULL),
 (1,2,DATE_SUB(CURDATE(), INTERVAL 2 DAY),'pemeriksaan','Pemeriksaan berkala sebelum ujian kompetensi','','Petugas Lab RPL',0,'menunggu',NULL,NULL),
 (4,1,DATE_SUB(CURDATE(), INTERVAL 1 DAY),'perbaikan','Layar berkedip saat dinyalakan','','Hendra Wijaya',0,'menunggu',NULL,'Belum dicek teknisi');
