<?php

use App\Models\AppSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    public string $email;

    public string $password;

    public bool $remember = false;

    public function login()
    {
        $validated = $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($validated, $this->remember)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password tidak sesuai.',
            ]);
        }

        if (! Auth::user()->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Akun ini sedang nonaktif. Hubungi Admin Sekretariat.',
            ]);
        }

        session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
};
?>

@php($agencyProfile = AppSetting::agency())
@php($androidApp = AppSetting::getValue('mobile_versions', \App\Http\Controllers\Api\Mobile\AppVersionController::defaults())['android'] ?? \App\Http\Controllers\Api\Mobile\AppVersionController::defaults()['android'])

<main class="login-page">
    <section class="login-card" aria-label="Masuk ke aplikasi persuratan">
        <div class="login-brand">
            <div class="login-brand-top">
                <x-app-logo class="h-12 w-12" />
                <div>
                    <div class="login-brand-name">{{ $agencyProfile['app_name'] }}</div>
                    <div class="login-brand-caption">Sistem Persuratan Digital</div>
                </div>
            </div>
            <div class="login-eyebrow">Ruang kerja persuratan</div>
            <h1>Surat tertata.<br><span>Kerja lebih mudah.</span></h1>
            <p class="login-brand-description">Kelola surat, teruskan disposisi, dan pantau tindak lanjut dalam satu ruang kerja untuk {{ $agencyProfile['name'] }}.</p>
            <div class="login-features">
                <div class="login-feature">
                    <span class="login-feature-icon"><x-icon name="inbox" class="h-5 w-5" /></span>
                    <div><strong>Arsip yang terorganisir</strong><p>Surat masuk dan keluar mudah ditemukan kembali.</p></div>
                </div>
                <div class="login-feature">
                    <span class="login-feature-icon"><x-icon name="send" class="h-5 w-5" /></span>
                    <div><strong>Disposisi lebih praktis</strong><p>Teruskan instruksi kepada pihak yang tepat.</p></div>
                </div>
                <div class="login-feature">
                    <span class="login-feature-icon"><x-icon name="route" class="h-5 w-5" /></span>
                    <div><strong>Pantau setiap proses</strong><p>Ikuti perjalanan surat dan penyelesaian tugas.</p></div>
                </div>
            </div>
            <div class="login-brand-footer">{{ $agencyProfile['name'] }}<br>Akses sesuai peran dan tanggung jawab Anda.</div>
        </div>
        <form wire:submit="login" class="login-form">
            <p class="login-form-eyebrow">Selamat datang kembali</p>
            <h2>Masuk ke ruang kerja</h2>
            <p class="login-form-description">Gunakan akun aktif Anda untuk melanjutkan<br class="hidden sm:block"> pengelolaan persuratan.</p>
            <div class="login-fields">
                <div>
                    <label for="login-email" class="form-label">Alamat email</label>
                    <input id="login-email" wire:model="email" type="email" autocomplete="username" required class="form-control @error('email') is-invalid @enderror" placeholder="nama@instansi.go.id" @error('email') aria-invalid="true" aria-describedby="login-email-error" @enderror>
                    @error('email') <div id="login-email-error" class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="login-password" class="form-label">Password</label>
                    <input id="login-password" wire:model="password" type="password" autocomplete="current-password" required class="form-control @error('password') is-invalid @enderror" placeholder="Masukkan password Anda" @error('password') aria-invalid="true" aria-describedby="login-password-error" @enderror>
                    @error('password') <div id="login-password-error" class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <label class="inline-flex items-center gap-2.5 text-xs text-slate-500">
                    <input wire:model="remember" type="checkbox" class="form-check-input h-4 w-4">
                    Ingat sesi login saya
                </label>
            </div>
            <button type="submit" class="btn btn-primary login-submit" wire:loading.attr="disabled" wire:target="login">
                <span class="button-label" wire:loading.remove wire:target="login">Masuk Aplikasi <x-icon name="send" class="h-4 w-4" /></span>
                <span class="button-progress" wire:loading.inline-flex wire:target="login"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Memverifikasi…</span>
            </button>
            <div class="login-divider">AKSES DARI PERANGKAT ANDA</div>
            <a href="{{ $androidApp['download_url'] ?? asset('downloads/esurat-android.apk') }}" class="btn btn-outline-secondary login-download" download>
                <x-icon name="download" class="h-4 w-4" /> Unduh Aplikasi Android
            </a>
            <div class="login-version">Versi Android {{ $androidApp['current_version_name'] ?? '1.0.0' }}</div>
            <div class="login-footer">Powered by <span class="font-semibold text-teal-700">Combrof Digital Solution</span><br>© {{ date('Y') }} · {{ $agencyProfile['app_name'] }}</div>
        </form>
    </section>
</main>
