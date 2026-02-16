<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Models\AuditLog;

class ListAuditLogsCommand extends BaseTyroCommand
{
    protected $signature = 'tyro:audit-list 
                            {--limit=20 : Number of logs to display} 
                            {--event= : Filter by event type}
                            {--from= : Filter by start date (YYYY-MM-DD)}
                            {--to= : Filter by end date (YYYY-MM-DD)}';

    protected $aliases = ['tyro:audit'];

    protected $description = 'Display recent Tyro audit logs';

    public function handle(): int
    {
        $query = AuditLog::query()->latest();

        if ($event = $this->option('event')) {
            $query->where('event', 'like', "%{$event}%");
        }

        if ($from = $this->option('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $this->option('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->limit((int) $this->option('limit'))->get();

        if ($logs->isEmpty()) {
            $this->warn('No audit logs found.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Date', 'Actor', 'Event', 'Summary', 'IP'],
            $logs->map(fn ($log) => [
                $log->id,
                $log->created_at->format('Y-m-d H:i:s'),
                $log->user_id ? "User:{$log->user_id}" : 'System',
                $log->event,
                $log->summary,
                $log->metadata['ip'] ?? 'N/A',
            ])->toArray()
        );

        return self::SUCCESS;
    }
}
