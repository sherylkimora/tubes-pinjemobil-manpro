<?php
declare(strict_types=1);

namespace Pinjemobil\Controllers;

use Pinjemobil\HttpException;
use Pinjemobil\Middleware\Auth;
use Pinjemobil\Repositories\UserRepository;
use Pinjemobil\Request;
use Pinjemobil\Response;
use Pinjemobil\Validator;

/** Manajemen akun staf & pembagian hak akses (WP 1.4). */
final class UserController
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    /** GET /users?role=pegawai - admin_utama: semua cabang; admin_cabang: cabangnya saja */
    public function index(Request $req): void
    {
        $role = $req->query['role'] ?? null;
        if ($role !== null && !in_array($role, ['admin_utama', 'admin_cabang', 'pegawai'], true)) {
            throw new HttpException(422, 'Filter role tidak valid.');
        }
        $cabang = $req->user['role'] === 'admin_utama' ? null : $req->user['id_cabang'];
        Response::ok($this->users->listStaff($cabang, $role));
    }

    /** POST /users - buat akun staf untuk data pegawai yang sudah ada */
    public function store(Request $req): void
    {
        $actor = $req->user;
        $d = $req->all();
        (new Validator($d))
            ->required('nama', 'username', 'password', 'role')
            ->username()->password()
            ->in('role', ['admin_utama', 'admin_cabang', 'pegawai'])
            ->integer('id_pegawai')
            ->validate();

        // admin_cabang hanya boleh membuat akun pegawai
        if ($actor['role'] === 'admin_cabang' && $d['role'] !== 'pegawai') {
            throw new HttpException(403, 'Admin cabang hanya boleh membuat akun pegawai.');
        }
        if ($d['role'] !== 'admin_utama' && empty($d['id_pegawai'])) {
            throw new HttpException(422, 'id_pegawai wajib untuk role admin_cabang dan pegawai.', ['id_pegawai' => 'wajib diisi.']);
        }

        $idPegawai = !empty($d['id_pegawai']) ? (int) $d['id_pegawai'] : null;
        if ($idPegawai !== null) {
            $pegawai = $this->users->findPegawai($idPegawai);
            if (!$pegawai) {
                throw new HttpException(404, 'Data pegawai tidak ditemukan.');
            }
            if (!Auth::canAccessCabang($actor, (int) $pegawai['id_cabang'])) {
                throw new HttpException(403, 'Pegawai ini bukan dari cabang Anda.');
            }
            if ($this->users->pegawaiHasAccount($idPegawai)) {
                throw new HttpException(409, 'Pegawai ini sudah memiliki akun.');
            }
        }
        if ($this->users->usernameExists($d['username'])) {
            throw new HttpException(409, 'Username sudah digunakan.', ['username' => 'Username sudah digunakan.']);
        }

        $id = $this->users->createStaff($d['nama'], $d['username'], password_hash($d['password'], PASSWORD_BCRYPT), $d['role'], $idPegawai);
        Response::ok(['id_user' => $id, 'role' => $d['role']], 'Akun berhasil dibuat.', 201);
    }

    /** PATCH /users/{id}/active  body: {"is_active": true|false} */
    public function setActive(Request $req): void
    {
        $actor = $req->user;
        $id = (int) $req->params['id'];
        $active = $req->input('is_active');
        if (!is_bool($active)) {
            throw new HttpException(422, 'is_active harus true atau false.');
        }
        if ($id === $actor['id_user']) {
            throw new HttpException(422, 'Anda tidak dapat menonaktifkan akun sendiri.');
        }
        $target = $this->users->findById($id);
        if (!$target || $target['role'] === 'customer') {
            throw new HttpException(404, 'Akun staf tidak ditemukan.');
        }
        if ($actor['role'] === 'admin_cabang'
            && ($target['role'] !== 'pegawai' || !Auth::canAccessCabang($actor, (int) $target['id_cabang']))) {
            throw new HttpException(403, 'Anda hanya boleh mengelola pegawai di cabang Anda.');
        }
        $this->users->setActive($id, $active);
        Response::ok(null, $active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.');
    }
}
