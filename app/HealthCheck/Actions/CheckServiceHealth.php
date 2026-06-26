<?php

declare(strict_types=1);

namespace App\HealthCheck\Actions;

use App\HealthCheck\Data\HealthAggregateData;
use App\HealthCheck\Data\HealthStatusData;
use App\HealthCheck\Enums\ServiceStatus;
use App\HealthCheck\Services\HealthCheckerService;
use Illuminate\Console\Command;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use OpenApi\Attributes as OA;

class CheckServiceHealth
{
    use AsAction;

    public string $commandSignature = 'health:check {service? : Service name (omit for all)}';

    public string $commandDescription = 'Check the health status of application services';

    public function __construct(private readonly HealthCheckerService $checker) {}

    /** @return HealthStatusData|HealthStatusData[] */
    public function handle(?string $service = null): HealthStatusData|array
    {
        return $service === null
            ? $this->checker->checkAll()
            : $this->checker->checkOne($service);
    }

    #[OA\Get(
        path: '/api/health',
        operationId: 'getHealthAggregate',
        summary: 'All services health check',
        security: [['sanctum' => []]],
        tags: ['HealthCheck'],
        responses: [
            new OA\Response(response: 200, description: 'All services healthy', content: new OA\JsonContent(ref: '#/components/schemas/HealthAggregateResource')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 503, description: 'One or more services degraded or down', content: new OA\JsonContent(ref: '#/components/schemas/HealthAggregateResource')),
        ],
    )]
    #[OA\Get(
        path: '/api/health/{service}',
        operationId: 'getServiceHealth',
        summary: 'Single service health check',
        security: [['sanctum' => []]],
        tags: ['HealthCheck'],
        parameters: [
            new OA\Parameter(name: 'service', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['app', 'mariadb', 'redis'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Service is healthy', content: new OA\JsonContent(ref: '#/components/schemas/HealthStatusResource')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Unknown service', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 503, description: 'Service is degraded or down', content: new OA\JsonContent(ref: '#/components/schemas/HealthStatusResource')),
        ],
    )]
    public function asController(Request $request, ?string $service = null): JsonResponse
    {
        try {
            $result = $this->handle($service);
        } catch (\InvalidArgumentException) {
            return response()->json(['message' => 'Unknown service'], 404);
        }

        if ($service === null) {
            /** @var HealthStatusData[] $result */
            $healthy = collect($result)->every(fn (HealthStatusData $s) => $s->status === ServiceStatus::Ok);
            $aggregate = new HealthAggregateData(
                services: $result,
                healthy: $healthy,
                checkedAt: now()->toIso8601String(),
            );

            return response()->json($aggregate, $healthy ? 200 : 503);
        }

        /** @var HealthStatusData $result */
        return response()->json($result, $result->code);
    }

    public function asCommand(Command $command): void
    {
        $service = $command->argument('service');
        $service = is_string($service) ? $service : null;

        try {
            $result = $this->handle($service);
        } catch (\InvalidArgumentException) {
            $command->error('Unknown service: '.($service ?? ''));

            return;
        }

        if ($service === null) {
            /** @var HealthStatusData[] $result */
            $rows = array_map(
                fn (HealthStatusData $d) => [$d->service, $d->status->value, $d->code, $d->executionTimeMs],
                $result
            );
        } else {
            /** @var HealthStatusData $result */
            $rows = [[$result->service, $result->status->value, $result->code, $result->executionTimeMs]];
        }

        $command->table(['Service', 'Status', 'Code', 'Time (ms)'], $rows);
    }
}
