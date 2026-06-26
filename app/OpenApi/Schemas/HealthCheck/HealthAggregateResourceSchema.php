<?php

declare(strict_types=1);

namespace App\OpenApi\Schemas\HealthCheck;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'HealthAggregateResource',
    required: ['services', 'healthy', 'checked_at'],
    properties: [
        new OA\Property(property: 'services', type: 'array', items: new OA\Items(ref: '#/components/schemas/HealthStatusResource')),
        new OA\Property(property: 'healthy', type: 'boolean', example: true),
        new OA\Property(property: 'checked_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
class HealthAggregateResourceSchema {}
