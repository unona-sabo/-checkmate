<?php

use App\Http\Controllers\Settings\BackupController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    Route::get('settings/backup', [BackupController::class, 'show'])->name('backup.show');
    Route::post('settings/backup/download', [BackupController::class, 'download'])->name('backup.download');
    Route::post('settings/backup/snapshot', [BackupController::class, 'snapshot'])->name('backup.snapshot');
    Route::post('settings/backup/download-snapshot/{filename}', [BackupController::class, 'downloadSnapshot'])->name('backup.download-snapshot');
    Route::delete('settings/backup/snapshot/{filename}', [BackupController::class, 'destroySnapshot'])->name('backup.destroy-snapshot');
    Route::post('settings/backup/restore/{filename}', [BackupController::class, 'restore'])->name('backup.restore');
});
