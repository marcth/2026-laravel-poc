<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\ApiVersion;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApiVersionTest extends TestCase
{
    public function test_resolve_version_from_accept_header(): void
    {
        // ForceJsonResponse overwrites Accept in the full HTTP stack, so we test
        // ApiVersion::resolveVersion() directly by calling handle() without that middleware.
        $request = Request::create('/api/health/app', 'GET');
        $request->headers->set('Accept', 'application/vnd.api.v3+json');

        (new ApiVersion)->handle($request, fn (Request $r): Response => new Response);

        $this->assertSame('3', app('api.version'));
    }
}
