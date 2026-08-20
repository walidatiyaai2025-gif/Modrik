<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', fn (): array => ['status' => 'ok'])
    ->name('health');
