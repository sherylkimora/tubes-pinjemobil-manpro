<?php
declare(strict_types=1);

use Pinjemobil\Controllers\{AuthController, HealthController, UserController};
use Pinjemobil\{Database, HttpException, Jwt, Request, Response, Router};
use Pinjemobil\Middleware\Auth;
use Pinjemobil\Repositories\UserRepository;

$config = require __DIR__ . '/bootstrap.php';

// --- Header keamanan & CORS ---
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $config['cors'], true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $db    = new Database($config['db']);
    $users = new UserRepository($db);
    $jwt   = new Jwt($config['jwt']['secret'], $config['jwt']['ttl'], $config['jwt']['issuer']);
    $auth  = new Auth($jwt, $users, require __DIR__ . '/config/permissions.php');

    $authC   = new AuthController($users, $jwt, $auth, $config['login']);
    $userC   = new UserController($users);
    $healthC = new HealthController($db);

    $r = new Router();
    $r->get('/health', [$healthC, 'check']);

    // Autentikasi
    $r->post('/auth/register',        [$authC, 'register']);
    $r->post('/auth/login',           [$authC, 'login']);
    $r->get('/auth/me',               [$authC, 'me'])->auth();
    $r->post('/auth/logout',          [$authC, 'logout'])->auth();
    $r->post('/auth/change-password', [$authC, 'changePassword'])->auth();

    // Manajemen akun staf (hak akses)
    $r->get('/users',                 [$userC, 'index'])->can('user.manage');
    $r->post('/users',                [$userC, 'store'])->can('user.manage');
    $r->patch('/users/{id}/active',   [$userC, 'setActive'])->can('user.manage');

    // Modul berikutnya (1.6 dst) cukup mendaftarkan route baru, mis.:
    // $r->get('/mobil', [$mobilC, 'index'])->can('mobil.read');

    $r->dispatch(Request::fromGlobals(), $auth);
} catch (HttpException $e) {
    Response::error($e->getMessage(), $e->status, $e->errors);
} catch (Throwable $e) {
    error_log('[api] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    Response::error($config['debug'] ?? false ? $e->getMessage() : 'Terjadi kesalahan pada server.', 500);
}
