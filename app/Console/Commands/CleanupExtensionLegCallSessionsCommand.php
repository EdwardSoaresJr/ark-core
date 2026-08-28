<?php

namespace App\Console\Commands;

use App\Ark\Operations\Telephony\CleanupExtensionLegCallSessions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CleanupExtensionLegCallSessionsCommand extends Command
{
    protected $signature = 'comms:cleanup-extension-leg-sessions
                            {--dry-run : List phantom sessions without deleting (default)}
                            {--force : Delete matching call sessions}';

    protected $description = 'Remove phantom call sessions where Asterisk reported a desk extension as the inbound caller.';

    public function handle(CleanupExtensionLegCallSessions $cleanup): int
    {
        if (! Schema::hasTable('call_sessions')) {
            $this->components->warn('call_sessions table is not migrated yet.');

            return self::FAILURE;
        }

        $dryRun = ! $this->option('force');
        $sessions = $cleanup->candidates();

        if ($sessions->isEmpty()) {
            $this->components->info('No extension-leg call sessions found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'From', 'To', 'Status', 'Started'],
            $sessions->map(fn ($session): array => [
                $session->id,
                $session->from_number,
                $session->to_number,
                $session->status->value,
                $session->started_at?->toDateTimeString() ?? '—',
            ])->all(),
        );

        if ($dryRun) {
            $this->components->warn('Dry run — '.$sessions->count().' session(s) would be deleted. Pass --force to delete.');

            return self::SUCCESS;
        }

        $deleted = $cleanup->delete(dryRun: false);
        $this->components->info('Deleted '.count($deleted).' extension-leg call session(s).');

        return self::SUCCESS;
    }
}
