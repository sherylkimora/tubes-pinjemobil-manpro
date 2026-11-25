<?php
declare(strict_types=1);

/**
 * Matriks hak akses (RBAC) Pinjemobil, mengacu pada TOR:
 * Admin Utama, Admin Cabang, Pegawai, Customer/Member.
 * Format permission: <modul>.<aksi>. Pemakaian di route: ->can('mobil.write')
 * Role admin_cabang & pegawai dibatasi ke cabang miliknya (lihat Auth::canAccessCabang).
 */
return [
    'admin_utama' => [
        'user.manage', 'cabang.read', 'cabang.write', 'pegawai.read', 'pegawai.write',
        'member.read', 'member.write', 'mobil.read', 'mobil.write',
        'peminjaman.read', 'peminjaman.write', 'inspeksi.write', 'pengembalian.write',
        'denda.read', 'laporan.read', 'dashboard.read',
    ],
    'admin_cabang' => [
        'user.manage', 'cabang.read', 'pegawai.read', 'pegawai.write',
        'member.read', 'member.write', 'mobil.read', 'mobil.write',
        'peminjaman.read', 'peminjaman.write', 'inspeksi.write', 'pengembalian.write',
        'denda.read', 'laporan.read', 'dashboard.read',
    ],
    'pegawai' => [
        'cabang.read', 'member.read', 'mobil.read',
        'peminjaman.read', 'peminjaman.write', 'inspeksi.write', 'pengembalian.write',
        'denda.read', 'dashboard.read',
    ],
    'customer' => [
        'cabang.read', 'mobil.read', 'booking.create', 'riwayat.own', 'profil.own',
    ],
];
