<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Models\AuditLog;

class ListAuditLogsCommand extends BaseTyroCommand
{
    protected $signature = 'tyro:audit {--limit=20 : Number of logs to display} {--event= : Filter by event type}';

    protected $description = 'Display recent Tyro audit logs';

    public function handle(): int
    {
        $query = AuditLog::query()->latest();

        if ($event = $this->option('event')) {
            $query->where('event', 'like', "%{$event}%");
        }

        $logs = $query->limit((int) $this->option('limit'))->get();

        if ($logs->isEmpty()) {
            $this->warn('No audit logs found.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Date', 'User ID', 'Event', 'Target', 'IP'],
            $logs->map(fn ($log) => [
                $log->id,
                $log->created_at->format('Y-m-d H:i:s'),
                $log->user_id ?? 'System',
                $log->event,
                $log->auditable_type ? basename($log->auditable_type) . ':' . $log->auditable_id : 'N/A',
                $log->metadata['ip'] ?? 'N/A',
            ])->toArray()
        );

        return self::SUCCESS;
    }
}
