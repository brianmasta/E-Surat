<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Admin Sekretariat MRP', 'role' => 'Admin Sekretariat', 'email' => 'admin@mrp-papuatengah.test'],
            ['name' => 'Pimpinan MRP Papua Tengah', 'role' => 'Pimpinan MRP', 'email' => 'pimpinan@mrp-papuatengah.test'],
            ['name' => 'Staf Sekretariat MRP', 'role' => 'Staf Sekretariat', 'email' => 'staf@mrp-papuatengah.test'],
        ];

        foreach ($users as $user) {
            User::create([
                ...$user,
                'password' => Hash::make('password'),
            ]);
        }

        $this->call(LetterSeeder::class);
        $this->call(AppSettingSeeder::class);
    }
}
