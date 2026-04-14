<?php

namespace HasinHayder\Tyro\Console\Commands;

use Illuminate\Console\Command;

class UpdateConfigCommand extends Command {
    protected $signature = 'tyro:update-config {--with-backup : Create backup before publishing}';

    protected $description = 'Update tyro config with the latest version';

    public function handle(): int {
        $appConfigPath = config_path('tyro.php');

        if ($this->option('with-backup')) {
            $backupFilename = 'tyro-backup-' . date('Y-m-d-His') . '.txt';
            $backupPath = config_path($backupFilename);

            if (file_exists($appConfigPath)) {
                copy($appConfigPath, $backupPath);
                $this->info("  ✓ Backup created: {$backupFilename}");
            }
        }

        $this->call('vendor:publish', [
            '--tag' => 'tyro-config',
            '--force' => true,
        ]);

        return self::SUCCESS;
    }
}