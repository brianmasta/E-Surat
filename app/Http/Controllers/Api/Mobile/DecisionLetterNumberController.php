<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Models\DecisionLetterNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DecisionLetterNumberController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $unitCode = $this->normalizeUnitCode($request->query('unit_code'));
        $year = (int) ($request->query('year') ?: now()->format('Y'));
        $settings = AppSetting::getValue('sk_numbering', [
            'include_year' => true,
            'classification_code' => 'SK',
        ]);

        $records = DecisionLetterNumber::query()
            ->with('creator')
            ->where('unit_code', $unitCode)
            ->where('year', $year)
            ->when($request->query('status', 'Semua') !== 'Semua', fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->query('search'), function ($query, string $search) {
                $like = '%'.$search.'%';

                $query->where(function ($query) use ($like) {
                    $query
                        ->where('number', 'like', $like)
                        ->orWhere('classification_code', 'like', $like)
                        ->orWhere('title', 'like', $like)
                        ->orWhere('notes', 'like', $like);
                });
            })
            ->orderByDesc('sequence')
            ->paginate((int) $request->query('per_page', 20));

        $nextSequence = ((int) DecisionLetterNumber::query()
            ->where('unit_code', $unitCode)
            ->where('year', $year)
            ->max('sequence')) + 1;
        $classificationCode = (string) ($settings['classification_code'] ?? 'SK');
        $includeYear = (bool) ($settings['include_year'] ?? true);

        return response()->json([
            'settings' => [
                'unit_code' => $unitCode,
                'year' => $year,
                'classification_code' => $classificationCode,
                'include_year' => $includeYear,
                'next_sequence' => $nextSequence,
                'next_number' => $this->formatNumber($classificationCode, $nextSequence, $unitCode, $year, $includeYear),
            ],
            'units' => AppSetting::letterUnits(),
            'statuses' => $this->statuses(),
            'missing_items' => $this->missingItems($unitCode, $year, $classificationCode, $includeYear),
            'data' => $records->getCollection()->map(fn (DecisionLetterNumber $record) => $this->formatRecord($record))->values(),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        [$validated, $sequence, $includeYear] = $this->validatedPayload($request);

        if ($this->duplicateExists($validated['unit_code'], (int) $validated['year'], $sequence)) {
            return response()->json([
                'message' => 'Nomor urut SK ini sudah dipakai.',
                'errors' => ['sequence' => ['Nomor urut SK ini sudah dipakai.']],
            ], 422);
        }

        $record = DecisionLetterNumber::create([
            ...$this->recordPayload($validated, $sequence, $includeYear),
            ...$this->filePayload($request),
            'created_by' => $request->user()->id,
        ]);

        AppSetting::putValue('sk_numbering', [
            'include_year' => $includeYear,
            'classification_code' => $this->normalizeClassificationCode($validated['classification_code']),
        ]);
        ActivityLog::record('mobile.sk_number.created', 'Nomor SK dicatat dari Android: '.$record->number, $record);

        return response()->json([
            'message' => 'Nomor SK berhasil dicatat.',
            'record' => $this->formatRecord($record->fresh('creator')),
        ], 201);
    }

    public function update(Request $request, DecisionLetterNumber $record)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        [$validated, $sequence, $includeYear] = $this->validatedPayload($request);

        if ($this->duplicateExists($validated['unit_code'], (int) $validated['year'], $sequence, $record->id)) {
            return response()->json([
                'message' => 'Nomor urut SK ini sudah dipakai.',
                'errors' => ['sequence' => ['Nomor urut SK ini sudah dipakai.']],
            ], 422);
        }

        $fileData = $this->filePayload($request, $record);
        $record->update([
            ...$this->recordPayload($validated, $sequence, $includeYear),
            ...$fileData,
        ]);

        AppSetting::putValue('sk_numbering', [
            'include_year' => $includeYear,
            'classification_code' => $this->normalizeClassificationCode($validated['classification_code']),
        ]);
        ActivityLog::record('mobile.sk_number.updated', 'Nomor SK diperbarui dari Android: '.$record->number, $record);

        return response()->json([
            'message' => 'Nomor SK berhasil diperbarui.',
            'record' => $this->formatRecord($record->fresh('creator')),
        ]);
    }

    public function destroy(Request $request, DecisionLetterNumber $record)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($record->file_path && Storage::disk('public')->exists($record->file_path)) {
            Storage::disk('public')->delete($record->file_path);
        }

        ActivityLog::record('mobile.sk_number.deleted', 'Nomor SK dihapus dari Android: '.$record->number, $record);
        $record->delete();

        return response()->json(['message' => 'Nomor SK berhasil dihapus.']);
    }

    public function file(Request $request, DecisionLetterNumber $record)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($record->file_path && Storage::disk('public')->exists($record->file_path), 404);

        ActivityLog::record('mobile.sk_number.file_reviewed', 'File SK dibuka dari Android: '.$record->number, $record);

        return response()->file(Storage::disk('public')->path($record->file_path));
    }

    private function validatedPayload(Request $request): array
    {
        $validated = $request->validate([
            'unit_code' => ['required', Rule::in(AppSetting::letterUnitCodes())],
            'classification_code' => ['required', 'string', 'max:40', 'not_regex:/[\/\\\\]/'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'include_year' => ['boolean'],
            'title' => ['required', 'string', 'max:255'],
            'decision_date' => ['nullable', 'date'],
            'sequence' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in($this->statuses())],
            'notes' => ['nullable', 'string', 'max:1000'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $sequence = (int) ($validated['sequence'] ?: $this->nextSequence($validated['unit_code'], (int) $validated['year']));
        $includeYear = filter_var($validated['include_year'] ?? true, FILTER_VALIDATE_BOOLEAN);

        return [$validated, $sequence, $includeYear];
    }

    private function recordPayload(array $validated, int $sequence, bool $includeYear): array
    {
        $classificationCode = $this->normalizeClassificationCode($validated['classification_code']);

        return [
            'unit_code' => $validated['unit_code'],
            'classification_code' => $classificationCode,
            'sequence' => $sequence,
            'year' => (int) $validated['year'],
            'number' => $this->formatNumber($classificationCode, $sequence, $validated['unit_code'], (int) $validated['year'], $includeYear),
            'title' => $validated['title'],
            'decision_date' => $validated['decision_date'] ?: null,
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?: null,
        ];
    }

    private function filePayload(Request $request, ?DecisionLetterNumber $record = null): array
    {
        if (! $request->hasFile('file')) {
            return [];
        }

        if ($record?->file_path && Storage::disk('public')->exists($record->file_path)) {
            Storage::disk('public')->delete($record->file_path);
        }

        $file = $request->file('file');

        return [
            'file_path' => $file->store('file-sk', 'public'),
            'file_original_name' => $file->getClientOriginalName(),
            'file_mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ];
    }

    private function duplicateExists(string $unitCode, int $year, int $sequence, ?int $ignoreId = null): bool
    {
        return DecisionLetterNumber::query()
            ->where('unit_code', $unitCode)
            ->where('year', $year)
            ->where('sequence', $sequence)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    private function missingItems(string $unitCode, int $year, string $classificationCode, bool $includeYear): array
    {
        $used = DecisionLetterNumber::query()
            ->where('unit_code', $unitCode)
            ->where('year', $year)
            ->pluck('sequence')
            ->map(fn ($sequence) => (int) $sequence)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $max = max($this->nextSequence($unitCode, $year) - 1, $used ? max($used) : 0);

        return collect(range(1, max(1, $max)))
            ->reject(fn (int $sequence) => in_array($sequence, $used, true))
            ->take(30)
            ->map(fn (int $sequence) => [
                'sequence' => $sequence,
                'number' => $this->formatNumber($classificationCode, $sequence, $unitCode, $year, $includeYear),
            ])
            ->values()
            ->all();
    }

    private function nextSequence(string $unitCode, int $year): int
    {
        return ((int) DecisionLetterNumber::query()
            ->where('unit_code', $unitCode)
            ->where('year', $year)
            ->max('sequence')) + 1;
    }

    private function formatRecord(DecisionLetterNumber $record): array
    {
        return [
            'id' => $record->id,
            'unit_code' => $record->unit_code,
            'classification_code' => $record->classification_code,
            'sequence' => $record->sequence,
            'year' => $record->year,
            'number' => $record->number,
            'title' => $record->title,
            'decision_date' => $record->decision_date?->toDateString(),
            'status' => $record->status,
            'notes' => $record->notes,
            'creator_name' => $record->creator?->name,
            'has_file' => (bool) $record->file_path,
            'file_original_name' => $record->file_original_name,
            'file_url' => $record->file_path ? route('api.mobile.sk-numbers.file', $record, false) : null,
            'created_at' => $record->created_at?->toIso8601String(),
            'updated_at' => $record->updated_at?->toIso8601String(),
        ];
    }

    private function formatNumber(string $classificationCode, int $sequence, string $unitCode, int $year, bool $includeYear): string
    {
        $parts = [$this->normalizeClassificationCode($classificationCode), str_pad((string) $sequence, 3, '0', STR_PAD_LEFT), $unitCode];

        if ($includeYear) {
            $parts[] = (string) $year;
        }

        return implode('/', $parts);
    }

    private function normalizeUnitCode(?string $unitCode): string
    {
        return in_array($unitCode, AppSetting::letterUnitCodes(), true)
            ? $unitCode
            : AppSetting::defaultLetterUnitCode();
    }

    private function normalizeClassificationCode(?string $classificationCode): string
    {
        $code = strtoupper(trim((string) $classificationCode));

        return $code !== '' ? $code : 'SK';
    }

    private function statuses(): array
    {
        return ['Dipesan', 'Dipakai', 'Batal'];
    }
}
