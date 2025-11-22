<?php

namespace HasinHayder\Tyro\Tests\Feature;

use HasinHayder\Tyro\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class DisabledCommandsTest extends TestCase {
    protected bool $disableTyroCommands = true;

    public function test_tyro_commands_are_not_registered_when_disabled(): void {
        $this->assertArrayNotHasKey('tyro:about', Artisan::all());
        $this->assertArrayNotHasKey('tyro:doc', Artisan::all());
        $this->assertArrayNotHasKey('tyro:quick-token', Artisan::all());
        $this->assertArrayNotHasKey('tyro:install', Artisan::all());
        $this->assertArrayNotHasKey('tyro:prepare-user-model', Artisan::all());
    }
}
