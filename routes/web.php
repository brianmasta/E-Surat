<?php

use App\Models\ActivityLog;
use App\Models\Letter;
use App\Models\LetterAttachment;
use App\Support\OutgoingLetterDocx;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/login', function () {
    return view('login');
})->middleware('guest')->name('login');

Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::get('/', function () {
    return view('dashboard');
})->middleware('auth')->name('dashboard');

Route::get('/tugas-saya', function () {
    return view('my-tasks');
})->middleware('auth')->name('my-tasks');

Route::get('/pimpinan', function () {
    $user = auth()->user();
    abort_unless($user?->isAdmin() || $user?->isLeader(), 403);

    return view('leadership');
})->middleware('auth')->name('leadership');

Route::get('/kepala-bagian', function () {
    $user = auth()->user();
    abort_unless($user?->isAdmin() || $user?->isDepartmentHead(), 403);

    return view('department-head');
})->middleware('auth')->name('department-head');

Route::get('/letters/{letter}/document', function (Letter $letter) {
    abort_unless($letter->file_path && Storage::disk('public')->exists($letter->file_path), 404);

    ActivityLog::record('document.reviewed', 'Dokumen surat direview: '.$letter->number, $letter);

    return response()->file(Storage::disk('public')->path($letter->file_path));
})->middleware('auth')->name('letters.document.review');

Route::get('/letters/{letter}/document/download', function (Letter $letter) {
    abort_unless($letter->file_path && Storage::disk('public')->exists($letter->file_path), 404);

    ActivityLog::record('document.downloaded', 'Dokumen surat didownload: '.$letter->number, $letter);

    return response()->download(
        Storage::disk('public')->path($letter->file_path),
        basename($letter->file_path),
    );
})->middleware('auth')->name('letters.document.download');

Route::get('/letter-attachments/{attachment}/review', function (LetterAttachment $attachment) {
    abort_unless(Storage::disk('public')->exists($attachment->file_path), 404);

    ActivityLog::record('attachment.reviewed', 'Lampiran direview: '.$attachment->original_name, $attachment);

    return response()->file(Storage::disk('public')->path($attachment->file_path));
})->middleware('auth')->name('letter-attachments.review');

Route::get('/letter-attachments/{attachment}/download', function (LetterAttachment $attachment) {
    abort_unless(Storage::disk('public')->exists($attachment->file_path), 404);

    ActivityLog::record('attachment.downloaded', 'Lampiran didownload: '.$attachment->original_name, $attachment);

    return response()->download(
        Storage::disk('public')->path($attachment->file_path),
        $attachment->original_name,
    );
})->middleware('auth')->name('letter-attachments.download');

Route::get('/letters/{letter}/template/pdf', function (Letter $letter) {
    abort_unless($letter->type === 'Keluar' && $letter->outgoing_body, 404);

    ActivityLog::record('letter.template_pdf_viewed', 'Template surat keluar dibuka untuk PDF: '.$letter->number, $letter);

    return view('letters.outgoing-template', [
        'letter' => $letter,
        'agency' => \App\Models\AppSetting::agency(),
    ]);
})->middleware('auth')->name('letters.template.pdf');

Route::get('/letters/{letter}/template/docx', function (Letter $letter) {
    abort_unless($letter->type === 'Keluar' && $letter->outgoing_body, 404);

    $path = OutgoingLetterDocx::make($letter);

    ActivityLog::record('letter.template_docx_downloaded', 'Template surat keluar diexport DOCX: '.$letter->number, $letter);

    return response()->download($path, 'surat-keluar-'.str_replace(['/', '\\'], '-', $letter->number).'.docx')->deleteFileAfterSend(true);
})->middleware('auth')->name('letters.template.docx');

Route::get('/letters/export/csv', function () {
    $filters = [
        'search' => request('search', ''),
        'type' => request('type', 'Semua'),
        'unit' => request('unit', 'Semua'),
        'status' => request('status', 'Semua'),
        'urgency' => request('urgency', 'Semua'),
        'due' => request('due', 'Semua'),
        'date_from' => request('date_from', ''),
        'date_to' => request('date_to', ''),
    ];

    $letters = Letter::query()
        ->with('classification')
        ->applyDashboardFilters($filters)
        ->latest('letter_date')
        ->latest()
        ->get();

    ActivityLog::record('letters.exported', 'Daftar surat diexport ke CSV.', null, [
        'filters' => $filters,
        'total' => $letters->count(),
    ]);

    $filename = 'daftar-surat-'.now()->format('Ymd-His').'.csv';

    return response()->streamDownload(function () use ($letters) {
        $output = fopen('php://output', 'w');
        fputcsv($output, [
            'Nomor Surat',
            'Nomor Agenda',
            'Jenis',
            'Unit',
            'Kode Arsip',
            'Nama Klasifikasi',
            'Perihal',
            'Pihak Luar',
            'Tanggal Surat',
            'Tanggal Diterima',
            'Sifat',
            'Prioritas',
            'Batas Waktu',
            'Status',
        ]);

        foreach ($letters as $letter) {
            fputcsv($output, [
                $letter->number,
                $letter->agenda_number,
                $letter->type,
                $letter->unit_code,
                $letter->classification_code,
                $letter->classification?->name,
                $letter->subject,
                $letter->external_party,
                $letter->letter_date?->format('Y-m-d'),
                $letter->received_date?->format('Y-m-d'),
                $letter->nature,
                $letter->urgency,
                $letter->due_date?->format('Y-m-d'),
                $letter->status,
            ]);
        }

        fclose($output);
    }, $filename, [
        'Content-Type' => 'text/csv; charset=UTF-8',
    ]);
})->middleware('auth')->name('letters.export');

Route::get('/settings', function () {
    abort_unless(auth()->user()?->isAdmin(), 403);

    return view('settings');
})->middleware('auth')->name('settings');
