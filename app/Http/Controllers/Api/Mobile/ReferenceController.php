<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\ArchiveClassification;
use App\Models\DispositionRecipient;

class ReferenceController extends Controller
{
    public function index()
    {
        return response()->json([
            'agency' => AppSetting::agency(),
            'units' => AppSetting::letterUnits(),
            'default_unit_code' => AppSetting::defaultLetterUnitCode(),
            'classifications' => ArchiveClassification::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'parent_code', 'description']),
            'disposition_recipients' => DispositionRecipient::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'position', 'unit']),
            'letter_options' => [
                'types' => ['Masuk', 'Keluar'],
                'natures' => ['Biasa', 'Penting', 'Rahasia', 'Sangat Rahasia'],
                'urgencies' => ['Normal', 'Segera', 'Sangat Segera'],
                'statuses' => ['Baru', 'Disposisi', 'Diproses', 'Selesai'],
                'retention_categories' => ['Aktif', 'Inaktif', 'Permanen', 'Siap Musnah', 'Dimusnahkan'],
            ],
        ]);
    }
}
