<?php
declare(strict_types=1);
/**
 * Mengubah password plaintext di tabel users menjadi bcrypt hash.
 * Aman dijalankan berulang: hanya baris yang belum berformat hash ($2y$) yang diproses.
 * Pakai:  php scripts/migrate_passwords.php
 */
$db = require __DIR__ . '/_db.php';

$rows = $db->fetchAll("SELECT id_user, username, password FROM users WHERE password NOT LIKE '$2y$%'");
foreach ($rows as $r) {
    $db->execute('UPDATE users SET password = ? WHERE id_user = ?', [password_hash($r['password'], PASSWORD_BCRYPT), $r['id_user']]);
    echo "hashed: {$r['username']}\n";
}
echo count($rows) . " password di-hash.\n";
