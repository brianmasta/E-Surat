<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed user accounts for each application role.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin Sekretariat MRP',
                'role' => 'Admin Sekretariat',
                'email' => 'admin@mrp-papuatengah.test',
            ],
            [
                'name' => 'Pimpinan MRP Papua Tengah',
                'role' => 'Pimpinan MRP',
                'email' => 'pimpinan@mrp-papuatengah.test',
            ],
            [
                'name' => 'Sekretaris Pribadi Pimpinan',
                'role' => 'Sekretaris Pribadi',
                'email' => 'sekpri@mrp-papuatengah.test',
            ],
            [
                'name' => 'Staf Sekretariat MRP',
                'role' => 'Staf Sekretariat',
                'email' => 'staf@mrp-papuatengah.test',
            ],
            [
                'name' => 'Kepala Bagian Umum',
                'role' => 'Kepala Bagian',
                'email' => 'kepala.bagian@mrp-papuatengah.test',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'is_active' => true,
                    'password' => Hash::make('password'),
                ],
            );
        }
    }
}
