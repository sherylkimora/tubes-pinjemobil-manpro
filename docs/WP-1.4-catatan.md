# Catatan Kerja WP 1.4 — Setup Environment & Autentikasi

**PIC:** Travelicia Louisiana Sumargono (Backend Developer) · **Durasi:** 1 hari · **Sprint 1** · **Precedence:** -
**Output WP (sesuai deskripsi):** repositori Git, lingkungan pengembangan, server staging, autentikasi pengguna, pembagian hak akses.

| Item deskripsi WP | Hasil | File |
|---|---|---|
| Repositori Git | .gitignore, alur branch, format commit, PR template, CI | `.gitignore`, `CONTRIBUTING.md`, `.github/` |
| Lingkungan pengembangan | konfigurasi via `.env`, panduan setup, script bantu | `.env.example`, `docs/SETUP.md`, `scripts/` |
| Server staging | Dockerfile + compose, endpoint `/health` | `Dockerfile`, `docker-compose.yml` |
| Autentikasi pengguna | register, login, me, logout, ganti password, JWT, hash bcrypt, lockout | `api/src/Controllers/AuthController.php` |
| Pembagian hak akses | 4 role TOR, matriks permission, scope per cabang, manajemen akun | `api/config/permissions.php`, `Middleware/Auth.php`, `UserController.php` |
| Database | migrasi role, kolom keamanan, auth_log | `database/migrations/001_auth_rbac.sql` |

## Definition of Done
- [x] Lint PHP + 36 unit test lulus (`php api/tests/run.php`)
- [ ] Migrasi dijalankan di DB lokal & staging
- [ ] Uji manual semua endpoint (curl/Postman) pada DB nyata
- [ ] PR di-merge ke `develop`, kartu 1.4 dipindah ke Done

## Bukti untuk presentasi 1 Des
Commit history WP 1.4, screenshot Postman (login 200, /users tanpa token 401, pegawai ke /users 403), kartu Trello di Done.
