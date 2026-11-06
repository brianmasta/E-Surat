@props(['rows', 'historyTitle' => 'Histori Nomor Terpakai'])

@php
    $sequenceLabel = fn ($sequence) => str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    $dateLabel = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->translatedFormat('d M Y') : '-';
@endphp

<div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
    <table class="table table-hover min-w-[760px] w-full text-left text-sm">
        <caption class="sr-only">Ringkasan monitoring nomor surat per unit. Buka detail untuk melihat nomor kosong dan histori.</caption>
        <thead>
            <tr>
                <th scope="col" class="px-5 py-4">Unit Surat</th>
                <th scope="col" class="px-5 py-4">Nomor Berikutnya</th>
                <th scope="col" class="px-5 py-4 text-center">Nomor Terpakai</th>
                <th scope="col" class="px-5 py-4 text-center">Nomor Kosong</th>
                <th scope="col" class="px-5 py-4">Hasil Cek</th>
                <th scope="col" class="px-5 py-4 text-right">Detail</th>
            </tr>
        </thead>
        @forelse ($rows as $row)
            @php($detailId = 'monitor-detail-'.md5($row['unit_code']))
            <tbody x-data="{ open: false }" wire:key="monitor-unit-{{ $row['unit_code'] }}-{{ $row['year'] }}">
                <tr>
                    <th scope="row" class="px-5 py-4 align-middle">
                        <div class="font-bold text-slate-900">{{ $row['unit_code'] }}</div>
                        <div class="mt-1 text-xs font-normal text-slate-500">{{ $row['unit_name'] }} · {{ $row['year'] }}</div>
                    </th>
                    <td class="px-5 py-4">
                        <div class="text-lg font-bold text-teal-700">{{ $sequenceLabel($row['next_sequence']) }}</div>
                        <div class="mt-1 max-w-xs break-all text-xs text-slate-500">{{ $row['next_outgoing_number'] }}</div>
                    </td>
                    <td class="px-5 py-4 text-center"><span class="rounded-full bg-emerald-50 px-3 py-1 text-sm font-bold text-emerald-700">{{ $row['used_count'] }}</span></td>
                    <td class="px-5 py-4 text-center"><span class="rounded-full bg-amber-50 px-3 py-1 text-sm font-bold text-amber-700">{{ $row['missing_count'] }}</span></td>
                    <td class="max-w-[240px] px-5 py-4 text-xs">
                        @if ($row['check_sequence'])
                            <span class="font-semibold {{ $row['check_is_available'] ? 'text-emerald-700' : 'text-rose-700' }}">
                                Nomor {{ $sequenceLabel($row['check_sequence']) }} {{ $row['check_is_available'] ? 'masih kosong' : 'sudah dipakai' }}
                            </span>
                            @if ($row['check_is_available'] && $row['check_recommendation'])
                                <div class="mt-1 text-slate-500">Rekomendasi tanggal lama: {{ $dateLabel($row['check_recommendation']['date']) }}</div>
                            @endif
                        @else
                            <span class="text-slate-400">Masukkan nomor urut untuk dicek.</span>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-right">
                        <button type="button" class="btn btn-outline-secondary inline-flex items-center gap-2 whitespace-nowrap px-3 py-2 text-xs font-semibold"
                                x-on:click="open = !open" x-bind:aria-expanded="open" aria-controls="{{ $detailId }}" aria-label="Detail nomor {{ $row['unit_code'] }}">
                            <span x-text="open ? 'Tutup detail' : 'Lihat detail'">Lihat detail</span>
                            <svg class="h-4 w-4 transition-transform" x-bind:class="{ 'rotate-180': open }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                        </button>
                    </td>
                </tr>
                <tr id="{{ $detailId }}" x-show="open" x-cloak>
                    <td colspan="6" class="monitor-detail-cell">
                        <div class="grid gap-5 p-5">
                            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                                <div class="border-b border-slate-200 px-4 py-3">
                                    <h3 class="text-sm font-bold">Nomor Kosong yang Bisa Dipakai</h3>
                                    <p class="mt-1 text-xs text-slate-500">Maksimal 40 nomor kosong pertama untuk {{ $row['unit_code'] }} pada tahun {{ $row['year'] }}.</p>
                                </div>
                                <table class="table min-w-[680px] w-full text-xs">
                                    <thead><tr><th scope="col" class="px-4 py-3">Urut</th><th scope="col" class="px-4 py-3">Nomor Surat</th><th scope="col" class="px-4 py-3">Rekomendasi Tanggal Lama</th><th scope="col" class="px-4 py-3">Keterangan</th><th scope="col" class="px-4 py-3 text-right">Aksi</th></tr></thead>
                                    <tbody>
                                        @forelse ($row['missing_items'] as $item)
                                            <tr>
                                                <td class="px-4 py-3 font-bold text-amber-700">{{ $sequenceLabel($item['sequence']) }}</td>
                                                <td class="px-4 py-3 font-semibold">{{ $item['suggested_number'] }}</td>
                                                <td class="px-4 py-3">{{ $dateLabel($item['recommended_date']) }}</td>
                                                <td class="px-4 py-3 text-slate-500">{{ $item['recommendation_note'] }}</td>
                                                <td class="px-4 py-3 text-right"><a data-page-link href="{{ route('dashboard', ['create' => 'Keluar', 'unit' => $row['unit_code'], 'number' => $item['suggested_number'], 'letter_date' => $item['recommended_date']]) }}" class="btn btn-primary whitespace-nowrap px-3 py-1.5 text-xs font-semibold">Pakai</a></td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Belum ada celah nomor pada rentang yang terbaca.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                                @if ($row['missing_ranges'])
                                    <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 px-4 py-3 text-xs text-slate-500">
                                        <span>Ringkasan rentang kosong:</span>
                                        @foreach ($row['missing_ranges'] as [$start, $end])
                                            <span class="rounded bg-amber-50 px-2 py-1 font-semibold text-amber-800">{{ $sequenceLabel($start) }}{{ $start !== $end ? ' - '.$sequenceLabel($end) : '' }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </section>
                            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                                <div class="border-b border-slate-200 px-4 py-3"><h3 class="text-sm font-bold">{{ $historyTitle }}</h3><p class="mt-1 text-xs text-slate-500">Maksimal 8 nomor terpakai terbaru dari filter saat ini.</p></div>
                                <table class="table min-w-[680px] w-full text-xs">
                                    <thead><tr><th scope="col" class="px-4 py-3">Urut</th><th scope="col" class="px-4 py-3">Nomor Surat</th><th scope="col" class="px-4 py-3">Tanggal Surat</th><th scope="col" class="px-4 py-3">Perihal</th></tr></thead>
                                    <tbody>
                                        @forelse ($row['recent_used'] as $item)
                                            <tr><td class="px-4 py-3 font-bold text-teal-700">{{ $sequenceLabel($item['sequence']) }}</td><td class="px-4 py-3 font-semibold">{{ $item['number'] }}</td><td class="px-4 py-3">{{ $dateLabel($item['letter_date']) }}</td><td class="px-4 py-3 text-slate-500">{{ $item['subject'] }}</td></tr>
                                        @empty
                                            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">Belum ada nomor surat keluar untuk filter ini.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </section>
                        </div>
                    </td>
                </tr>
            </tbody>
        @empty
            <tbody><tr><td colspan="6" class="px-5 py-8 text-center text-slate-500">Tidak ada Unit Surat untuk filter ini.</td></tr></tbody>
        @endforelse
    </table>
</div>
