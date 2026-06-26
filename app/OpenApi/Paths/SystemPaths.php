<?php

declare(strict_types=1);

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/up',
    operationId: 'liveness',
    summary: 'Application liveness check',
    description: 'Laravel built-in liveness endpoint. Returns 200 when the application is running, 503 when in maintenance mode. Not authenticated — intended for Docker health checks and load balancers.',
    tags: ['System'],
    responses: [
        new OA\Response(response: 200, description: 'Application is up'),
        new OA\Response(response: 503, description: 'Application is in maintenance mode'),
    ],
)]
class SystemPaths {}
