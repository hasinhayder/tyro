<?php

namespace HasinHayder\Tyro\Tests\Feature;

use HasinHayder\Tyro\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class CommandAliasTest extends TestCase {
    public function test_command_aliases_are_registered(): void {
        $aliases = [
            'tyro:users' => 'tyro:user-list',
            'tyro:users-with-roles' => 'tyro:user-list-with-roles',
            'tyro:create-user' => 'tyro:user-create',
            'tyro:update-user' => 'tyro:user-update',
            'tyro:delete-user' => 'tyro:user-delete',
            'tyro:suspend-user' => 'tyro:user-suspend',
            'tyro:unsuspend-user' => 'tyro:user-unsuspend',
            'tyro:suspended-users' => 'tyro:user-suspended',
            'tyro:quick-token' => 'tyro:user-token',
            'tyro:prepare-user-model' => 'tyro:user-prepare',
            'tyro:roles' => 'tyro:role-list',
            'tyro:roles-with-privileges' => 'tyro:role-list-with-privileges',
            'tyro:create-role' => 'tyro:role-create',
            'tyro:update-role' => 'tyro:role-update',
            'tyro:delete-role' => 'tyro:role-delete',
            'tyro:purge-roles' => 'tyro:role-purge',
            'tyro:assign-role' => 'tyro:role-assign',
            'tyro:delete-user-role' => 'tyro:role-remove',
            'tyro:privileges' => 'tyro:privilege-list',
            'tyro:add-privilege' => 'tyro:privilege-create',
            'tyro:update-privilege' => 'tyro:privilege-update',
            'tyro:delete-privilege' => 'tyro:privilege-delete',
            'tyro:purge-privileges' => 'tyro:privilege-purge',
            'tyro:attach-privilege' => 'tyro:privilege-attach',
            'tyro:detach-privilege' => 'tyro:privilege-detach',
            'tyro:audit' => 'tyro:audit-list',
            'tyro:login' => 'tyro:auth-login',
            'tyro:logout' => 'tyro:auth-logout',
            'tyro:logout-all' => 'tyro:auth-logout-all',
            'tyro:logout-all-users' => 'tyro:auth-logout-all-users',
            'tyro:me' => 'tyro:auth-me',
            'tyro:run-tests' => 'tyro:test',
            'tyro:postman-collection' => 'tyro:postman',
            'tyro:seed' => 'tyro:seed-all',
            'tyro:doc' => 'tyro:sys-doc',
            'tyro:star' => 'tyro:sys-star',
            'tyro:version' => 'tyro:sys-version',
            'tyro:about' => 'tyro:sys-about',
            'tyro:install' => 'tyro:sys-install',
        ];

        // Commands that are now primary but were previously aliases
        $newPrimaries = [
            'tyro:user-roles',
            'tyro:user-privileges',
            'tyro:role-users',
            'tyro:audit-purge',
            'tyro:seed-roles',
            'tyro:seed-privileges',
            'tyro:publish-config',
            'tyro:publish-migrations',
        ];

        $allCommands = Artisan::all();

        foreach ($aliases as $alias => $primary) {
            $this->assertArrayHasKey($alias, $allCommands, "Alias {$alias} is not registered.");
            $this->assertArrayHasKey($primary, $allCommands, "Primary command {$primary} is not registered.");
        }

        foreach ($newPrimaries as $command) {
            $this->assertArrayHasKey($command, $allCommands, "Command {$command} is not registered.");
        }
    }
}
