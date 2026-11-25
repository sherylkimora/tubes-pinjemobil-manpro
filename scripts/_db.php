<?php
declare(strict_types=1);
// Helper koneksi untuk script CLI (pakai .env yang sama dengan API)
$config = require dirname(__DIR__) . '/api/bootstrap.php';
return new Pinjemobil\Database($config['db']);
