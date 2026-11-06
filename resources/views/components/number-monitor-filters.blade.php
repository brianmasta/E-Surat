@props(['units', 'year', 'unitModel', 'yearModel', 'checkModel'])

@php
    $years = \App\Models\Letter::where('type', 'Keluar')->whereNotNull('letter_date')
        ->pluck('letter_date')
        ->map(fn ($date) => (int) \Illuminate\Support\Carbon::parse($date)->format('Y'))
        ->merge(range((int) now()->format('Y') + 1, (int) now()->format('Y') - 10))
        ->push((int) $year)->unique()->sortDesc();
@endphp

<div class="grid gap-4 md:grid-cols-3">
    <label class="grid gap-1 text-sm font-bold text-slate-600">
        Unit Surat
        <select wire:model.live="{{ $unitModel }}" class="form-select min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
            <option value="Semua">Semua Unit Surat</option>
            @foreach ($units as $unit)
                <option value="{{ $unit['code'] }}">{{ $unit['code'] }} - {{ $unit['name'] }}</option>
            @endforeach
        </select>
    </label>
    <label class="grid gap-1 text-sm font-bold text-slate-600">
        Tahun Surat
        <select wire:model.live="{{ $yearModel }}" class="form-select min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
            @foreach ($years as $optionYear)
                <option value="{{ $optionYear }}">{{ $optionYear }}</option>
            @endforeach
        </select>
    </label>
    <label class="grid gap-1 text-sm font-bold text-slate-600">
        Cek Nomor Urut
        <input wire:model.live.debounce.300ms="{{ $checkModel }}" type="number" min="1" class="form-control min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950" placeholder="Contoh: 25">
    </label>
</div>
