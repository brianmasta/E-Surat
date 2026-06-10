<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;

class AppVersionController extends Controller
{
    public function show(Request $request)
    {
        $platform = $request->query('platform', 'android');
        $versionCode = (int) $request->query('version_code', 0);
        $versions = AppSetting::getValue('mobile_versions', static::defaults());
        $info = $versions[$platform] ?? $versions['android'] ?? static::defaults()['android'];

        $currentCode = (int) ($info['current_version_code'] ?? 1);
        $minimumCode = (int) ($info['minimum_version_code'] ?? 1);

        return response()->json([
            'platform' => $platform,
            'current_version_name' => (string) ($info['current_version_name'] ?? '1.0.0'),
            'current_version_code' => $currentCode,
            'minimum_version_code' => $minimumCode,
            'update_available' => $versionCode > 0 && $versionCode < $currentCode,
            'update_required' => $versionCode > 0 && $versionCode < $minimumCode,
            'download_url' => (string) ($info['download_url'] ?? ''),
            'release_notes' => (string) ($info['release_notes'] ?? ''),
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    public static function defaults(): array
    {
        return [
            'android' => [
                'current_version_name' => '1.0.0',
                'current_version_code' => 1,
                'minimum_version_code' => 1,
                'download_url' => 'https://esurat.simpelmrp.com/downloads/esurat-android.apk',
                'release_notes' => 'Rilis awal aplikasi Android E-Surat.',
            ],
        ];
    }
}
