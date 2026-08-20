<?php

namespace App\Providers;

use App\Auth\PendingProviderIdentityVerifier;
use App\Auth\ProviderIdentityVerifier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProviderIdentityVerifier::class, PendingProviderIdentityVerifier::class);
    }

    public function boot(): void
    {
        //
    }
}
