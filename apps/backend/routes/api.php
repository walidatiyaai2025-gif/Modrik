<?php

use App\Http\Controllers\Api\AcademicContextController;
use App\Http\Controllers\Api\AdvertisingDecisionController;
use App\Http\Controllers\Api\AttemptController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContentPreparationController;
use App\Http\Controllers\Api\LearningController;
use App\Http\Controllers\Api\OfflineAnswerSyncController;
use App\Http\Controllers\Api\ProviderAuthController;
use App\Http\Controllers\Api\StudentContentCatalogueController;
use App\Http\Controllers\Api\StudentNotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn (): array => ['status' => 'ok'])
    ->name('health');

Route::prefix('/v1/auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/email/verify', [AuthController::class, 'verifyEmail'])->name('auth.email.verify');
    Route::post('/password/recovery', [AuthController::class, 'requestRecovery'])->name('auth.password.recovery');
    Route::post('/password/reset', [AuthController::class, 'resetPassword'])->name('auth.password.reset');
    Route::post('/providers/{provider}/login-intents', [ProviderAuthController::class, 'loginIntent'])->name('auth.providers.login-intent');
    Route::post('/providers/{provider}/callback', [ProviderAuthController::class, 'callback'])->name('auth.providers.callback');

    Route::middleware('auth.production')->group(function (): void {
        Route::post('/email/verification', [AuthController::class, 'resendVerification'])->name('auth.email.verification.resend');
        Route::post('/reauthenticate', [AuthController::class, 'reauthenticate'])->name('auth.reauthenticate');
        Route::get('/sessions', [AuthController::class, 'sessions'])->name('auth.sessions.index');
        Route::delete('/sessions/current', [AuthController::class, 'logoutCurrent'])->name('auth.sessions.current.destroy');
        Route::delete('/sessions/others', [AuthController::class, 'revokeOtherSessions'])->name('auth.sessions.others.destroy');
        Route::delete('/sessions', [AuthController::class, 'revokeAllSessions'])->name('auth.sessions.destroy');

        Route::middleware('auth.recent')->group(function (): void {
            Route::put('/password', [AuthController::class, 'changePassword'])->name('auth.password.update');
            Route::delete('/account', [AuthController::class, 'deleteAccount'])->name('auth.account.destroy');
            Route::post('/providers/{provider}/link-intents', [ProviderAuthController::class, 'linkIntent'])->name('auth.providers.link-intent');
            Route::delete('/providers/{provider}', [ProviderAuthController::class, 'unlink'])->name('auth.providers.unlink');
        });
    });
});

Route::prefix('/v1')->middleware('auth.modrik')->group(function (): void {
    Route::get('/session', [LearningController::class, 'session'])->name('session.show');
    Route::get('/academic-tracks', [AcademicContextController::class, 'catalogue'])->name('academic-tracks.index');
    Route::get('/academic-context', [LearningController::class, 'academicContext'])->name('academic-context.show');
    Route::post('/academic-context/activate', [AcademicContextController::class, 'activate'])
        ->middleware('auth.verified-password')->name('academic-context.activate');
    Route::post('/academic-context/reset', [AcademicContextController::class, 'reset'])
        ->middleware('auth.verified-password')->name('academic-context.reset');
    Route::get('/content-catalogue', [StudentContentCatalogueController::class, 'index'])->name('content-catalogue.index');
    Route::get('/lessons/{lessonId}', [LearningController::class, 'lesson'])->name('lessons.show');
    Route::get('/progress', [LearningController::class, 'progress'])->name('progress.index');
    Route::get('/notifications', [StudentNotificationController::class, 'index'])->name('notifications.index');
    Route::put('/notifications/read-all', [StudentNotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::put('/notifications/{notificationId}/read', [StudentNotificationController::class, 'read'])->name('notifications.read');
    Route::get('/advertising/decisions/{placementCode}', [AdvertisingDecisionController::class, 'show'])
        ->name('advertising-decisions.show');

    Route::post('/attempts', [AttemptController::class, 'start'])
        ->middleware('auth.verified-password')->name('attempts.store');
    Route::get('/attempts/{attemptId}', [AttemptController::class, 'show'])->name('attempts.show');
    Route::put('/attempts/{attemptId}/answers/{attemptQuestionId}', [AttemptController::class, 'answer'])
        ->middleware('auth.verified-password')->name('attempts.answers.update');
    Route::post('/attempts/{attemptId}/submit', [AttemptController::class, 'submit'])
        ->middleware('auth.verified-password')->name('attempts.submit');
    Route::post('/sync/answers', [OfflineAnswerSyncController::class, 'store'])
        ->middleware('auth.verified-password')->name('sync.answers.store');

    Route::prefix('/admin')->middleware(['auth.content', 'auth.verified-password'])->group(function (): void {
        Route::post('/preparation-requests', [ContentPreparationController::class, 'create'])
            ->name('preparation-requests.store');
        Route::post('/preparation-imports/validate', [ContentPreparationController::class, 'validateImport'])
            ->name('preparation-imports.validate');
    });
});
