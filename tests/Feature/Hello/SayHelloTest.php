<?php

declare(strict_types=1);

namespace Tests\Feature\Hello;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SayHelloTest extends TestCase
{
    use RefreshDatabase;

    public function test_hello_returns_greeting(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/hello')
            ->assertStatus(200)
            ->assertJson(['message' => 'Hello, World!']);
    }

    public function test_hello_requires_auth(): void
    {
        $this->getJson('/api/hello')->assertStatus(401);
    }
}
