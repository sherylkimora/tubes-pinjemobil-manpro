<?php
declare(strict_types=1);

namespace Pinjemobil\Middleware;

use Pinjemobil\HttpException;
use Pinjemobil\Jwt;
use Pinjemobil\Repositories\UserRepository;
use Pinjemobil\Request;

final class Auth
{
    public const ROLES = ['admin_utama', 'admin_cabang', 'pegawai', 'customer'];

    public function __construct(
        private readonly Jwt $jwt,
        private readonly UserRepository $users,
        private readonly array $permissions
    ) {
    }

    /** Verifikasi Bearer token + cek status akun terkini di database. */
    public function authenticate(Request $req): void
    {
        $token = $req->bearerToken();
        if (!$token) {
            throw new HttpException(401, 'Autentikasi diperlukan. Sertakan header Authorization: Bearer <token>.');
        }
        $claims = $this->jwt->decode($token);
        $user = $this->users->findById((int) ($claims['sub'] ?? 0));

        if (!$user || (int) $user['token_version'] !== (int) ($claims['tv'] ?? -1)) {
            throw new HttpException(401, 'Sesi tidak berlaku, silakan login ulang.');
        }
        if (!(int) $user['is_active']) {
            throw new HttpException(403, 'Akun dinonaktifkan.');
        }
        $req->user = [
            'id_user'    => (int) $user['id_user'],
            'nama'       => $user['nama'],
            'username'   => $user['username'],
            'role'       => $user['role'],
            'id_cabang'  => $user['id_cabang'] !== null ? (int) $user['id_cabang'] : null,
            'id_member'  => $user['id_member'] !== null ? (int) $user['id_member'] : null,
            'id_pegawai' => $user['id_pegawai'] !== null ? (int) $user['id_pegawai'] : null,
        ];
    }

    public function requireRoles(Request $req, array $roles): void
    {
        if (!$req->user || !in_array($req->user['role'], $roles, true)) {
            throw new HttpException(403, 'Anda tidak memiliki akses ke resource ini.');
        }
    }

    public function requirePermission(Request $req, string $permission): void
    {
        if (!$req->user || !$this->has($req->user['role'], $permission)) {
            throw new HttpException(403, 'Anda tidak memiliki akses ke resource ini.');
        }
    }

    public function has(string $role, string $permission): bool
    {
        return in_array($permission, $this->permissions[$role] ?? [], true);
    }

    public function permissionsFor(string $role): array
    {
        return $this->permissions[$role] ?? [];
    }

    /**
     * Aturan data per cabang: admin_utama semua cabang; admin_cabang & pegawai hanya cabang sendiri;
     * customer tidak punya akses lintas cabang. Dipakai modul 1.6 dst.
     */
    public static function canAccessCabang(array $user, int $idCabang): bool
    {
        return match ($user['role']) {
            'admin_utama'                => true,
            'admin_cabang', 'pegawai'    => $user['id_cabang'] === $idCabang,
            default                      => false,
        };
    }
}
