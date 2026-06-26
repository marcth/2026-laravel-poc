<?php

namespace Tests\Feature\HealthCheck;

use App\HealthCheck\Data\HealthStatusData;
use App\HealthCheck\Enums\ServiceStatus;
use App\HealthCheck\Services\HealthCheckerService;
use Tests\TestCase;

class CheckServiceHealthCommandTest extends TestCase
{
    public function test_health_check_command_displays_all_services(): void
    {
        $this->mock(HealthCheckerService::class, function ($mock): void {
            $mock->shouldReceive('checkAll')->once()->andReturn([
                new HealthStatusData('laravel', ServiceStatus::Ok, 200, 1, []),
                new HealthStatusData('mariadb', ServiceStatus::Ok, 200, 2, []),
                new HealthStatusData('redis', ServiceStatus::Ok, 200, 3, []),
            ]);
        });

        $this->artisan('health:check')->assertExitCode(0);
    }

    public function test_health_check_command_displays_single_service(): void
    {
        $this->mock(HealthCheckerService::class, function ($mock): void {
            $mock->shouldReceive('checkOne')->with('redis')->once()->andReturn(
                new HealthStatusData('redis', ServiceStatus::Ok, 200, 2, [])
            );
        });

        $this->artisan('health:check redis')->assertExitCode(0);
    }

    public function test_health_check_command_errors_on_unknown_service(): void
    {
        $this->artisan('health:check unknown-service')
            ->expectsOutputToContain('Unknown service')
            ->assertExitCode(0);
    }
}
