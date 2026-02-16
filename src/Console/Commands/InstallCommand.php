<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Support\TyroAudit;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Exception\CommandNotFoundException;

class InstallCommand extends BaseTyroCommand {
    protected $signature = 'tyro:sys-install
        {--force : Pass the --force flag to migrate}
        {--dry-run : Print the steps without executing install:api or migrate}';

    protected $aliases = ['tyro:install'];

    protected $description = 'Bootstrap Tyro: set up Sanctum, run migrations, seed roles/privileges, and prepare your User model';

    public function handle(): int {
        if ($this->option('dry-run')) {
            $this->warn('Dry run: skipped install:api and migrate.');

            return self::SUCCESS;
        }

        if (!$this->runRequiredCommand('install:api')) {
            return self::FAILURE;
        }

        if (!$this->runRequiredCommand('tyro:user-prepare')) {
            return self::FAILURE;
        }

        $arguments = [];

        if ($this->option('force')) {
            $arguments['--force'] = true;
        }

        if (!$this->runRequiredCommand('migrate', $arguments)) {
            return self::FAILURE;
        }

        if ($this->input->isInteractive()) {
            if ($this->confirm('Seed Tyro roles, privileges, and the bootstrap admin user now?', true)) {
                if (!$this->runRequiredCommand('tyro:seed-all', ['--force' => true])) {
                    return self::FAILURE;
                }
            }
        } else {
            if (!$this->runRequiredCommand('tyro:seed-roles', ['--force' => true])) {
                return self::FAILURE;
            }

            if (!$this->runRequiredCommand('tyro:seed-privileges', ['--force' => true])) {
                return self::FAILURE;
            }
        }

        TyroAudit::log('system.installed');

        $this->info('Tyro install flow complete.');

        return self::SUCCESS;
    }

    protected function runRequiredCommand(string $command, array $arguments = []): bool {
        $this->info(sprintf('Running %s...', $command));

        $arguments = array_merge(['--no-interaction' => true], $arguments);

        try {
            $exitCode = Artisan::call($command, $arguments);
        } catch (CommandNotFoundException $e) {
            $this->error(sprintf('Command "%s" is not available in this application.', $command));

            return false;
        }

        $capturedOutput = Artisan::output();

        if (trim($capturedOutput) !== '') {
            $this->line($capturedOutput);
        }

        if ($exitCode !== 0) {
            $this->error(sprintf('Command "%s" exited with code %s.', $command, $exitCode));

            return false;
        }

        return true;
    }
}
