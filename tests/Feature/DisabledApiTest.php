<?php

namespace HasinHayder\Tyro\Tests\Feature;

use HasinHayder\Tyro\Tests\TestCase;

class DisabledApiTest extends TestCase {
    protected bool $disableTyroApi = true;

    public function test_tyro_routes_are_not_registered_when_disabled(): void {
        $this->get('/api/tyro')->assertNotFound();
        $this->get('/api/tyro/version')->assertNotFound();
    }
}
