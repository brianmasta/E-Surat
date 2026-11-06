@php
    $loadingAgency = \App\Models\AppSetting::agency();
@endphp
<div id="page-loader" class="page-loader" role="status" aria-live="polite" aria-atomic="true" aria-label="Memuat halaman">
    <div class="page-loader-card">
        <div class="page-loader-eyebrow">Sistem Persuratan Digital</div>
        <div class="page-loader-emblem" aria-hidden="true">
            <span class="page-loader-orbit"></span>
            <span class="page-loader-orbit page-loader-orbit-inner"></span>
            <span class="page-loader-logo"><x-app-logo class="h-16 w-16" /></span>
            <span class="page-loader-paper"><x-icon name="mail" class="h-5 w-5" /></span>
            <span class="page-loader-paper page-loader-paper-secondary"><x-icon name="file" class="h-4 w-4" /></span>
        </div>
        <div class="page-loader-brand">{{ $loadingAgency['app_name'] }}</div>
        <div class="page-loader-agency">{{ $loadingAgency['name'] }}</div>
        <div class="page-loader-track" aria-hidden="true"><span></span></div>
        <div class="page-loader-caption" data-loading-caption>Menyiapkan ruang kerja Anda…</div>
        <div class="page-loader-hint">Mohon tunggu sebentar</div>
        <div class="page-loader-footer"><x-icon name="route" class="h-3.5 w-3.5" />Surat tertata. Kerja lebih mudah.</div>
    </div>
</div>
<div id="request-progress" class="request-progress" role="status" aria-label="Memproses permintaan" hidden><span></span></div>
<div id="toast-container" class="toast-container app-toasts" aria-live="polite" aria-atomic="false"></div>
<div id="upload-status" class="upload-status" role="status" aria-live="polite" hidden>
    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
    <span data-upload-label>Mengunggah dokumen…</span>
</div>
@if (session('success') || session('error') || session('warning') || session('status'))
    @php
        $flashNotifications = collect(['success', 'error', 'warning', 'status'])
            ->filter(fn ($key) => session()->has($key))
            ->map(fn ($key) => ['type' => $key === 'status' ? 'info' : $key, 'message' => session($key)])
            ->values();
    @endphp
    <script type="application/json" id="flash-notifications">@json($flashNotifications)</script>
@endif
