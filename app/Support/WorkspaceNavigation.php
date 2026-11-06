<?php

namespace App\Support;

use App\Models\User;

class WorkspaceNavigation
{
    public static function pages(?User $user): array
    {
        if (! $user?->is_active) {
            return [];
        }

        $pages = ['surat', 'tugas', 'pelacakan'];

        return match (true) {
            $user->isAdmin() => [...$pages, 'disposisi', 'tindak-lanjut', 'monitoring', 'sk', 'pengaturan'],
            $user->isLeader(), $user->isPersonalSecretary() => [...$pages, 'disposisi'],
            $user->isDepartmentHead(), $user->isStaff() => [...$pages, 'tindak-lanjut'],
            default => [],
        };
    }

    public static function authorize(string $page): void
    {
        abort_unless(in_array($page, static::pages(auth()->user()), true), 403);
    }

    public static function settingsTabs(): array
    {
        return [
            'profil' => 'Profil Instansi',
            'nomor' => 'Nomor & Alur',
            'unit' => 'Unit Surat',
            'kode-arsip' => 'Kode Arsip',
            'monitoring-nomor' => 'Monitoring Nomor',
            'pengguna' => 'Pengguna',
            'disposisi' => 'Penerima Disposisi',
            'android' => 'Android',
            'audit' => 'Audit & Peran',
        ];
    }

    public static function titles(): array
    {
        return [
            'surat' => 'Dasbor Persuratan',
            'tugas' => 'Inbox / Tugas Saya',
            'pelacakan' => 'Pelacakan Surat',
            'disposisi' => 'Disposisi Pimpinan',
            'tindak-lanjut' => 'Tindak Lanjut Disposisi',
            'monitoring' => 'Monitoring Nomor Surat',
            'sk' => 'Penomoran Surat Keputusan',
            'pengaturan' => 'Pengaturan Aplikasi',
        ];
    }
}
