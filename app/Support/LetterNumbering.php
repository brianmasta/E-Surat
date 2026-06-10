<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\Letter;
use Illuminate\Support\Collection;

class LetterNumbering
{
    public static function nextLetterNumber(?string $unitCode = null, ?string $classificationCode = null): string
    {
        $unitCode = static::normalizeUnitCode($unitCode);
        $numbering = static::settings();
        $separator = $numbering['separator'];
        $parts = [
            $classificationCode ?: $numbering['prefix'],
            str_pad((string) $numbering['next_sequence'], 3, '0', STR_PAD_LEFT),
            $unitCode,
        ];

        if ($numbering['include_month']) {
            $parts[] = now()->format('m');
        }

        if ($numbering['include_year']) {
            $parts[] = now()->format('Y');
        }

        return implode($separator, $parts);
    }

    public static function nextAgendaNumber(?string $unitCode = null): string
    {
        $unitCode = static::normalizeUnitCode($unitCode);
        $year = now()->format('Y');
        $next = Letter::query()
            ->where('type', 'Masuk')
            ->where('unit_code', $unitCode)
            ->whereYear('received_date', $year)
            ->count() + 1;

        return 'AG/'.$unitCode.'/'.$year.'/'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public static function advanceNextLetterSequence(string $number): void
    {
        $numbering = AppSetting::getValue('letter_numbering', [
            'prefix' => '800',
            'unit_code' => AppSetting::defaultLetterUnitCode(),
            'separator' => '/',
            'include_month' => true,
            'include_year' => true,
            'next_sequence' => 1,
        ]);

        $separator = (string) ($numbering['separator'] ?? '/');
        $parts = $separator !== '' ? explode($separator, $number) : [];
        $sequence = isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : null;

        if ($sequence === null) {
            return;
        }

        AppSetting::putValue('letter_numbering', [
            ...$numbering,
            'next_sequence' => max((int) ($numbering['next_sequence'] ?? 1), $sequence + 1),
        ]);
    }

    public static function monitor(?string $unitCode = null, ?int $year = null, ?int $checkSequence = null): array
    {
        $unitCode = static::normalizeUnitCode($unitCode);
        $year = $year ?: (int) now()->format('Y');
        $numbering = AppSetting::getValue('letter_numbering', [
            'prefix' => '800',
            'separator' => '/',
            'include_month' => true,
            'include_year' => true,
            'next_sequence' => 1,
        ]);
        $nextSequence = max(1, (int) ($numbering['next_sequence'] ?? 1));

        $used = Letter::query()
            ->where('type', 'Keluar')
            ->where('unit_code', $unitCode)
            ->whereYear('letter_date', $year)
            ->get(['id', 'number', 'subject', 'letter_date'])
            ->map(fn (Letter $letter) => [
                'sequence' => static::extractSequenceFromNumber($letter->number),
                'number' => $letter->number,
                'subject' => $letter->subject,
                'letter_date' => $letter->letter_date,
            ])
            ->filter(fn (array $item) => $item['sequence'] !== null)
            ->sortBy('sequence')
            ->values();

        $usedSequences = $used->pluck('sequence')->unique()->sort()->values()->all();
        $maxSequence = max($nextSequence - 1, $usedSequences ? max($usedSequences) : 0);
        $missing = collect(range(1, max(1, $maxSequence)))
            ->reject(fn (int $sequence) => in_array($sequence, $usedSequences, true))
            ->values()
            ->all();

        $missingItems = collect($missing)
            ->take(40)
            ->map(function (int $sequence) use ($used, $unitCode, $year) {
                $recommendation = static::recommendedDateForSequence($sequence, $used);

                return [
                    'sequence' => $sequence,
                    'recommended_date' => $recommendation['date']?->toDateString(),
                    'recommendation_note' => $recommendation['note'],
                    'suggested_number' => static::numberForSequence($sequence, $unitCode, $year, $recommendation['date']),
                ];
            })
            ->values()
            ->all();

        $checkSequence = $checkSequence ? max(1, $checkSequence) : null;
        $checkRecommendation = $checkSequence && ! in_array($checkSequence, $usedSequences, true)
            ? static::recommendedDateForSequence($checkSequence, $used)
            : null;

        return [
            'unit_code' => $unitCode,
            'year' => $year,
            'next_sequence' => $nextSequence,
            'next_outgoing_number' => static::nextLetterNumber($unitCode),
            'next_agenda_number' => static::nextAgendaNumber($unitCode),
            'used_count' => count($usedSequences),
            'missing_count' => count($missing),
            'missing_sequences' => array_slice($missing, 0, 40),
            'missing_items' => $missingItems,
            'missing_ranges' => static::sequenceRanges($missing),
            'recent_used' => $used->reverse()->take(8)->map(fn (array $item) => [
                ...$item,
                'letter_date' => $item['letter_date']?->toDateString(),
            ])->values()->all(),
            'check_sequence' => $checkSequence,
            'check_is_available' => $checkSequence ? ! in_array($checkSequence, $usedSequences, true) : null,
            'check_recommendation' => $checkRecommendation ? [
                'date' => $checkRecommendation['date']?->toDateString(),
                'note' => $checkRecommendation['note'],
            ] : null,
        ];
    }

    public static function recommendedDateForSequence(int $sequence, Collection $used): array
    {
        $previous = $used
            ->filter(fn (array $item) => $item['sequence'] < $sequence && $item['letter_date'])
            ->sortByDesc('sequence')
            ->first();
        $next = $used
            ->filter(fn (array $item) => $item['sequence'] > $sequence && $item['letter_date'])
            ->sortBy('sequence')
            ->first();

        if ($previous && $next && $previous['letter_date']->isSameDay($next['letter_date'])) {
            return [
                'date' => $previous['letter_date'],
                'note' => 'Mengikuti tanggal nomor '.str_pad((string) $previous['sequence'], 3, '0', STR_PAD_LEFT).' dan '.str_pad((string) $next['sequence'], 3, '0', STR_PAD_LEFT),
            ];
        }

        if ($previous) {
            return [
                'date' => $previous['letter_date'],
                'note' => 'Disarankan mengikuti tanggal nomor sebelumnya '.str_pad((string) $previous['sequence'], 3, '0', STR_PAD_LEFT),
            ];
        }

        if ($next) {
            return [
                'date' => $next['letter_date'],
                'note' => 'Disarankan mengikuti tanggal nomor berikutnya '.str_pad((string) $next['sequence'], 3, '0', STR_PAD_LEFT),
            ];
        }

        return [
            'date' => null,
            'note' => 'Belum ada histori pembanding',
        ];
    }

    public static function numberForSequence(int $sequence, string $unitCode, int $year, mixed $recommendedDate = null): string
    {
        $numbering = static::settings();
        $date = $recommendedDate ?: now()->setYear($year);
        $parts = [
            $numbering['prefix'],
            str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
            $unitCode,
        ];

        if ($numbering['include_month']) {
            $parts[] = $date->format('m');
        }

        if ($numbering['include_year']) {
            $parts[] = $date->format('Y');
        }

        return implode($numbering['separator'], $parts);
    }

    public static function extractSequenceFromNumber(?string $number): ?int
    {
        if (! $number) {
            return null;
        }

        $separator = static::settings()['separator'];
        $parts = $separator !== '' ? explode($separator, $number) : [];

        return isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : null;
    }

    public static function sequenceRanges(array $sequences): array
    {
        $ranges = [];
        $start = null;
        $previous = null;

        foreach ($sequences as $sequence) {
            if ($start === null) {
                $start = $previous = $sequence;

                continue;
            }

            if ($sequence === $previous + 1) {
                $previous = $sequence;

                continue;
            }

            $ranges[] = [$start, $previous];
            $start = $previous = $sequence;
        }

        if ($start !== null) {
            $ranges[] = [$start, $previous];
        }

        return array_slice($ranges, 0, 12);
    }

    public static function normalizeUnitCode(?string $unitCode = null): string
    {
        $codes = AppSetting::letterUnitCodes();

        return in_array($unitCode, $codes, true) ? $unitCode : AppSetting::defaultLetterUnitCode();
    }

    public static function defaultOutgoingBody(): string
    {
        return "Dengan hormat,\n\nSehubungan dengan perihal tersebut di atas, bersama ini kami sampaikan surat ini untuk menjadi perhatian dan tindak lanjut sebagaimana mestinya.\n\nDemikian disampaikan. Atas perhatian dan kerja samanya, kami ucapkan terima kasih.";
    }

    public static function settings(): array
    {
        $numbering = AppSetting::getValue('letter_numbering', [
            'prefix' => '800',
            'separator' => '/',
            'include_month' => true,
            'include_year' => true,
            'next_sequence' => 1,
        ]);

        return [
            'prefix' => (string) ($numbering['prefix'] ?? '800'),
            'separator' => (string) ($numbering['separator'] ?? '/'),
            'include_month' => (bool) ($numbering['include_month'] ?? true),
            'include_year' => (bool) ($numbering['include_year'] ?? true),
            'next_sequence' => max(1, (int) ($numbering['next_sequence'] ?? 1)),
        ];
    }
}
