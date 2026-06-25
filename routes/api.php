<?php

use App\Http\Controllers\Api\Mobile\AppVersionController;
use App\Http\Controllers\Api\Mobile\ApprovalController;
use App\Http\Controllers\Api\Mobile\AuthController;
use App\Http\Controllers\Api\Mobile\DashboardController;
use App\Http\Controllers\Api\Mobile\DecisionLetterNumberController;
use App\Http\Controllers\Api\Mobile\DispositionController;
use App\Http\Controllers\Api\Mobile\DocumentController;
use App\Http\Controllers\Api\Mobile\LetterController;
use App\Http\Controllers\Api\Mobile\NumberingController;
use App\Http\Controllers\Api\Mobile\ReferenceController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')->name('api.mobile.')->group(function () {
    Route::get('/version', [AppVersionController::class, 'show'])->name('version');
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/references', [ReferenceController::class, 'index'])->name('references');
        Route::get('/numbering', [NumberingController::class, 'show'])->name('numbering');
        Route::get('/sk-numbers', [DecisionLetterNumberController::class, 'index'])->name('sk-numbers.index');
        Route::post('/sk-numbers', [DecisionLetterNumberController::class, 'store'])->name('sk-numbers.store');
        Route::post('/sk-numbers/{record}', [DecisionLetterNumberController::class, 'update'])->name('sk-numbers.update');
        Route::delete('/sk-numbers/{record}', [DecisionLetterNumberController::class, 'destroy'])->name('sk-numbers.destroy');
        Route::get('/sk-numbers/{record}/file', [DecisionLetterNumberController::class, 'file'])->name('sk-numbers.file');

        Route::get('/letters', [LetterController::class, 'index'])->name('letters.index');
        Route::post('/letters', [LetterController::class, 'store'])->name('letters.store');
        Route::get('/letters/{letter}', [LetterController::class, 'show'])->name('letters.show');
        Route::patch('/letters/{letter}/status', [LetterController::class, 'updateStatus'])->name('letters.status');
        Route::post('/letters/{letter}/document', [LetterController::class, 'uploadDocument'])->name('letters.document.upload');
        Route::get('/letters/{letter}/document', [DocumentController::class, 'letter'])->name('letters.document');

        Route::get('/attachments/{attachment}/document', [DocumentController::class, 'attachment'])->name('attachments.document');

        Route::get('/dispositions', [DispositionController::class, 'index'])->name('dispositions.index');
        Route::post('/dispositions', [DispositionController::class, 'storeStandalone'])->name('dispositions.store-standalone');
        Route::post('/letters/{letter}/dispositions', [DispositionController::class, 'store'])->name('dispositions.store');
        Route::get('/dispositions/{disposition}/scan', [DocumentController::class, 'dispositionScan'])->name('dispositions.scan');
        Route::post('/dispositions/{disposition}/forward', [DispositionController::class, 'forward'])->name('dispositions.forward');
        Route::patch('/dispositions/{disposition}/status', [DispositionController::class, 'updateStatus'])->name('dispositions.status');

        Route::post('/letters/{letter}/approvals/start', [ApprovalController::class, 'start'])->name('approvals.start');
        Route::post('/approvals/{approval}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('/approvals/{approval}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
    });
});
