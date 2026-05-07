<?php

use Illuminate\Support\Facades\Route;

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

Route::get('/settings', function () {
    abort_unless(auth()->user()?->isAdmin(), 403);

    return view('settings');
})->middleware('auth')->name('settings');
