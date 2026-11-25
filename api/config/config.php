<?php
declare(strict_types=1);

use Pinjemobil\Env;

return [
    'env'   => Env::get('APP_ENV', 'production'),
    'debug' => filter_var(Env::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'db' => [
        'server'     => Env::get('DB_SERVER', 'localhost'),
        'name'       => Env::get('DB_NAME', 'pinjemobil'),
        'user'       => Env::get('DB_USER', ''),
        'pass'       => Env::get('DB_PASS', ''),
        'trust_cert' => filter_var(Env::get('DB_TRUST_CERT', 'true'), FILTER_VALIDATE_BOOLEAN),
    ],
    'jwt' => [
        'secret' => Env::get('JWT_SECRET', ''),
        'ttl'    => (int) Env::get('JWT_TTL_MINUTES', '120') * 60,
        'issuer' => Env::get('JWT_ISSUER', 'pinjemobil'),
    ],
    'login' => [
        'max_attempts' => (int) Env::get('LOGIN_MAX_ATTEMPTS', '5'),
        'lock_minutes' => (int) Env::get('LOGIN_LOCK_MINUTES', '15'),
    ],
    'cors' => array_values(array_filter(array_map('trim', explode(',', Env::get('CORS_ALLOWED_ORIGINS', ''))))),
];
