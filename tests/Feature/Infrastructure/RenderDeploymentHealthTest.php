<?php

namespace Tests\Feature\Infrastructure;

use Tests\TestCase;

class RenderDeploymentHealthTest extends TestCase
{
    public function test_health_check_endpoint_returns_ok(): void
    {
        $this->get('/up')->assertOk();
    }
}
