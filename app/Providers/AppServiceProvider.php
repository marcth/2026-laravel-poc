<?php

declare(strict_types=1);

namespace App\Providers;

use App\HealthCheck\Contracts\HealthCheckInterface;
use App\HealthCheck\Services\HealthCheckerService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /** @var array<class-string<HealthCheckInterface>> $checks */
        $checks = config('health-check.checks', []);
        $this->app->tag($checks, 'health-checks');

        $this->app->bind(
            HealthCheckerService::class,
            function (Application $app): HealthCheckerService {
                /** @var iterable<HealthCheckInterface> $checkers */
                $checkers = $app->tagged('health-checks');

                return new HealthCheckerService($checkers);
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
