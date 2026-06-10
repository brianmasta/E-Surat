<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Letter;
use App\Models\LetterAttachment;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function letter(Letter $letter)
    {
        abort_unless($letter->file_path && Storage::disk('public')->exists($letter->file_path), 404);

        ActivityLog::record('mobile.document.reviewed', 'Dokumen surat dibuka dari Android: '.$letter->number, $letter);

        return response()->file(Storage::disk('public')->path($letter->file_path));
    }

    public function attachment(LetterAttachment $attachment)
    {
        abort_unless(Storage::disk('public')->exists($attachment->file_path), 404);

        ActivityLog::record('mobile.attachment.reviewed', 'Lampiran dibuka dari Android: '.$attachment->original_name, $attachment);

        return response()->file(Storage::disk('public')->path($attachment->file_path));
    }
}
