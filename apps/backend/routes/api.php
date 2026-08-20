<?php

use App\Http\Controllers\Api\AcademicContextController;
use App\Http\Controllers\Api\AdvertisingDecisionController;
use App\Http\Controllers\Api\AttemptController;
use App\Http\Controllers\Api\ContentPreparationController;
use App\Http\Controllers\Api\LearningController;
use App\Http\Controllers\Api\OfflineAnswerSyncController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn (): array => ['status' => 'ok'])
    ->name('health');

Route::prefix('/v1')->middleware('auth.fixture')->group(function (): void {
    Route::get('/session', [LearningController::class, 'session'])->name('session.show');
    Route::get('/academic-context', [LearningController::class, 'academicContext'])->name('academic-context.show');
    Route::post('/academic-context/activate', [AcademicContextController::class, 'activate'])->name('academic-context.activate');
    Route::post('/academic-context/reset', [AcademicContextController::class, 'reset'])->name('academic-context.reset');
    Route::get('/lessons/{lessonId}', [LearningController::class, 'lesson'])->name('lessons.show');
    Route::get('/progress', [LearningController::class, 'progress'])->name('progress.index');
    Route::get('/advertising/decisions/{placementCode}', [AdvertisingDecisionController::class, 'show'])->name('advertising-decisions.show');

    Route::post('/attempts', [AttemptController::class, 'start'])->name('attempts.store');
    Route::get('/attempts/{attemptId}', [AttemptController::class, 'show'])->name('attempts.show');
    Route::put('/attempts/{attemptId}/answers/{attemptQuestionId}', [AttemptController::class, 'answer'])->name('attempts.answers.update');
    Route::post('/attempts/{attemptId}/submit', [AttemptController::class, 'submit'])->name('attempts.submit');
    Route::post('/sync/answers', [OfflineAnswerSyncController::class, 'store'])->name('sync.answers.store');

    Route::prefix('/admin')->middleware('auth.content')->group(function (): void {
        Route::post('/preparation-requests', [ContentPreparationController::class, 'create'])->name('preparation-requests.store');
        Route::post('/preparation-imports/validate', [ContentPreparationController::class, 'validateImport'])->name('preparation-imports.validate');
    });
});
