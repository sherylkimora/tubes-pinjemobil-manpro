<?php
declare(strict_types=1);

namespace Pinjemobil\Controllers;

use Pinjemobil\Database;
use Pinjemobil\Request;
use Pinjemobil\Response;

final class HealthController
{
    public function __construct(private readonly Database $db)
    {
    }

    /** GET /health - dipakai untuk cek server staging & monitoring */
    public function check(Request $req): void
    {
        $dbOk = true;
        try {
            $this->db->fetchOne('SELECT 1 AS ok');
        } catch (\Throwable) {
            $dbOk = false;
        }
        Response::json([
            'success'  => $dbOk,
            'service'  => 'pinjemobil-api',
            'database' => $dbOk ? 'connected' : 'unreachable',
            'time'     => date('c'),
        ], $dbOk ? 200 : 503);
    }
}
