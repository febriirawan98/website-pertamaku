<?php
/**
 * Logika stok, peminjaman, dan pemeliharaan.
 *
 * Prinsip (satu sumber data stok):
 *  - items.jumlah_total hanya berubah melalui record_stock_transaction().
 *  - Peminjaman TIDAK mengurangi jumlah_total; jumlah dipinjam dihitung
 *    dari tabel loans pada view v_item_stock (tidak pernah dikurangi dua kali).
 *  - Pemeliharaan (perawatan/perbaikan aktif) menahan unit di v_item_stock.
 *  - Semua operasi menulis dalam transaksi database dan mengunci baris barang.
 */

/** Kunci baris barang (FOR UPDATE). Harus dipanggil di dalam transaksi, sebelum membaca stok. */
function lock_item(int $id): void
{
    db_val('SELECT id FROM items WHERE id = ? FOR UPDATE', [$id]);
}

function item_with_stock(int $id): ?array
{
    return db_row(
        'SELECT i.*, s.jumlah_dipinjam, s.jumlah_pemeliharaan, s.jumlah_tersedia
           FROM items i JOIN v_item_stock s ON s.item_id = i.id WHERE i.id = ?',
        [$id]
    );
}

/** Nomor otomatis: PREFIX-TAHUN-00001 (berurutan per tahun). */
function next_number(string $table, string $col, string $prefix): string
{
    $year = date('Y');
    $max = db_val("SELECT MAX(`$col`) FROM `$table` WHERE `$col` LIKE ?", ["$prefix-$year-%"]);
    $n = $max ? ((int)substr($max, -5)) + 1 : 1;
    return sprintf('%s-%s-%05d', $prefix, $year, $n);
}

/** Jalankan callback dalam transaksi; rollback bila ada kegagalan. */
function in_transaction(callable $fn)
{
    $pdo = db();
    if ($pdo->inTransaction()) {   // bersarang: ikut transaksi pemanggil
        return $fn();
    }
    $pdo->beginTransaction();
    try {
        $r = $fn();
        $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Catat transaksi stok dan perbarui jumlah_total secara atomik.
 * $d: tanggal, jenis, item_id, jumlah | stok_baru (penyesuaian), location_id, sumber_tujuan, keterangan
 * @return string nomor transaksi
 * @throws DomainException bila validasi bisnis gagal
 */
function record_stock_transaction(array $d, array $user): string
{
    return in_transaction(function () use ($d, $user) {
        $itemId = (int)$d['item_id'];
        lock_item($itemId);
        $item = item_with_stock($itemId);
        if (!$item) {
            throw new DomainException('Barang tidak ditemukan.');
        }
        if (!(int)$item['is_active']) {
            throw new DomainException('Barang sudah dinonaktifkan. Aktifkan kembali sebelum membuat transaksi.');
        }
        $jenis  = $d['jenis'];
        $before = (int)$item['jumlah_total'];

        if ($jenis === 'penyesuaian') {
            $after = (int)$d['stok_baru'];
            if ($after < 0) {
                throw new DomainException('Hasil stok fisik tidak boleh negatif.');
            }
            if ($after === $before) {
                throw new DomainException("Stok fisik yang dimasukkan sama dengan stok sistem ($before). Tidak ada penyesuaian.");
            }
            if (trim((string)$d['keterangan']) === '') {
                throw new DomainException('Alasan penyesuaian wajib diisi.');
            }
            $arah   = $after > $before ? 'masuk' : 'keluar';
            $jumlah = abs($after - $before);
        } else {
            $jumlah = (int)$d['jumlah'];
            if ($jumlah <= 0) {
                throw new DomainException('Jumlah harus lebih dari 0.');
            }
            $arah  = str_starts_with($jenis, 'masuk') ? 'masuk' : 'keluar';
            $after = $arah === 'masuk' ? $before + $jumlah : $before - $jumlah;
        }
        if ($after < 0) {
            throw new DomainException("Stok tidak mencukupi. Stok saat ini $before, jumlah keluar $jumlah.");
        }
        $terpakai = (int)$item['jumlah_dipinjam'] + (int)$item['jumlah_pemeliharaan'];
        if ($after < $terpakai) {
            throw new DomainException("Stok tidak boleh kurang dari $terpakai unit yang sedang dipinjam/dalam pemeliharaan. Kembalikan atau selesaikan dahulu.");
        }

        db_exec('UPDATE items SET jumlah_total = ? WHERE id = ?', [$after, $itemId]);

        for ($try = 0; $try < 5; $try++) {
            $no = next_number('stock_transactions', 'no_transaksi', 'TRX');
            try {
                db_exec(
                    'INSERT INTO stock_transactions (no_transaksi, tanggal, jenis, arah, item_id, jumlah, stok_sebelum, stok_sesudah,
                        location_id, sumber_tujuan, user_id, petugas, keterangan)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$no, $d['tanggal'], $jenis, $arah, $itemId, $jumlah, $before, $after,
                     $d['location_id'] ?: null, $d['sumber_tujuan'] ?: null, $user['id'], $user['nama'], $d['keterangan'] ?: null]
                );
                return $no;
            } catch (PDOException $e) {
                if (!is_duplicate_error($e) || $try === 4) {
                    throw $e;
                }
            }
        }
        throw new RuntimeException('Gagal membuat nomor transaksi.');
    });
}

/**
 * Catat peminjaman baru. Validasi jumlah <= jumlah tersedia (sudah dikurangi
 * unit dipinjam & dalam pemeliharaan). TIDAK mengubah items.jumlah_total.
 */
function create_loan(array $d, array $user): string
{
    return in_transaction(function () use ($d, $user) {
        $itemId = (int)$d['item_id'];
        lock_item($itemId);
        $item = item_with_stock($itemId);
        if (!$item || !(int)$item['is_active']) {
            throw new DomainException('Barang tidak ditemukan atau sudah dinonaktifkan.');
        }
        if ($item['kondisi'] === 'rusak_berat') {
            throw new DomainException('Barang berkondisi rusak berat tidak dapat dipinjam.');
        }
        $jumlah = (int)$d['jumlah'];
        if ($jumlah <= 0) {
            throw new DomainException('Jumlah pinjam harus lebih dari 0.');
        }
        if ($jumlah > (int)$item['jumlah_tersedia']) {
            throw new DomainException('Jumlah melebihi stok tersedia (' . (int)$item['jumlah_tersedia'] . ' ' . $item['satuan']
                . '). Dipinjam: ' . (int)$item['jumlah_dipinjam'] . ', pemeliharaan: ' . (int)$item['jumlah_pemeliharaan'] . '.');
        }
        for ($try = 0; $try < 5; $try++) {
            $no = next_number('loans', 'no_peminjaman', 'PJM');
            try {
                db_exec(
                    'INSERT INTO loans (no_peminjaman, nama_peminjam, nomor_induk, jenis_peminjam, kelas_bagian, item_id, jumlah,
                        tanggal_pinjam, tanggal_rencana_kembali, status, user_id, petugas, keterangan)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$no, $d['nama_peminjam'], $d['nomor_induk'] ?: null, $d['jenis_peminjam'], $d['kelas_bagian'] ?: null,
                     $itemId, $jumlah, $d['tanggal_pinjam'], $d['tanggal_rencana_kembali'], 'dipinjam',
                     $user['id'], $user['nama'], $d['keterangan'] ?: null]
                );
                return $no;
            } catch (PDOException $e) {
                if (!is_duplicate_error($e) || $try === 4) {
                    throw $e;
                }
            }
        }
        throw new RuntimeException('Gagal membuat nomor peminjaman.');
    });
}

/**
 * Catat pengembalian (penuh atau sebagian).
 * @return array{loan: array, selesai: bool}
 */
function return_loan(int $loanId, int $qty, string $tanggal, string $kondisi, string $catatan): array
{
    return in_transaction(function () use ($loanId, $qty, $tanggal, $kondisi, $catatan) {
        $loan = db_row('SELECT * FROM loans WHERE id = ? FOR UPDATE', [$loanId]);
        if (!$loan) {
            throw new DomainException('Data peminjaman tidak ditemukan.');
        }
        $sisa = (int)$loan['jumlah'] - (int)$loan['jumlah_dikembalikan'];
        if ($loan['status'] === 'dikembalikan' || $sisa <= 0) {
            throw new DomainException('Peminjaman ini sudah dikembalikan seluruhnya.');
        }
        if ($qty <= 0 || $qty > $sisa) {
            throw new DomainException("Jumlah pengembalian harus antara 1 dan $sisa.");
        }
        if ($tanggal < $loan['tanggal_pinjam']) {
            throw new DomainException('Tanggal pengembalian tidak boleh sebelum tanggal pinjam.');
        }
        $baru   = (int)$loan['jumlah_dikembalikan'] + $qty;
        $status = $baru >= (int)$loan['jumlah'] ? 'dikembalikan' : 'sebagian';
        $note   = trim(($loan['catatan_kembali'] ? $loan['catatan_kembali'] . "\n" : '')
                . '[' . fmt_date($tanggal) . '] ' . $qty . ' unit, kondisi ' . (kondisi_labels()[$kondisi] ?? $kondisi)
                . ($catatan !== '' ? ': ' . $catatan : ''));
        db_exec(
            'UPDATE loans SET jumlah_dikembalikan = ?, status = ?, tanggal_kembali_aktual = ?, kondisi_kembali = ?, catatan_kembali = ? WHERE id = ?',
            [$baru, $status, $tanggal, $kondisi, $note, $loanId]
        );
        $loan['status'] = $status;
        return ['loan' => $loan, 'selesai' => $status === 'dikembalikan'];
    });
}

function maint_holds_units(string $jenis, string $status): bool
{
    return in_array($jenis, ['perawatan', 'perbaikan'], true) && in_array($status, ['menunggu', 'dalam_proses'], true);
}

/**
 * Simpan (tambah/ubah) catatan pemeliharaan. Bila menahan unit, jumlahnya
 * divalidasi terhadap unit yang tidak sedang dipinjam / dipelihara lainnya.
 * @return int id catatan
 */
function save_maintenance(?int $id, array $d, array $user): int
{
    return in_transaction(function () use ($id, $d, $user) {
        $itemId = (int)$d['item_id'];
        lock_item($itemId);
        $item = item_with_stock($itemId);
        if (!$item) {
            throw new DomainException('Barang tidak ditemukan.');
        }
        $jumlah = (int)$d['jumlah'];
        if ($jumlah <= 0 || $jumlah > (int)$item['jumlah_total']) {
            throw new DomainException('Jumlah unit harus antara 1 dan ' . (int)$item['jumlah_total'] . '.');
        }
        $holdsNew = maint_holds_units($d['jenis'], $d['status']);
        if ($holdsNew) {
            $pemLain = (int)$item['jumlah_pemeliharaan'];
            if ($id) {
                $old = db_row('SELECT item_id, jumlah, jenis, status FROM maintenance WHERE id = ?', [$id]);
                if ($old && (int)$old['item_id'] === $itemId && maint_holds_units($old['jenis'], $old['status'])) {
                    $pemLain -= (int)$old['jumlah'];
                }
            }
            $bebas = (int)$item['jumlah_total'] - (int)$item['jumlah_dipinjam'] - $pemLain;
            if ($jumlah > $bebas) {
                throw new DomainException("Hanya $bebas unit yang dapat dipelihara (sisanya sedang dipinjam atau sudah dalam pemeliharaan lain).");
            }
        }
        $tglSelesai = in_array($d['status'], ['selesai', 'tidak_dapat_diperbaiki'], true)
            ? ($d['tanggal_selesai'] ?: today()) : null;
        $params = [$itemId, $jumlah, $d['tanggal_periksa'], $d['jenis'], $d['deskripsi_kerusakan'] ?: null, $d['tindakan'] ?: null,
                   $d['teknisi'], $d['biaya'], $d['status'], $tglSelesai, $d['keterangan'] ?: null];
        if ($id) {
            db_exec('UPDATE maintenance SET item_id=?, jumlah=?, tanggal_periksa=?, jenis=?, deskripsi_kerusakan=?, tindakan=?, teknisi=?,
                        biaya=?, status=?, tanggal_selesai=?, keterangan=? WHERE id=?', array_merge($params, [$id]));
            return $id;
        }
        db_exec('INSERT INTO maintenance (item_id, jumlah, tanggal_periksa, jenis, deskripsi_kerusakan, tindakan, teknisi, biaya, status,
                    tanggal_selesai, keterangan, user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', array_merge($params, [$user['id']]));
        return (int)db()->lastInsertId();
    });
}
