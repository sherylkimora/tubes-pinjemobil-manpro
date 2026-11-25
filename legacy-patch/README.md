# Patch halaman PHP lama (login.php & register.php)

Setelah `migrate_passwords.php` dijalankan, password di database berupa hash sehingga
`login.php` lama (`WHERE password = ?`) tidak bisa login. Terapkan 3 perubahan ini:

## login.php
Ganti query & pengecekan:
```php
$sql = "SELECT * FROM users WHERE username = ? AND is_active = 1";
$query = sqlsrv_query($koneksi, $sql, [$username]);
$user = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['id_user'] = $user['id_user'];
    $_SESSION['nama'] = $user['nama'];
    // halaman lama mengecek role == 'admin', jadi admin_utama/admin_cabang dipetakan ke 'admin'
    $_SESSION['role'] = in_array($user['role'], ['admin_utama', 'admin_cabang']) ? 'admin' : $user['role'];
    $_SESSION['role_asli'] = $user['role'];
    ...
```
(hapus `$sql ... AND password = ?` dan `$params = [$username, $password]`)

## register.php
Pada `$params_user`, ganti `$password` menjadi:
```php
password_hash($password, PASSWORD_BCRYPT)
```
