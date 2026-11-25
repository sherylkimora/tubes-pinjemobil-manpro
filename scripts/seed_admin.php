<?php
declare(strict_types=1);
/**
 * Membuat / mereset akun admin_utama untuk environment baru (staging/production).
 * Pakai:  php scripts/seed_admin.php <username> <password> "<Nama Lengkap>"
 */
$db = require __DIR__ . '/_db.php';
[$self, $username, $password, $nama] = $argv + [null, null, null, 'Admin Utama'];

if (!$username || !$password || !Pinjemobil\Validator::strongPassword($password)) {
    fwrite(STDERR, "Pakai: php scripts/seed_admin.php <username> <password> \"<Nama>\"\nPassword min. 8 karakter, 1 huruf besar, 1 angka, 1 simbol.\n");
    exit(1);
}
$hash = password_hash($password, PASSWORD_BCRYPT);
if ($db->fetchOne('SELECT 1 AS x FROM users WHERE username = ?', [$username])) {
    $db->execute("UPDATE users SET password = ?, role = 'admin_utama', is_active = 1, token_version = token_version + 1 WHERE username = ?", [$hash, $username]);
    echo "Akun $username di-reset.\n";
} else {
    $db->execute("INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, 'admin_utama')", [$nama, $username, $hash]);
    echo "Akun admin_utama $username dibuat.\n";
}
