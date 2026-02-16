<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Models\AuditLog;

class PurgeAuditLogsCommand extends BaseTyroCommand
{
    protected $signature = 'tyro:audit-purge {--days= : Override configured retention days} {--force : Force purge without confirmation}';

    protected $description = 'Purge old Tyro audit logs';

    public function handle(): int
    {
        $days = $this->option('days') ?: config('tyro.audit.retention_days', 30);
        $date = now()->subDays((int) $days);

        $count = AuditLog::where('created_at', '<', $date)->count();

        if ($count === 0) {
            $this->info('No old audit logs to purge.');
            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm("Are you sure you want to delete {$count} audit logs older than {$days} days?")) {
            $this->info('Purge cancelled.');
            return self::SUCCESS;
        }

        AuditLog::where('created_at', '<', $date)->delete();

        $this->info("Successfully purged {$count} audit logs.");

        return self::SUCCESS;
    }
}
