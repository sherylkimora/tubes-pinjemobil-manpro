<?php
declare(strict_types=1);

namespace Pinjemobil\Repositories;

use Pinjemobil\Database;

class UserRepository
{
    private const BASE = "SELECT u.id_user, u.nama, u.username, u.password, u.role, u.id_member, u.id_pegawai,
            u.is_active, u.failed_login_attempts, u.token_version,
            COALESCE(p.id_cabang, m.id_cabang) AS id_cabang,
            CASE WHEN u.locked_until IS NOT NULL AND u.locked_until > SYSDATETIME() THEN 1 ELSE 0 END AS is_locked,
            CASE WHEN u.locked_until IS NOT NULL AND u.locked_until > SYSDATETIME()
                 THEN DATEDIFF(MINUTE, SYSDATETIME(), u.locked_until) + 1 ELSE 0 END AS lock_minutes_left
        FROM users u
        LEFT JOIN pegawai p ON p.id_pegawai = u.id_pegawai
        LEFT JOIN member  m ON m.id_member  = u.id_member";

    public function __construct(protected readonly Database $db)
    {
    }

    public function findByUsername(string $username): ?array
    {
        return $this->db->fetchOne(self::BASE . ' WHERE u.username = ?', [$username]);
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(self::BASE . ' WHERE u.id_user = ?', [$id]);
    }

    public function usernameExists(string $username): bool
    {
        return $this->db->fetchOne('SELECT 1 AS x FROM users WHERE username = ?', [$username]) !== null;
    }

    public function memberEmailExists(string $email): bool
    {
        return $this->db->fetchOne('SELECT 1 AS x FROM member WHERE email = ?', [$email]) !== null;
    }

    public function cabangExists(int $id): bool
    {
        return $this->db->fetchOne('SELECT 1 AS x FROM cabang WHERE id_cabang = ?', [$id]) !== null;
    }

    public function findPegawai(int $id): ?array
    {
        return $this->db->fetchOne('SELECT id_pegawai, id_cabang, nama_pegawai FROM pegawai WHERE id_pegawai = ?', [$id]);
    }

    public function pegawaiHasAccount(int $idPegawai): bool
    {
        return $this->db->fetchOne('SELECT 1 AS x FROM users WHERE id_pegawai = ?', [$idPegawai]) !== null;
    }

    /** Registrasi mandiri customer: buat baris member + users dalam satu transaksi. */
    public function registerCustomer(array $d, string $hash): int
    {
        return $this->db->transaction(function (Database $db) use ($d, $hash) {
            $idMember = $db->insertGetId(
                'INSERT INTO member (nama_member, alamat, nomor_telepon, email, tanggal_registrasi, id_cabang)
                 OUTPUT INSERTED.id_member
                 VALUES (?, ?, ?, ?, CAST(GETDATE() AS DATE), ?)',
                [$d['nama_member'], $d['alamat'] ?? null, $d['nomor_telepon'], $d['email'], (int) $d['id_cabang']]
            );
            return $db->insertGetId(
                "INSERT INTO users (nama, username, password, role, id_member, id_pegawai)
                 OUTPUT INSERTED.id_user
                 VALUES (?, ?, ?, 'customer', ?, NULL)",
                [$d['nama_member'], $d['username'], $hash, $idMember]
            );
        });
    }

    /** Akun staf (admin_utama / admin_cabang / pegawai) yang dibuat admin. */
    public function createStaff(string $nama, string $username, string $hash, string $role, ?int $idPegawai): int
    {
        return $this->db->insertGetId(
            'INSERT INTO users (nama, username, password, role, id_member, id_pegawai)
             OUTPUT INSERTED.id_user VALUES (?, ?, ?, ?, NULL, ?)',
            [$nama, $username, $hash, $role, $idPegawai]
        );
    }

    public function listStaff(?int $idCabang, ?string $role): array
    {
        $sql = "SELECT u.id_user, u.nama, u.username, u.role, u.is_active, u.last_login_at,
                       p.id_cabang, c.nama_cabang
                FROM users u
                LEFT JOIN pegawai p ON p.id_pegawai = u.id_pegawai
                LEFT JOIN cabang c ON c.id_cabang = p.id_cabang
                WHERE u.role <> 'customer'";
        $params = [];
        if ($idCabang !== null) {
            $sql .= ' AND p.id_cabang = ?';
            $params[] = $idCabang;
        }
        if ($role !== null) {
            $sql .= ' AND u.role = ?';
            $params[] = $role;
        }
        return $this->db->fetchAll($sql . ' ORDER BY u.id_user', $params);
    }

    public function setActive(int $id, bool $active): void
    {
        // token_version dinaikkan agar token lama langsung tidak berlaku
        $this->db->execute(
            'UPDATE users SET is_active = ?, token_version = token_version + 1 WHERE id_user = ?',
            [$active ? 1 : 0, $id]
        );
    }

    public function recordFailedLogin(int $id, int $maxAttempts, int $lockMinutes): void
    {
        $this->db->execute(
            'UPDATE users SET
                locked_until = CASE WHEN failed_login_attempts + 1 >= ? THEN DATEADD(MINUTE, ?, SYSDATETIME()) ELSE locked_until END,
                failed_login_attempts = CASE WHEN failed_login_attempts + 1 >= ? THEN 0 ELSE failed_login_attempts + 1 END
             WHERE id_user = ?',
            [$maxAttempts, $lockMinutes, $maxAttempts, $id]
        );
    }

    public function recordSuccessfulLogin(int $id): void
    {
        $this->db->execute(
            'UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = SYSDATETIME() WHERE id_user = ?',
            [$id]
        );
    }

    public function updatePassword(int $id, string $hash): void
    {
        $this->db->execute(
            'UPDATE users SET password = ?, token_version = token_version + 1 WHERE id_user = ?',
            [$hash, $id]
        );
    }

    public function revokeTokens(int $id): void
    {
        $this->db->execute('UPDATE users SET token_version = token_version + 1 WHERE id_user = ?', [$id]);
    }

    public function logAuth(?int $idUser, string $username, string $event, bool $success, string $ip, string $ua): void
    {
        try {
            $this->db->execute(
                'INSERT INTO auth_log (id_user, username, event, success, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)',
                [$idUser, substr($username, 0, 50), $event, $success ? 1 : 0, $ip, $ua]
            );
        } catch (\Throwable $e) {
            error_log('[auth_log] ' . $e->getMessage()); // log gagal tidak boleh menggagalkan request
        }
    }
}
