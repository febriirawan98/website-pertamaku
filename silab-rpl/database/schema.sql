-- ============================================================
-- SILAB-RPL : Skema Database
-- SMK Negeri 1 Ampelgading
-- Impor melalui phpMyAdmin (tab "Import") atau lewat install.php
-- Kompatibel dengan MySQL 5.7+/8 dan MariaDB 10.3+ (XAMPP)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `inventaris_lab_rpl`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `inventaris_lab_rpl`;

SET FOREIGN_KEY_CHECKS = 0;
DROP VIEW  IF EXISTS `v_item_stock`;
DROP TABLE IF EXISTS `maintenance`;
DROP TABLE IF EXISTS `loans`;
DROP TABLE IF EXISTS `stock_transactions`;
DROP TABLE IF EXISTS `items`;
DROP TABLE IF EXISTS `locations`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Pengguna ------------------------------------------------
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_lengkap`  VARCHAR(100) NOT NULL,
  `username`      VARCHAR(50)  NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('admin','laboran') NOT NULL DEFAULT 'laboran',
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `last_login`    DATETIME NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Kategori ------------------------------------------------
CREATE TABLE `categories` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_kategori` VARCHAR(100) NOT NULL,
  `keterangan`    VARCHAR(255) NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_nama` (`nama_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Lokasi penyimpanan --------------------------------------
CREATE TABLE `locations` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_lokasi` VARCHAR(100) NOT NULL,
  `keterangan`  VARCHAR(255) NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_locations_nama` (`nama_lokasi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Barang --------------------------------------------------
-- `jumlah_total` = jumlah fisik. Satu-satunya sumber data stok.
-- Hanya diubah lewat stock_transactions. Jumlah dipinjam, dalam
-- pemeliharaan, dan tersedia dihitung oleh view v_item_stock.
CREATE TABLE `items` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_barang`      VARCHAR(30)  NOT NULL,
  `nama_barang`      VARCHAR(150) NOT NULL,
  `category_id`      INT UNSIGNED NOT NULL,
  `merek`            VARCHAR(80)  NULL,
  `model`            VARCHAR(80)  NULL,
  `nomor_seri`       VARCHAR(80)  NULL,
  `jumlah_total`     INT UNSIGNED NOT NULL DEFAULT 0,
  `satuan`           VARCHAR(20)  NOT NULL DEFAULT 'unit',
  `kondisi`          ENUM('baik','rusak_ringan','rusak_berat') NOT NULL DEFAULT 'baik',
  `location_id`      INT UNSIGNED NOT NULL,
  `tanggal_pengadaan` DATE NULL,
  `sumber_pengadaan` VARCHAR(100) NULL,
  `harga_satuan`     DECIMAL(15,2) NOT NULL DEFAULT 0,
  `stok_minimum`     INT UNSIGNED NOT NULL DEFAULT 1,
  `keterangan`       TEXT NULL,
  `foto`             VARCHAR(255) NULL,
  `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_items_kode` (`kode_barang`),
  KEY `idx_items_nama` (`nama_barang`),
  KEY `idx_items_serial` (`nomor_seri`),
  KEY `idx_items_kondisi` (`kondisi`),
  KEY `idx_items_category` (`category_id`),
  KEY `idx_items_location` (`location_id`),
  CONSTRAINT `fk_items_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_items_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Transaksi stok (barang masuk / keluar / penyesuaian) -----
CREATE TABLE `stock_transactions` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `no_transaksi`  VARCHAR(30) NOT NULL,
  `tanggal`       DATE NOT NULL,
  `jenis`         ENUM('masuk_pengadaan','masuk_hibah','keluar_pemindahan','keluar_retur','keluar_penghapusan','penyesuaian') NOT NULL,
  `arah`          ENUM('masuk','keluar') NOT NULL,
  `item_id`       INT UNSIGNED NOT NULL,
  `jumlah`        INT UNSIGNED NOT NULL,
  `stok_sebelum`  INT UNSIGNED NOT NULL,
  `stok_sesudah`  INT UNSIGNED NOT NULL,
  `location_id`   INT UNSIGNED NULL,
  `sumber_tujuan` VARCHAR(150) NULL,
  `user_id`       INT UNSIGNED NULL,
  `petugas`       VARCHAR(100) NOT NULL,
  `keterangan`    TEXT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trx_no` (`no_transaksi`),
  KEY `idx_trx_tanggal` (`tanggal`),
  KEY `idx_trx_item` (`item_id`),
  KEY `idx_trx_jenis` (`jenis`),
  CONSTRAINT `fk_trx_item`     FOREIGN KEY (`item_id`)     REFERENCES `items` (`id`)     ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_trx_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_trx_user`     FOREIGN KEY (`user_id`)     REFERENCES `users` (`id`)     ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_trx_jumlah`  CHECK (`jumlah` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Peminjaman ----------------------------------------------
-- Peminjaman TIDAK mengubah items.jumlah_total. Jumlah dipinjam
-- dihitung dari (jumlah - jumlah_dikembalikan) pada v_item_stock.
-- Status "terlambat" ditentukan saat tampil/laporan:
--   status <> 'dikembalikan' AND tanggal_rencana_kembali < hari ini.
CREATE TABLE `loans` (
  `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `no_peminjaman`          VARCHAR(30) NOT NULL,
  `nama_peminjam`          VARCHAR(100) NOT NULL,
  `nomor_induk`            VARCHAR(30) NULL,
  `jenis_peminjam`         ENUM('siswa','guru','petugas') NOT NULL DEFAULT 'siswa',
  `kelas_bagian`           VARCHAR(60) NULL,
  `item_id`                INT UNSIGNED NOT NULL,
  `jumlah`                 INT UNSIGNED NOT NULL,
  `jumlah_dikembalikan`    INT UNSIGNED NOT NULL DEFAULT 0,
  `tanggal_pinjam`         DATE NOT NULL,
  `tanggal_rencana_kembali` DATE NOT NULL,
  `tanggal_kembali_aktual` DATE NULL,
  `kondisi_kembali`        ENUM('baik','rusak_ringan','rusak_berat') NULL,
  `catatan_kembali`        TEXT NULL,
  `status`                 ENUM('dipinjam','sebagian','dikembalikan') NOT NULL DEFAULT 'dipinjam',
  `user_id`                INT UNSIGNED NULL,
  `petugas`                VARCHAR(100) NOT NULL,
  `keterangan`             TEXT NULL,
  `created_at`             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_loans_no` (`no_peminjaman`),
  KEY `idx_loans_item` (`item_id`),
  KEY `idx_loans_status` (`status`),
  KEY `idx_loans_nama` (`nama_peminjam`),
  KEY `idx_loans_rencana` (`tanggal_rencana_kembali`),
  CONSTRAINT `fk_loans_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_loans_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_loans_jumlah` CHECK (`jumlah` > 0 AND `jumlah_dikembalikan` <= `jumlah`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Pemeliharaan --------------------------------------------
-- Catatan dengan jenis perawatan/perbaikan dan status
-- menunggu/dalam_proses menahan `jumlah` unit (tidak tersedia).
CREATE TABLE `maintenance` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id`             INT UNSIGNED NOT NULL,
  `jumlah`              INT UNSIGNED NOT NULL DEFAULT 1,
  `tanggal_periksa`     DATE NOT NULL,
  `jenis`               ENUM('pemeriksaan','perawatan','perbaikan') NOT NULL DEFAULT 'pemeriksaan',
  `deskripsi_kerusakan` TEXT NULL,
  `tindakan`            TEXT NULL,
  `teknisi`             VARCHAR(100) NOT NULL,
  `biaya`               DECIMAL(15,2) NOT NULL DEFAULT 0,
  `status`              ENUM('menunggu','dalam_proses','selesai','tidak_dapat_diperbaiki') NOT NULL DEFAULT 'menunggu',
  `tanggal_selesai`     DATE NULL,
  `keterangan`          TEXT NULL,
  `user_id`             INT UNSIGNED NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mnt_item` (`item_id`),
  KEY `idx_mnt_status` (`status`),
  KEY `idx_mnt_tanggal` (`tanggal_periksa`),
  CONSTRAINT `fk_mnt_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_mnt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_mnt_jumlah` CHECK (`jumlah` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- View stok: satu tempat untuk menghitung ketersediaan ---------
--   jumlah_total        : jumlah fisik (items.jumlah_total)
--   jumlah_dipinjam     : unit yang belum dikembalikan
--   jumlah_pemeliharaan : unit yang sedang dirawat/diperbaiki
--   jumlah_tersedia     : siap dipinjam (0 bila kondisi rusak berat)
CREATE VIEW `v_item_stock` AS
SELECT t.`item_id`, t.`jumlah_total`, t.`jumlah_dipinjam`, t.`jumlah_pemeliharaan`,
       CASE WHEN t.`kondisi` = 'rusak_berat' THEN 0
            WHEN t.`jumlah_total` > t.`jumlah_dipinjam` + t.`jumlah_pemeliharaan`
              THEN t.`jumlah_total` - t.`jumlah_dipinjam` - t.`jumlah_pemeliharaan`
            ELSE 0 END AS `jumlah_tersedia`
FROM (
  SELECT i.`id` AS `item_id`, i.`jumlah_total`, i.`kondisi`,
    CAST(COALESCE((SELECT SUM(l.`jumlah` - l.`jumlah_dikembalikan`) FROM `loans` l
                   WHERE l.`item_id` = i.`id` AND l.`status` <> 'dikembalikan'), 0) AS SIGNED) AS `jumlah_dipinjam`,
    CAST(COALESCE((SELECT SUM(m.`jumlah`) FROM `maintenance` m
                   WHERE m.`item_id` = i.`id` AND m.`jenis` IN ('perawatan','perbaikan')
                     AND m.`status` IN ('menunggu','dalam_proses')), 0) AS SIGNED) AS `jumlah_pemeliharaan`
  FROM `items` i
) t;
