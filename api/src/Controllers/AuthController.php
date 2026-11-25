<?php
declare(strict_types=1);

namespace Pinjemobil\Controllers;

use Pinjemobil\HttpException;
use Pinjemobil\Jwt;
use Pinjemobil\Middleware\Auth;
use Pinjemobil\Repositories\UserRepository;
use Pinjemobil\Request;
use Pinjemobil\Response;
use Pinjemobil\Validator;

final class AuthController
{
    /** Hash palsu untuk menyamakan waktu respons saat username tidak ditemukan. */
    private const DUMMY_HASH = '$2y$10$z/QFPpaQlb1NCDaBkrBJtuQoUg3RzmvSwu1fNaed8DSstVzC5Z.8O';

    public function __construct(
        private readonly UserRepository $users,
        private readonly Jwt $jwt,
        private readonly Auth $auth,
        private readonly array $loginCfg
    ) {
    }

    /** POST /auth/register - registrasi mandiri customer/member */
    public function register(Request $req): void
    {
        $d = $req->all();
        (new Validator($d))
            ->required('nama_member', 'nomor_telepon', 'email', 'username', 'password', 'id_cabang')
            ->username()->password()->email()->phone()->integer('id_cabang')
            ->validate();

        $errors = [];
        if (!$this->users->cabangExists((int) $d['id_cabang'])) {
            $errors['id_cabang'] = 'Cabang tidak ditemukan.';
        }
        if ($this->users->usernameExists($d['username'])) {
            $errors['username'] = 'Username sudah digunakan.';
        }
        if ($this->users->memberEmailExists($d['email'])) {
            $errors['email'] = 'Email sudah terdaftar.';
        }
        if ($errors) {
            throw new HttpException(409, 'Registrasi gagal.', $errors);
        }

        $id = $this->users->registerCustomer($d, password_hash($d['password'], PASSWORD_BCRYPT));
        $this->users->logAuth($id, $d['username'], 'register', true, $req->ip, $req->userAgent);
        Response::ok(['id_user' => $id, 'role' => 'customer'], 'Registrasi berhasil. Silakan login.', 201);
    }

    /** POST /auth/login */
    public function login(Request $req): void
    {
        $d = $req->all();
        (new Validator($d))->required('username', 'password')->validate();
        $username = (string) $d['username'];

        $user = $this->users->findByUsername($username);

        if ($user && (int) $user['is_locked']) {
            $this->users->logAuth((int) $user['id_user'], $username, 'login_locked', false, $req->ip, $req->userAgent);
            throw new HttpException(423, "Akun terkunci karena terlalu banyak percobaan gagal. Coba lagi sekitar {$user['lock_minutes_left']} menit lagi.");
        }

        $valid = password_verify((string) $d['password'], $user['password'] ?? self::DUMMY_HASH);
        if (!$user || !$valid) {
            if ($user) {
                $this->users->recordFailedLogin((int) $user['id_user'], $this->loginCfg['max_attempts'], $this->loginCfg['lock_minutes']);
            }
            $this->users->logAuth($user ? (int) $user['id_user'] : null, $username, 'login', false, $req->ip, $req->userAgent);
            throw new HttpException(401, 'Username atau password salah.');
        }
        if (!(int) $user['is_active']) {
            throw new HttpException(403, 'Akun dinonaktifkan. Hubungi admin.');
        }

        if (password_needs_rehash($user['password'], PASSWORD_BCRYPT)) {
            $this->users->updatePassword((int) $user['id_user'], password_hash((string) $d['password'], PASSWORD_BCRYPT));
            $user = $this->users->findById((int) $user['id_user']);
        }
        $this->users->recordSuccessfulLogin((int) $user['id_user']);
        $this->users->logAuth((int) $user['id_user'], $username, 'login', true, $req->ip, $req->userAgent);

        $token = $this->jwt->encode([
            'sub'  => (int) $user['id_user'],
            'role' => $user['role'],
            'tv'   => (int) $user['token_version'],
        ]);

        Response::ok([
            'token'      => $token,
            'token_type' => 'Bearer',
            'expires_in' => $this->jwt->ttl(),
            'user'       => $this->publicUser($user),
        ], 'Login berhasil.');
    }

    /** GET /auth/me */
    public function me(Request $req): void
    {
        Response::ok($req->user + ['permissions' => $this->auth->permissionsFor($req->user['role'])]);
    }

    /** POST /auth/logout - mencabut semua token milik user (token_version +1) */
    public function logout(Request $req): void
    {
        $this->users->revokeTokens($req->user['id_user']);
        $this->users->logAuth($req->user['id_user'], $req->user['username'], 'logout', true, $req->ip, $req->userAgent);
        Response::ok(null, 'Logout berhasil.');
    }

    /** POST /auth/change-password */
    public function changePassword(Request $req): void
    {
        $d = $req->all();
        (new Validator($d))->required('password_lama', 'password_baru')->password('password_baru')->validate();

        $user = $this->users->findById($req->user['id_user']);
        if (!password_verify((string) $d['password_lama'], $user['password'])) {
            throw new HttpException(422, 'Password lama salah.', ['password_lama' => 'Password lama salah.']);
        }
        if ($d['password_lama'] === $d['password_baru']) {
            throw new HttpException(422, 'Password baru harus berbeda dari password lama.');
        }
        $this->users->updatePassword($user['id_user'], password_hash($d['password_baru'], PASSWORD_BCRYPT));
        $this->users->logAuth($user['id_user'], $user['username'], 'change_password', true, $req->ip, $req->userAgent);
        Response::ok(null, 'Password berhasil diubah. Silakan login ulang.');
    }

    private function publicUser(array $u): array
    {
        return [
            'id_user'     => (int) $u['id_user'],
            'nama'        => $u['nama'],
            'username'    => $u['username'],
            'role'        => $u['role'],
            'id_cabang'   => $u['id_cabang'] !== null ? (int) $u['id_cabang'] : null,
            'permissions' => $this->auth->permissionsFor($u['role']),
        ];
    }
}
