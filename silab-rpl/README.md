# SILAB-RPL
**Sistem Informasi Inventaris Laboratorium Rekayasa Perangkat Lunak**
SMK Negeri 1 Ampelgading

Aplikasi web untuk laboran mengelola inventaris laboratorium RPL: data barang, kategori, lokasi, barang masuk/keluar, peminjaman & pengembalian, pemeliharaan, laporan (cetak & CSV), serta pengelolaan pengguna.

**Teknologi:** PHP 8 (native, terstruktur) · MySQL/MariaDB · Bootstrap 5 · Chart.js · Bootstrap Icons.
Semua pustaka frontend sudah disertakan di `assets/vendor/`, sehingga **berjalan tanpa internet** dan tanpa layanan cloud.

---

## 1. Persyaratan
- XAMPP dengan **PHP 8.0 atau lebih baru** (disarankan 8.1+), Apache, dan MySQL/MariaDB.
- Peramban modern (Chrome, Edge, Firefox).

## 2. Instalasi di XAMPP

1. Jalankan **XAMPP Control Panel**, lalu **Start** modul **Apache** dan **MySQL**.
2. Ekstrak ZIP ini sehingga foldernya menjadi:
   `C:\xampp\htdocs\silab-rpl\` (di Linux/macOS: `/opt/lampp/htdocs/silab-rpl/`).
3. Pilih **salah satu** cara menyiapkan database:

   **Cara A - otomatis (disarankan)**
   1. Buka `http://localhost/silab-rpl/install.php`.
   2. Isi nama lengkap, username, dan password administrator (min. 8 karakter).
   3. Biarkan kotak **Muat data contoh** tercentang bila ingin data uji, lalu klik **Pasang & buat akun**.
   4. Skrip membuat database `inventaris_lab_rpl`, ketujuh tabel, (opsional) data contoh, dan akun administrator Anda.

   **Cara B - manual lewat phpMyAdmin**
   1. Buka `http://localhost/phpmyadmin` → tab **Import** → pilih `database/schema.sql` → **Go**.
   2. (Opsional, data contoh) Import `database/seed.sql` dengan cara yang sama.
   3. Buka `http://localhost/silab-rpl/install.php` untuk membuat akun administrator (kotak data contoh tidak muncul bila barang sudah ada).

4. Buka `http://localhost/silab-rpl/` dan login dengan akun administrator yang Anda buat.
5. **Hapus file `install.php`** setelah selesai. (Halaman ini sudah otomatis terkunci begitu ada akun pengguna.)

> **Tidak ada password bawaan.** Password administrator dibuat oleh Anda saat instalasi dan disimpan sebagai hash (`password_hash`). File SQL tidak memuat akun pengguna.

### Pengaturan koneksi
Seluruh konfigurasi koneksi ada **di satu file**: `config/config.php` (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`). Bawaan XAMPP: user `root`, password kosong. Alamat dasar aplikasi (`BASE_URL`) terdeteksi otomatis; bila perlu, set manual di file yang sama.

### Masalah umum
| Gejala | Solusi |
|---|---|
| "Database tidak dapat diakses" | Pastikan MySQL sudah Start dan nilai `DB_*` di `config/config.php` benar. |
| Tampilan tanpa gaya/ikon | Pastikan folder diberi nama `silab-rpl` dan berada di `htdocs`; atau set `BASE_URL` manual. |
| Foto tidak terunggah | Pastikan folder `uploads/items/` dapat ditulis oleh Apache. |
| Lupa password admin | Buat hash baru: `php -r "echo password_hash('PasswordBaru', PASSWORD_DEFAULT);"` lalu perbarui kolom `password_hash` tabel `users` lewat phpMyAdmin. |

---

## 3. Struktur folder
```
silab-rpl/
├── config/config.php        satu-satunya konfigurasi + koneksi PDO
├── includes/                init, fungsi bantu, logika stok, laporan, header/footer
├── auth/profile.php         ganti password sendiri
├── dashboard/               ringkasan, grafik, aktivitas, tindak lanjut
├── categories/  locations/  master kategori & lokasi
├── items/                   daftar, form tambah/ubah, detail + riwayat
├── transactions/            barang masuk / keluar / penyesuaian stok
├── loans/                   peminjaman, pengembalian, perpanjangan
├── maintenance/             pemeliharaan & kerusakan
├── reports/                 10 jenis laporan, cetak, CSV
├── users/                   pengelolaan pengguna (admin)
├── assets/css, js, vendor   gaya, skrip, Bootstrap/Icons/Chart.js lokal
├── database/schema.sql      DDL 7 tabel + view stok
├── database/seed.sql        data contoh
├── uploads/items/           foto barang (eksekusi PHP diblokir)
├── install.php  login.php  logout.php  search.php  index.php
```

## 4. Desain database
Database `inventaris_lab_rpl`, mesin InnoDB, utf8mb4:

`users` · `categories` · `locations` · `items` · `stock_transactions` · `loans` · `maintenance` (+ view `v_item_stock`)

- `items.category_id` & `items.location_id` → FK (RESTRICT): kategori/lokasi yang dipakai tidak bisa dihapus.
- `stock_transactions.item_id`, `loans.item_id`, `maintenance.item_id` → FK RESTRICT: barang dengan riwayat tidak bisa dihapus (hanya dinonaktifkan).
- Kolom `user_id` pada transaksi/peminjaman/pemeliharaan → FK `ON DELETE SET NULL`; nama petugas disimpan juga sebagai teks agar riwayat utuh.
- `kode_barang`, `no_transaksi`, `no_peminjaman`, `username`, nama kategori, nama lokasi: **UNIQUE**.
- Indeks pada nama, nomor seri, kondisi, tanggal, status, dan kolom FK untuk pencarian.

### Model stok (satu sumber data)
| Besaran | Sumber |
|---|---|
| **Jumlah fisik** | `items.jumlah_total` - hanya berubah lewat `stock_transactions` (setiap perubahan tercatat: stok sebelum → sesudah). |
| **Dipinjam** | dihitung: Σ(`jumlah` − `jumlah_dikembalikan`) pada `loans` yang belum `dikembalikan`. Peminjaman **tidak** mengurangi stok fisik, jadi tidak ada pengurangan ganda. |
| **Dalam pemeliharaan** | dihitung: Σ`jumlah` pada `maintenance` jenis perawatan/perbaikan berstatus menunggu/dalam proses. |
| **Tersedia** | `fisik − dipinjam − pemeliharaan` (0 bila kondisi barang *rusak berat*). Semuanya dihitung di view `v_item_stock`. |

Seluruh operasi stok berjalan dalam **transaksi database** dengan penguncian baris barang (`SELECT ... FOR UPDATE`), sehingga stok tidak negatif dan tidak terpecah bila terjadi kegagalan.

## 5. Peran & hak akses
| Fitur | Administrator | Laboran |
|---|:-:|:-:|
| Dashboard, barang, transaksi, peminjaman, pemeliharaan, laporan | ✔ | ✔ |
| Kategori & lokasi: lihat, tambah, ubah | ✔ | ✔ |
| Kategori & lokasi: hapus | ✔ | ✘ |
| Hapus catatan peminjaman salah input (belum ada pengembalian) | ✔ | ✘ |
| Kelola pengguna | ✔ | ✘ |

Aturan integritas: barang yang sudah punya riwayat tidak dihapus (gunakan **Nonaktifkan**); transaksi stok tidak dapat diubah/dihapus (koreksi dengan transaksi *penyesuaian*); administrator tidak dapat menghapus/menonaktifkan akun sendiri atau administrator aktif terakhir; akun nonaktif tidak bisa login dan sesi aktifnya langsung ditolak.

Catatan: kondisi barang (baik / rusak ringan / rusak berat) berlaku untuk satu baris barang. Bila sebagian unit rusak, pisahkan menjadi baris barang tersendiri (mis. kode `...-RSK`) atau catat melalui pemeliharaan.

## 6. Keamanan
Prepared statements (PDO) untuk seluruh query · escaping output (`htmlspecialchars`) · token CSRF pada semua formulir POST (termasuk logout) · `password_hash`/`password_verify` · session cookie `HttpOnly` + `SameSite` + regenerasi ID saat login · pembatasan percobaan login · validasi server (jumlah positif, harga, tanggal, tipe & ukuran foto) · `.htaccess` memblokir akses langsung ke `config/`, `includes/`, `database/` dan eksekusi PHP di `uploads/` · CSV dilindungi dari *formula injection*.

---

## 7. Panduan pengujian
Gunakan data contoh (`seed.sql`). Login sebagai administrator kecuali dinyatakan lain.

| # | Uji | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| 1 | Login/logout | Salah password → benar → klik **Logout** | Pesan galat; masuk dashboard; kembali ke login. Membuka `/dashboard/` tanpa login diarahkan ke login. |
| 2 | Dashboard dari DB | Buka Dashboard; ubah data (mis. tambah transaksi) lalu muat ulang | Kartu & 3 grafik berubah sesuai data. |
| 3 | Tambah barang | Data Barang → Tambah (jumlah awal 4, penerimaan Hibah) | Barang tersimpan; di tab Transaksi detail muncul transaksi masuk 0 → 4. |
| 4 | Kode duplikat | Tambah barang dengan kode yang sudah ada | Ditolak dengan pesan. |
| 5 | Cari & filter | Cari `lenovo`, nomor seri `TPL-C6-2301`; filter kategori/kondisi/lokasi/stok | Daftar menyempit; pencarian global (kotak atas) memuat barang & peminjam. |
| 6 | Hapus barang | Hapus barang yang punya riwayat | Ditolak; gunakan Nonaktifkan (riwayat tetap). |
| 7 | Barang masuk | Transaksi Stok → Masuk Hibah, 5 unit | Stok fisik +5; tercatat nomor `TRX-...`. |
| 8 | Stok tidak negatif | Transaksi Keluar dengan jumlah > stok | Ditolak; stok tidak berubah. |
| 9 | Batas unit dipinjam | Keluar/penyesuaian sehingga stok < unit yang sedang dipinjam | Ditolak. |
| 10 | Penyesuaian | Jenis Penyesuaian, isi stok fisik hasil opname, kosongkan keterangan → isi keterangan | Wajib alasan; selisih dihitung otomatis. |
| 11 | Peminjaman | Pinjam 3 unit barang yang tersedia 9 | Tersedia menjadi 6; stok fisik tetap 9; `jumlah_dipinjam` 3. |
| 12 | Pinjam > tersedia | Pinjam lebih dari tersedia, atau proyektor contoh (tersedia 0) | Ditolak. |
| 13 | Pengembalian | Buka detail pinjaman → kembalikan sebagian → sisanya | Status *Dikembalikan sebagian* → *Dikembalikan*; tersedia pulih. |
| 14 | Rusak saat kembali | Kembalikan dengan kondisi *Rusak ringan* | Diarahkan ke form pemeliharaan terisi otomatis. |
| 15 | Terlambat | Lihat tab **Terlambat** pada Peminjaman | Pinjaman lewat tanggal rencana tampil merah, juga di dashboard & laporan. |
| 16 | Pemeliharaan | Catat Perbaikan 2 unit status *Dalam proses* | Tersedia berkurang 2 dan kartu "Dalam Pemeliharaan" naik; ubah ke *Selesai* → unit kembali tersedia. |
| 17 | Rusak berat | Lihat Monitor 17 inci (rusak berat) | Tersedia 0, tidak muncul di pilihan peminjaman. |
| 18 | Laporan | Laporan → pilih tiap jenis (10 jenis) + filter → Tampilkan | Data dari database, header sekolah/lab/periode/petugas. |
| 19 | Cetak | Tombol **Cetak** | Pratinjau cetak tanpa sidebar/menu (A4 lanskap). |
| 20 | Ekspor CSV | Tombol **CSV (Excel)** | File terunduh dan terbuka rapi di Excel (kolom terpisah). |
| 21 | Pengguna | Menu Pengguna → tambah Laboran; nonaktifkan; coba hapus diri sendiri / admin terakhir | Laboran tidak bisa mengakses menu Pengguna (403); akun nonaktif tidak bisa login; penghapusan terlarang ditolak. |
| 22 | Kategori/lokasi | Hapus kategori yang dipakai; tambah nama ganda | Ditolak dengan pesan. Laboran tidak melihat tombol hapus. |
| 23 | Keamanan | Kirim formulir tanpa token CSRF; masukkan `<script>` pada nama lokasi; `' OR 1=1 --` pada pencarian | HTTP 419; teks tampil apa adanya; tidak ada efek SQL. |
| 24 | Unggah foto | Unggah PNG/JPG < 2 MB; coba unggah berkas `.php` | Foto tampil; berkas bukan gambar ditolak. |

Pemeriksaan konsistensi stok langsung di phpMyAdmin (hasil harus **kosong**):
```sql
SELECT i.id, i.kode_barang, i.jumlah_total,
       (SELECT SUM(IF(arah='masuk', jumlah, -jumlah)) FROM stock_transactions t WHERE t.item_id = i.id) AS dari_transaksi
FROM items i
HAVING jumlah_total <> IFNULL(dari_transaksi, 0);
```

## 8. Catatan pengembangan
- Tambah `BASE_URL` manual di `config/config.php` bila aplikasi dipasang di subfolder berbeda.
- Status "Terlambat" dihitung saat tampil (tanggal rencana kembali < hari ini dan belum dikembalikan penuh), bukan disimpan, sehingga selalu akurat.
- Nomor otomatis berformat `TRX-TAHUN-00001` dan `PJM-TAHUN-00001` (berurutan per tahun).
