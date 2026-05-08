<?php

namespace Database\Seeders;

use App\Models\DispositionRecipient;
use Illuminate\Database\Seeder;

class DispositionRecipientSeeder extends Seeder
{
    /**
     * Seed disposition recipient master data.
     */
    public function run(): void
    {
        $recipients = [
            ['name' => 'Staf Administrasi', 'position' => 'Staf', 'unit' => 'Subbagian Administrasi'],
            ['name' => 'Kepala Bagian Umum', 'position' => 'Kepala Bagian', 'unit' => 'Umum'],
            ['name' => 'Kepala Subbagian Umum', 'position' => 'Kepala Subbagian', 'unit' => 'Umum'],
            ['name' => 'Analis Kebijakan', 'position' => 'Analis', 'unit' => 'Kebijakan'],
        ];

        foreach ($recipients as $recipient) {
            DispositionRecipient::updateOrCreate(
                ['name' => $recipient['name']],
                [
                    'position' => $recipient['position'],
                    'unit' => $recipient['unit'],
                    'is_active' => true,
                ],
            );
        }
    }
}
