<?php

declare(strict_types=1);

namespace App\OpenApi\Schemas\HealthCheck;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'HealthStatusResource',
    required: ['service', 'status', 'code', 'execution_time_ms'],
    properties: [
        new OA\Property(property: 'service', type: 'string', example: 'redis'),
        new OA\Property(property: 'status', type: 'string', enum: ['ok', 'degraded', 'down'], example: 'ok'),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'execution_time_ms', type: 'integer', example: 3),
        new OA\Property(property: 'meta', type: 'object', additionalProperties: new OA\AdditionalProperties),
    ],
    type: 'object',
)]
class HealthStatusResourceSchema {}
