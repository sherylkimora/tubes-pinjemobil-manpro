# Setup Environment (WP 1.4)

## Prasyarat
- XAMPP (Apache + PHP 8.1+), SQL Server Express + SSMS
- Ekstensi PHP **sqlsrv** aktif (`php -m | findstr sqlsrv`) — sudah dipakai `koneksi.php` lama
- Git

## Langkah (lokal)
1. `git clone <url-repo>` ke `C:\xampp\htdocs\pinjemobil`, lalu `git checkout develop`
2. Salin `.env.example` -> `.env`, isi `DB_SERVER`, `DB_NAME`, dan `JWT_SECRET` (buat dengan `php scripts/generate_secret.php`)
3. Buat database `pinjemobil` lalu jalankan `database/dummy_pinjemobil.sql` di SSMS
4. Jalankan `database/migrations/001_auth_rbac.sql` di SSMS
5. `php scripts/migrate_passwords.php` (hash password lama)
6. Terapkan `legacy-patch/README.md` ke `login.php` & `register.php` lama
7. Start Apache, cek `http://localhost/pinjemobil/api/health` -> `{"success":true,"database":"connected",...}`
8. Jalankan test: `php api/tests/run.php`

## Staging
Opsi A (Docker): `JWT_SECRET=<rahasia> docker compose up -d`, lalu jalankan migrasi SQL ke container db, API di `http://<host>:8080/api/health`.
Opsi B: salin repo ke server staging, buat `.env` dengan `APP_ENV=staging`, `APP_DEBUG=false`.
Buat admin awal di staging: `php scripts/seed_admin.php admin 'Password#123' "Admin Utama"`.

## Akun dummy setelah migrasi
Password dummy lama (mis. `admin`/`admin`) tetap berlaku untuk dev, hanya sudah di-hash. Ganti semuanya di staging/produksi.

## Troubleshooting
| Gejala | Solusi |
|---|---|
| `Ekstensi PHP sqlsrv belum aktif` | aktifkan `extension=php_sqlsrv` di php.ini, restart Apache |
| `JWT_SECRET minimal 32 karakter` | isi `.env` |
| 401 padahal header ada (Apache) | pastikan `mod_rewrite` aktif & `api/.htaccess` terbaca (`AllowOverride All`) |
| `/api/health` 503 | cek `DB_SERVER`, SQL Server Browser/TCP aktif |
