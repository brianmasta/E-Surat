<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    public string $email = 'admin@mrp-papuatengah.test';
    public string $password = 'password';
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

        session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
};
?>

<main class="grid min-h-screen place-items-center px-4 py-8">
    <section class="w-full max-w-5xl overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm lg:grid lg:grid-cols-[1fr_420px]">
        <div class="bg-slate-900 p-8 text-white lg:p-10">
            <div class="grid h-12 w-12 place-items-center rounded-lg bg-teal-100 font-bold text-teal-800">ES</div>
            <h1 class="mt-8 text-3xl font-bold tracking-normal">E-Surat MRP Papua Tengah</h1>
            <p class="mt-3 max-w-xl text-sm leading-6 text-slate-300">
                Sistem persuratan untuk Sekretariat MRP dan MRP Provinsi Papua Tengah, dengan akses berbeda untuk Admin, Pimpinan, dan Staf.
            </p>

            <div class="mt-8 grid gap-3 text-sm">
                <div class="rounded-lg bg-white/10 p-4">
                    <div class="font-bold">Admin Sekretariat</div>
                    <div class="text-slate-300">Catat surat, kelola setting, disposisi, dan status.</div>
                </div>
                <div class="rounded-lg bg-white/10 p-4">
                    <div class="font-bold">Pimpinan MRP</div>
                    <div class="text-slate-300">Melihat surat dan memberi disposisi elektronik.</div>
                </div>
                <div class="rounded-lg bg-white/10 p-4">
                    <div class="font-bold">Staf Sekretariat</div>
                    <div class="text-slate-300">Memproses surat dan memperbarui status pekerjaan.</div>
                </div>
            </div>
        </div>

        <form wire:submit="login" class="p-6 sm:p-8">
            <p class="text-xs font-bold uppercase text-teal-700">Autentikasi</p>
            <h2 class="mt-1 text-2xl font-bold">Masuk Aplikasi</h2>
            <p class="mt-2 text-sm text-slate-500">Gunakan akun sesuai role pengguna.</p>

            <div class="mt-6 grid gap-4">
                <label class="grid gap-1 text-sm font-bold text-slate-600">
                    Email
                    <input wire:model="email" type="email" autocomplete="email" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                    @error('email') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
                <label class="grid gap-1 text-sm font-bold text-slate-600">
                    Password
                    <input wire:model="password" type="password" autocomplete="current-password" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                    @error('password') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
                <label class="flex items-center gap-3 text-sm font-bold text-slate-600">
                    <input wire:model="remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-teal-700">
                    Ingat sesi login
                </label>
            </div>

            <button type="submit" class="mt-6 min-h-11 w-full rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">
                Masuk
            </button>

            <div class="mt-6 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
                <div class="font-bold text-slate-800">Akun demo</div>
                <div class="mt-2 grid gap-1">
                    <div>Admin: admin@mrp-papuatengah.test</div>
                    <div>Pimpinan: pimpinan@mrp-papuatengah.test</div>
                    <div>Staf: staf@mrp-papuatengah.test</div>
                    <div>Password semua akun: password</div>
                </div>
            </div>
        </form>
    </section>
</main>
