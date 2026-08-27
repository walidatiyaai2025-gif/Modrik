<?php

namespace App\Providers;

use App\Auth\PendingProviderIdentityVerifier;
use App\Auth\ProviderIdentityVerifier;
use App\Services\InstallerRuntime;
use App\Services\LaravelInstallerRuntime;
use App\Services\Updates\ActivationHealthChecker;
use App\Services\Updates\BackendReleaseOperator;
use App\Services\Updates\CommandBackendReleaseOperator;
use App\Services\Updates\GovernedActivationHealthChecker;
use App\Services\Updates\GovernedDemoRestartAdapter;
use App\Services\Updates\WebRestartAdapter;
use App\Support\Observability\DatabaseDiagnosticSink;
use App\Support\Observability\DiagnosticSink;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $maxUpdatePackageKb = (int) config('updates.max_package_kb', 131072);
        config()->set('livewire.temporary_file_upload.rules', ['required', 'file', 'max:'.$maxUpdatePackageKb]);

        $this->app->bind(ProviderIdentityVerifier::class, PendingProviderIdentityVerifier::class);
        $this->app->bind(DiagnosticSink::class, DatabaseDiagnosticSink::class);
        $this->app->bind(WebRestartAdapter::class, GovernedDemoRestartAdapter::class);
        $this->app->bind(BackendReleaseOperator::class, CommandBackendReleaseOperator::class);
        $this->app->bind(ActivationHealthChecker::class, GovernedActivationHealthChecker::class);
        $this->app->bind(InstallerRuntime::class, LaravelInstallerRuntime::class);
    }

    public function boot(): void
    {
        //
    }
}
