<?php

namespace App\Providers;

use App\Auth\PendingProviderIdentityVerifier;
use App\Auth\ProviderIdentityVerifier;
use App\Services\InstallerRuntime;
use App\Services\LaravelInstallerRuntime;
use App\Services\Updates\ActivationHealthChecker;
use App\Services\Updates\BackendReleaseOperator;
use App\Services\Updates\CommandBackendReleaseOperator;
use App\Services\Updates\CpanelDashboardRestartAdapter;
use App\Services\Updates\CpanelLivePayloadActivator;
use App\Services\Updates\GovernedActivationHealthChecker;
use App\Services\Updates\LivePayloadActivator;
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
        $this->app->bind(LivePayloadActivator::class, CpanelLivePayloadActivator::class);
        $this->app->bind(WebRestartAdapter::class, CpanelDashboardRestartAdapter::class);
        $this->app->bind(BackendReleaseOperator::class, CommandBackendReleaseOperator::class);
        $this->app->bind(ActivationHealthChecker::class, GovernedActivationHealthChecker::class);
        $this->app->bind(InstallerRuntime::class, LaravelInstallerRuntime::class);
    }

    public function boot(): void
    {
        //
    }
}
