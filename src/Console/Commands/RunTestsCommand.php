<?php

namespace HasinHayder\Tyro\Console\Commands;

use Symfony\Component\Process\Process;

class RunTestsCommand extends BaseTyroCommand
{
    protected $signature = 'tyro:sys-test
        {--pest : Force Pest}
        {--phpunit : Force PHPUnit}
        {--filter= : Filter tests by name}
        {--testsuite= : Limit to a specific testsuite}
        {--coverage : Enable coverage collection}
        {--dry-run : Print the command instead of executing it}
        {--extra=* : Additional arguments to pass through}';

    protected $aliases = ['tyro:sys-test', 'tyro:run-tests', 'tyro:test'];

    protected $description = 'Run your project\'s automated tests (Pest by default)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $binary = $this->resolveBinary($dryRun);

        if (! $binary) {
            $this->error('Could not find pest/phpunit binaries under vendor/bin.');

            return self::FAILURE;
        }

        $command = $this->buildCommand($binary);
        $commandString = implode(' ', array_map('escapeshellarg', $command));

        if ($dryRun) {
            $this->info('Would run: '.$commandString);

            return self::SUCCESS;
        }

        $process = new Process($command, base_path());
        $process->setTty(Process::isTtySupported());
        $process->setTimeout(null);
        $process->run(function ($type, $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->error(sprintf('Tests failed with exit code %s.', $process->getExitCode()));

            return $process->getExitCode() ?? self::FAILURE;
        }

        $this->info('Tests finished successfully.');

        return self::SUCCESS;
    }

    protected function buildCommand(string $binary): array
    {
        $command = [PHP_BINARY, $binary];

        if ($this->option('coverage')) {
            $command[] = '--coverage';
        }

        if ($filter = $this->option('filter')) {
            $command[] = '--filter='.$filter;
        }

        if ($suite = $this->option('testsuite')) {
            $command[] = '--testsuite='.$suite;
        }

        if ($extra = $this->option('extra')) {
            $command = array_merge($command, $extra);
        }

        return $command;
    }

    protected function resolveBinary(bool $allowMissing = false): ?string
    {
        $candidates = [];

        if ($this->option('phpunit')) {
            $candidates[] = base_path('vendor/bin/phpunit');
        } elseif ($this->option('pest')) {
            $candidates[] = base_path('vendor/bin/pest');
        } else {
            $candidates[] = base_path('vendor/bin/pest');
            $candidates[] = base_path('vendor/bin/phpunit');
        }

        foreach ($candidates as $candidate) {
            if ($allowMissing || file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
