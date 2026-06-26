<?php

declare(strict_types=1);

namespace App\HealthCheck\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
class HealthAggregateData extends Data
{
    public function __construct(
        /** @var HealthStatusData[] */
        public readonly array $services,
        public readonly bool $healthy,
        public readonly string $checkedAt,
    ) {}
}
