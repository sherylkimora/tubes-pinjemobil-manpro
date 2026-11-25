<?php
declare(strict_types=1);

namespace Pinjemobil;

/** Pembungkus ekstensi sqlsrv (sama dengan yang dipakai koneksi.php lama). Koneksi dibuat lazy. */
final class Database
{
    /** @var resource|null */
    private $conn = null;

    public function __construct(private readonly array $cfg)
    {
    }

    private function connect()
    {
        if ($this->conn) {
            return $this->conn;
        }
        if (!function_exists('sqlsrv_connect')) {
            throw new \RuntimeException('Ekstensi PHP sqlsrv belum aktif (cek php.ini / docs/SETUP.md).');
        }
        $opts = [
            'Database'               => $this->cfg['name'],
            'TrustServerCertificate' => $this->cfg['trust_cert'],
            'CharacterSet'           => 'UTF-8',
            'ReturnDatesAsStrings'   => true,
        ];
        if ($this->cfg['user'] !== '') {
            $opts['UID'] = $this->cfg['user'];
            $opts['PWD'] = $this->cfg['pass'];
        }
        $conn = sqlsrv_connect($this->cfg['server'], $opts);
        if (!$conn) {
            error_log('[db] connect gagal: ' . json_encode(sqlsrv_errors()));
            throw new \RuntimeException('Tidak dapat terhubung ke database.');
        }
        return $this->conn = $conn;
    }

    private function run(string $sql, array $params)
    {
        $stmt = sqlsrv_query($this->connect(), $sql, $params);
        if ($stmt === false) {
            error_log('[db] query gagal: ' . json_encode(sqlsrv_errors()) . ' SQL=' . $sql);
            throw new \RuntimeException('Terjadi kesalahan pada database.');
        }
        return $stmt;
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = sqlsrv_fetch_array($this->run($sql, $params), SQLSRV_FETCH_ASSOC);
        return $row ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->run($sql, $params);
        $rows = [];
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $r;
        }
        return $rows;
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->run($sql, $params);
        return (int) sqlsrv_rows_affected($stmt);
    }

    /** Jalankan INSERT ... OUTPUT INSERTED.<pk> dan kembalikan nilai pk. */
    public function insertGetId(string $sqlWithOutput, array $params = []): int
    {
        $row = sqlsrv_fetch_array($this->run($sqlWithOutput, $params), SQLSRV_FETCH_NUMERIC);
        return (int) ($row[0] ?? 0);
    }

    public function transaction(callable $fn): mixed
    {
        sqlsrv_begin_transaction($this->connect());
        try {
            $result = $fn($this);
            sqlsrv_commit($this->connect());
            return $result;
        } catch (\Throwable $e) {
            sqlsrv_rollback($this->connect());
            throw $e;
        }
    }
}
