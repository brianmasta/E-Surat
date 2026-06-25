<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Disposition;
use App\Models\Letter;
use App\Models\LetterAttachment;
use App\Support\DocumentAccess;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function letter(Letter $letter)
    {
        abort_unless(DocumentAccess::canViewLetter(request()->user(), $letter), 403);
        abort_unless($letter->file_path && Storage::disk('public')->exists($letter->file_path), 404);

        ActivityLog::record('mobile.document.reviewed', 'Dokumen surat dibuka dari Android: '.$letter->number, $letter);

        return response()->file(Storage::disk('public')->path($letter->file_path));
    }

    public function attachment(LetterAttachment $attachment)
    {
        abort_unless(DocumentAccess::canViewAttachment(request()->user(), $attachment), 403);
        abort_unless(Storage::disk('public')->exists($attachment->file_path), 404);

        ActivityLog::record('mobile.attachment.reviewed', 'Lampiran dibuka dari Android: '.$attachment->original_name, $attachment);

        return response()->file(Storage::disk('public')->path($attachment->file_path));
    }

    public function dispositionScan(Disposition $disposition)
    {
        abort_unless(DocumentAccess::canViewDispositionScan(request()->user(), $disposition), 403);
        abort_unless($disposition->scan_path && Storage::disk('public')->exists($disposition->scan_path), 404);

        ActivityLog::record('mobile.disposition_scan.reviewed', 'Scan disposisi dibuka dari Android: '.$disposition->letter?->number, $disposition);

        return response()->file(Storage::disk('public')->path($disposition->scan_path));
    }
}
