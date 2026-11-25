<?php
declare(strict_types=1);

/** Test runner sederhana tanpa dependensi:  php api/tests/run.php  (tidak butuh database) */

require dirname(__DIR__) . '/bootstrap.php';

use Pinjemobil\{Database, HttpException, Jwt, Request, Router, Validator};
use Pinjemobil\Middleware\Auth;
use Pinjemobil\Repositories\UserRepository;

$pass = 0; $fail = 0;
function check(string $name, bool $cond): void
{
    global $pass, $fail;
    $cond ? $pass++ : $fail++;
    echo ($cond ? '  [PASS] ' : '  [FAIL] ') . $name . PHP_EOL;
}
function throwsStatus(callable $fn, int $status): bool
{
    try { $fn(); } catch (HttpException $e) { return $e->status === $status; }
    return false;
}

$secret = str_repeat('k', 40);
$jwt = new Jwt($secret, 3600, 'pinjemobil');

echo "JWT\n";
$tok = $jwt->encode(['sub' => 7, 'role' => 'pegawai', 'tv' => 0]);
check('encode lalu decode mengembalikan klaim', $jwt->decode($tok)['sub'] === 7);
check('token diubah -> 401', throwsStatus(fn() => $jwt->decode($tok . 'x'), 401));
check('secret berbeda -> 401', throwsStatus(fn() => (new Jwt(str_repeat('z', 40), 3600, 'pinjemobil'))->decode($tok), 401));
check('token kedaluwarsa -> 401', throwsStatus(fn() => $jwt->decode((new Jwt($secret, -10, 'pinjemobil'))->encode(['sub' => 1])), 401));
check('alg none ditolak', throwsStatus(fn() => $jwt->decode(
    rtrim(strtr(base64_encode('{"alg":"none"}'), '+/', '-_'), '=') . '.' . explode('.', $tok)[1] . '.'), 401));
check('secret pendek ditolak', (function () { try { new Jwt('pendek', 1, 'x'); } catch (RuntimeException) { return true; } return false; })());

echo "Validator\n";
check('password kuat diterima', Validator::strongPassword('Rental#2026'));
check('password tanpa simbol ditolak', !Validator::strongPassword('Rental2026'));
check('password pendek ditolak', !Validator::strongPassword('Ab1#'));
check('data kosong -> 422', throwsStatus(fn() => (new Validator([]))->required('username')->validate(), 422));
check('username huruf besar -> 422', throwsStatus(fn() => (new Validator(['username' => 'Budi']))->username()->validate(), 422));
check('email salah -> 422', throwsStatus(fn() => (new Validator(['email' => 'abc']))->email()->validate(), 422));
check('telepon huruf -> 422', throwsStatus(fn() => (new Validator(['nomor_telepon' => '08ab']))->phone()->validate(), 422));
check('role di luar daftar -> 422', throwsStatus(fn() => (new Validator(['role' => 'hacker']))->in('role', ['pegawai'])->validate(), 422));

echo "Auth middleware & RBAC\n";
$perm = require dirname(__DIR__) . '/config/permissions.php';
$fakeRepo = new class(new Database([])) extends UserRepository {
    public array $rows = [];
    public function findById(int $id): ?array { return $this->rows[$id] ?? null; }
};
$mk = fn(int $id, string $role, int $tv = 0, int $active = 1, ?int $cabang = 1) => [
    'id_user' => $id, 'nama' => 'U', 'username' => 'u' . $id, 'role' => $role, 'id_member' => null,
    'id_pegawai' => null, 'is_active' => $active, 'token_version' => $tv, 'id_cabang' => $cabang,
];
$fakeRepo->rows = [
    1 => $mk(1, 'admin_utama'), 2 => $mk(2, 'admin_cabang'), 3 => $mk(3, 'pegawai'),
    4 => $mk(4, 'customer'), 5 => $mk(5, 'pegawai', 0, 0), 6 => $mk(6, 'pegawai', 3),
];
$auth = new Auth($jwt, $fakeRepo, $perm);
$reqFor = function (?string $token): Request {
    $h = $token ? ['authorization' => "Bearer $token"] : [];
    return new Request('GET', '/x', $h, [], [], '127.0.0.1', 'test');
};
$login = fn(int $id, string $role, int $tv = 0) => $jwt->encode(['sub' => $id, 'role' => $role, 'tv' => $tv]);

check('tanpa token -> 401', throwsStatus(fn() => $auth->authenticate($reqFor(null)), 401));
check('user tidak ada -> 401', throwsStatus(fn() => $auth->authenticate($reqFor($login(99, 'pegawai'))), 401));
check('token_version lama (setelah logout) -> 401', throwsStatus(fn() => $auth->authenticate($reqFor($login(6, 'pegawai', 0))), 401));
check('akun nonaktif -> 403', throwsStatus(fn() => $auth->authenticate($reqFor($login(5, 'pegawai'))), 403));
$r = $reqFor($login(3, 'pegawai'));
$auth->authenticate($r);
check('token valid mengisi req->user', $r->user['role'] === 'pegawai');
check('pegawai ditolak endpoint admin', throwsStatus(fn() => $auth->requireRoles($r, ['admin_utama', 'admin_cabang']), 403));
check('pegawai ditolak permission user.manage', throwsStatus(fn() => $auth->requirePermission($r, 'user.manage'), 403));
check('pegawai boleh inspeksi.write', (function () use ($auth, $r) { $auth->requirePermission($r, 'inspeksi.write'); return true; })());
check('customer tidak boleh mobil.write', !$auth->has('customer', 'mobil.write'));
check('customer boleh booking.create', $auth->has('customer', 'booking.create'));
check('admin_utama boleh cabang.write, admin_cabang tidak', $auth->has('admin_utama', 'cabang.write') && !$auth->has('admin_cabang', 'cabang.write'));
check('role tak dikenal tanpa permission', !$auth->has('hacker', 'mobil.read'));
check('admin_utama akses semua cabang', Auth::canAccessCabang(['role' => 'admin_utama', 'id_cabang' => 1], 9));
check('admin_cabang hanya cabang sendiri', Auth::canAccessCabang(['role' => 'admin_cabang', 'id_cabang' => 2], 2) && !Auth::canAccessCabang(['role' => 'admin_cabang', 'id_cabang' => 2], 3));
check('customer tidak punya akses lintas cabang', !Auth::canAccessCabang(['role' => 'customer', 'id_cabang' => 1], 1));

echo "Router\n";
$router = new Router();
$hit = null;
$router->get('/users/{id}', function (Request $q) use (&$hit) { $hit = $q->params['id']; });
$router->post('/auth/login', function () use (&$hit) { $hit = 'login'; });
$router->get('/secure', function () use (&$hit) { $hit = 'secure'; })->roles('admin_utama');
$mkReq = fn(string $m, string $p, ?string $t = null) => new Request($m, $p, $t ? ['authorization' => "Bearer $t"] : [], [], [], '', '');
$router->dispatch($mkReq('GET', '/users/42'), $auth);
check('route dengan parameter {id}', $hit === '42');
$router->dispatch($mkReq('POST', '/auth/login'), $auth);
check('route POST', $hit === 'login');
check('path tak ada -> 404', throwsStatus(fn() => $router->dispatch($mkReq('GET', '/nope'), $auth), 404));
check('method salah -> 405', throwsStatus(fn() => $router->dispatch($mkReq('DELETE', '/auth/login'), $auth), 405));
check('route terproteksi tanpa token -> 401', throwsStatus(fn() => $router->dispatch($mkReq('GET', '/secure'), $auth), 401));
check('route admin dengan token pegawai -> 403', throwsStatus(fn() => $router->dispatch($mkReq('GET', '/secure', $login(3, 'pegawai')), $auth), 403));
$router->dispatch($mkReq('GET', '/secure', $login(1, 'admin_utama')), $auth);
check('route admin dengan token admin_utama -> lolos', $hit === 'secure');

echo PHP_EOL . "Hasil: $pass lulus, $fail gagal" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
