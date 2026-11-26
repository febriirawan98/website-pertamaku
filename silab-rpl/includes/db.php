<?php
declare(strict_types=1);

/**
 * Koneksi database (PDO) dan fungsi bantu query.
 * Semua query yang menerima input pengguna memakai prepared statements.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Samakan zona waktu MySQL dengan PHP agar CURDATE() konsisten.
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    return $pdo;
}

/** Menjalankan query dengan parameter posisi (?). */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $i = 1;
    foreach ($params as $v) {
        if (is_int($v)) {
            $st->bindValue($i, $v, PDO::PARAM_INT);
        } elseif (is_bool($v)) {
            $st->bindValue($i, $v ? 1 : 0, PDO::PARAM_INT);
        } elseif ($v === null) {
            $st->bindValue($i, null, PDO::PARAM_NULL);
        } else {
            $st->bindValue($i, (string) $v, PDO::PARAM_STR);
        }
        $i++;
    }
    $st->execute();
    return $st;
}

function fetch_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function fetch_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function fetch_value(string $sql, array $params = []): mixed
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function last_id(): int
{
    return (int) db()->lastInsertId();
}

/**
 * Menjalankan $fn di dalam transaksi database. Jika sudah berada dalam
 * transaksi, $fn dijalankan apa adanya (bergabung dengan transaksi luar).
 * Bila terjadi exception, seluruh perubahan dibatalkan (rollback).
 * READ COMMITTED dipakai supaya setelah baris barang dikunci (FOR UPDATE)
 * pembacaan berikutnya selalu melihat data terbaru.
 */
function with_tx(callable $fn): mixed
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        return $fn();
    }
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    $pdo->beginTransaction();
    try {
        $result = $fn();
        $pdo->commit();
        return $result;
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $t;
    }
}
