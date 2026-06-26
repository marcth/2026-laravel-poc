<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Laravel Prototype API',
    version: L5_SWAGGER_CONST_VERSION,
    description: 'Laravel 13 prototype — team starter template and legacy migration target.',
)]
#[OA\Server(url: L5_SWAGGER_CONST_HOST, description: 'Local development')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Token',
    description: 'Laravel Sanctum personal access token.',
)]
class ApiController extends Controller {}
