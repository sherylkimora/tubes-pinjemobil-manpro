# Panduan Kolaborasi Tim Pinjemobil

## Branch
| Branch | Fungsi |
|---|---|
| `main` | Stabil, hasil akhir tiap sprint (hanya lewat PR) |
| `develop` | Integrasi harian |
| `feature/<wp>-<deskripsi>` | Satu branch per work package, mis. `feature/1.4-setup-autentikasi` |

Alur: buat branch dari `develop` -> commit kecil -> push -> Pull Request ke `develop` -> review minimal 1 anggota -> merge -> pindahkan kartu Trello.

## Format commit (Conventional Commits)
`feat(auth): tambah login JWT` | `fix(db): perbaiki constraint role` | `docs: update API` | `test: tambah test RBAC` | `chore: setup env`
Sertakan nomor WP bila relevan: `feat(auth): login JWT [WP 1.4]`.

## Aturan
- Jangan commit `.env`, password, atau token.
- Selalu `git pull --rebase origin develop` sebelum push untuk mengurangi konflik.
- Konflik: selesaikan lokal, jalankan test, baru push.
- Perubahan status kartu Trello: To Do -> Dikerjakan saat mulai, Done setelah PR merge.
