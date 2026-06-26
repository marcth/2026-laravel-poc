<?php

declare(strict_types=1);

namespace App\Hello\Actions;

use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;
use OpenApi\Attributes as OA;

class SayHello
{
    use AsAction;

    /** @return array{message: string} */
    public function handle(): array
    {
        return ['message' => 'Hello, World!'];
    }

    #[OA\Get(
        path: '/api/hello',
        operationId: 'getHello',
        summary: 'Hello World',
        security: [['sanctum' => []]],
        tags: ['Hello'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns a greeting.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Hello, World!'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
        ],
    )]
    public function asController(): JsonResponse
    {
        return response()->json($this->handle());
    }
}
