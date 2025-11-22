<?php

namespace HasinHayder\Tyro\Tests\Feature;

use HasinHayder\Tyro\Tests\TestCase;

class HelloTyroTest extends TestCase {
    public function test_hello_tyro_endpoint_returns_message(): void {
        $response = $this->get('/api/tyro');

        $response->assertStatus(200)->assertJsonStructure(['message']);
    }

    public function test_version_endpoint_returns_configured_version(): void {
        config(['tyro.version' => '9.9.9']);

        $response = $this->get('/api/tyro/version');

        $response->assertStatus(200)->assertJson(['version' => '9.9.9']);
    }
}
