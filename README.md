# tubes-pinjemobil-manpro

Repository Team untuk Tugas Besar 2 Manajemen Proyek. Berisi pengembangan aplikasi rental mobil Pinjemobil, lanjutan dari perencanaan proyek di Tubes 1.

## Anggota

- Nona Andhika Navaldy (6182301038), PM/Scrum Master
- Travelicia Louisiana Sumargono (6182401012), backend, QA, deployment
- Kaylene Jacinta Suhardja (6182401030), system analyst, database, user manual
- Sheryl Kimora (6182401060), UI/UX dan frontend

## Gambaran proyek

Aplikasi ini dipakai Pinjemobil untuk mengelola kendaraan, data member, reservasi, transaksi sewa, pengembalian, denda, dan laporan keuangan. Kami mengerjakannya dengan Scrum dalam tiga sprint:

- Sprint 1: data master (cabang, mobil, customer), termasuk riset pengguna dan prototipe
- Sprint 2: katalog dan booking, inspeksi kondisi, pengembalian, dan denda
- Sprint 3: dashboard, laporan keuangan, UAT, dan penyerahan

Detail tiap paket kerja ada di dokumen perencanaan proyek.

## Board Trello

Link: https://trello.com/b/2XVTyJFs/pinjemobils-board

Kolom yang dipakai: Daftar Task, Antrian, Dikerjakan, Done.

Kartu dipindah ke Dikerjakan saat mulai dikerjakan dan ke Done setelah lolos pengujian dan sudah di-merge.

## Cara kerja dengan Git

Branch utama adalah `main`.

Nama branch mengikuti nomor paket, misalnya `1.6-crud-api-master`.

```bash
git checkout main
git pull origin main
git checkout -b 1.6-crud-api-master
# kerjakan, lalu:
git add .
git commit -m "Tambah endpoint CRUD cabang"
git push -u origin 1.6-crud-api-master
```

## Struktur folder

```
tubes_pinjemobil_manpro/
├── docs/        dokumen perencanaan, user flow, wireframe
├── backend/     API dan logika bisnis
├── frontend/    tampilan aplikasi
└── database/    ERD dan script SQL
```

## Menjalankan aplikasi
