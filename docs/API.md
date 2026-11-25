# Dokumentasi API Autentikasi & Hak Akses (WP 1.4)

Base URL lokal: `http://localhost/pinjemobil/api`. Body JSON. Respons: `{"success":bool,"message":"...","data":...}`.
Endpoint terproteksi memakai header `Authorization: Bearer <token>`.

| Method | Path | Akses | Keterangan |
|---|---|---|---|
| GET | /health | publik | cek API & koneksi DB |
| POST | /auth/register | publik | daftar customer/member |
| POST | /auth/login | publik | dapat token JWT |
| GET | /auth/me | login | profil + daftar permission |
| POST | /auth/logout | login | cabut semua token user |
| POST | /auth/change-password | login | ganti password (token lama dicabut) |
| GET | /users?role= | `user.manage` | daftar akun staf (admin_cabang: cabang sendiri) |
| POST | /users | `user.manage` | buat akun staf untuk pegawai yang ada |
| PATCH | /users/{id}/active | `user.manage` | aktif/nonaktifkan akun |

## Contoh
```bash
# register
curl -X POST localhost/pinjemobil/api/auth/register -H "Content-Type: application/json" -d '{
 "nama_member":"Budi","nomor_telepon":"081234567890","email":"budi@mail.com",
 "username":"budi","password":"Rental#2026","id_cabang":1,"alamat":"Bandung"}'

# login
curl -X POST localhost/pinjemobil/api/auth/login -H "Content-Type: application/json" \
 -d '{"username":"admin","password":"admin"}'

# endpoint terproteksi
curl localhost/pinjemobil/api/auth/me -H "Authorization: Bearer <token>"

# admin membuat akun pegawai (id_pegawai harus sudah ada di tabel pegawai)
curl -X POST localhost/pinjemobil/api/users -H "Authorization: Bearer <token>" -H "Content-Type: application/json" \
 -d '{"nama":"Pegawai Barat","username":"pegbarat","password":"Pegawai#2026","role":"pegawai","id_pegawai":3}'
```

## Kode status
200/201 sukses · 401 token tidak ada/invalid/kedaluwarsa · 403 role/permission tidak cukup atau akun nonaktif · 404 tidak ditemukan · 405 method salah · 409 duplikat · 422 validasi gagal · 423 akun terkunci · 500 error server.

## Aturan keamanan
- Password bcrypt; kebijakan: min. 8 karakter, 1 huruf besar, 1 angka, 1 simbol.
- 5x gagal login -> akun terkunci 15 menit (atur di `.env`).
- Token JWT HS256, masa berlaku `JWT_TTL_MINUTES`. Logout/ganti password/nonaktifkan akun menaikkan `token_version` sehingga token lama langsung tidak berlaku.
- Semua aktivitas login dicatat di tabel `auth_log`.

## Matriks hak akses
Lihat `api/config/permissions.php`. Modul baru memakai `->can('mobil.write')` pada route dan `Auth::canAccessCabang($user, $idCabang)` untuk pembatasan per cabang (admin_cabang & pegawai hanya cabangnya).

| Role | Ringkas |
|---|---|
| admin_utama | semua cabang, kelola cabang/pegawai/mobil/member, laporan, akun |
| admin_cabang | data cabang sendiri, kelola pegawai/mobil/member, laporan, akun pegawai |
| pegawai | baca master, transaksi, inspeksi, pengembalian di cabang sendiri |
| customer | lihat katalog, booking, riwayat & profil sendiri |
