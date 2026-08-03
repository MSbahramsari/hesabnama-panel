<?php

namespace App\Providers;

use App\Contracts\TaxPlatformGateway;
use App\Services\DemoTaxPlatformGateway;
use App\Services\MoadianTaxPlatformGateway;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TaxPlatformGateway::class, fn ($app) => config('services.moadian.driver') === 'real'
            ? $app->make(MoadianTaxPlatformGateway::class)
            : $app->make(DemoTaxPlatformGateway::class));
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
