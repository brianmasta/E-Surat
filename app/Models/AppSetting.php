<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public static function getValue(string $key, array $default = []): array
    {
        return static::where('key', $key)->first()?->value ?? $default;
    }

    public static function putValue(string $key, array $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function defaultAgency(): array
    {
        return [
            'app_name' => 'E-Surat Pemerintahan',
            'short_name' => 'ES',
            'name' => 'Instansi Pemerintah',
            'unit' => 'Unit Kerja',
            'leader_title' => 'Pimpinan Instansi',
            'city' => 'Tempat',
            'address' => '',
            'email' => '',
            'phone' => '',
        ];
    }

    public static function agency(): array
    {
        return [
            ...static::defaultAgency(),
            ...static::getValue('agency', []),
        ];
    }

    public static function defaultLetterUnits(): array
    {
        return [
            ['code' => 'SET-MRP', 'name' => 'Sekretariat', 'description' => 'Unit sekretariat', 'is_default' => true],
            ['code' => 'MRP', 'name' => 'Lembaga', 'description' => 'Unit lembaga', 'is_default' => false],
        ];
    }

    public static function letterUnits(): array
    {
        $units = static::getValue('letter_units', static::defaultLetterUnits());

        return collect($units)
            ->map(fn (array $unit) => [
                'code' => preg_replace('/\s+/', ' ', strtoupper(trim((string) ($unit['code'] ?? '')))) ?? '',
                'name' => trim((string) ($unit['name'] ?? ($unit['code'] ?? ''))),
                'description' => trim((string) ($unit['description'] ?? '')),
                'is_default' => (bool) ($unit['is_default'] ?? false),
            ])
            ->filter(fn (array $unit) => $unit['code'] !== '' && $unit['name'] !== '')
            ->unique('code')
            ->values()
            ->all();
    }

    public static function letterUnitCodes(): array
    {
        return collect(static::letterUnits())->pluck('code')->all();
    }

    public static function defaultLetterUnitCode(): string
    {
        return collect(static::letterUnits())->firstWhere('is_default', true)['code']
            ?? static::letterUnits()[0]['code']
            ?? 'SET-MRP';
    }
}
