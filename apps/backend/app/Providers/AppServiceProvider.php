<?php

namespace App\Providers;

use App\Auth\PendingProviderIdentityVerifier;
use App\Auth\ProviderIdentityVerifier;
use App\Services\Updates\HostActionRequiredRestartAdapter;
use App\Services\Updates\WebRestartAdapter;
use App\Support\Observability\DatabaseDiagnosticSink;
use App\Support\Observability\DiagnosticSink;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProviderIdentityVerifier::class, PendingProviderIdentityVerifier::class);
        $this->app->bind(DiagnosticSink::class, DatabaseDiagnosticSink::class);
        $this->app->bind(WebRestartAdapter::class, HostActionRequiredRestartAdapter::class);
    }

    public function boot(): void
    {
        //
    }
}
