<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonStructure(['status', 'env'])
            ->assertJsonPath('status', 'ok');
    }

    public function test_health_endpoint_is_public(): void
    {
        // No auth required
        $this->getJson('/api/health')->assertOk();
    }
}
