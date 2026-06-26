<?php

declare(strict_types=1);

namespace App\HealthCheck\Data;

use App\HealthCheck\Enums\ServiceStatus;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
class HealthStatusData extends Data
{
    public function __construct(
        public readonly string $service,
        public readonly ServiceStatus $status,
        public readonly int $code,
        public readonly int $executionTimeMs,
        /** @var array<string, mixed> */
        public readonly array $meta = [],
    ) {}
}
