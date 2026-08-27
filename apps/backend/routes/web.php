<?php

use App\Http\Controllers\InstallerController;
use Illuminate\Support\Facades\Route;

Route::get('/install/finish', [InstallerController::class, 'finish'])->name('installer.finish');

Route::middleware('install.uninstalled')->group(function (): void {
    Route::get('/install', [InstallerController::class, 'show'])->name('installer.show');
    Route::post('/install', [InstallerController::class, 'submit'])->name('installer.submit');
});

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'status' => 'bootstrap',
    ]);
});
