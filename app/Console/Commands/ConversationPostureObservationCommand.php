<?php

namespace App\Console\Commands;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ConversationPostureObservationCommand extends Command
{
    protected $signature = 'ark:conversation-posture-observation
        {--open-only : Only open conversations}
        {--markdown : Output as markdown table}';

    protected $description = 'Notebook query — conversation owner, waiting_on, age in state, reopen count.';

    public function handle(): int
    {
        if (! Schema::hasColumn('conversations', 'waiting_on')) {
            $this->components->warn('Conversation posture columns are not migrated yet.');

            return self::FAILURE;
        }

        $query = Conversation::query()
            ->with('owner:id,name')
            ->orderByDesc('posture_changed_at');

        if ($this->option('open-only')) {
            $query->where('status', ConversationStatus::Open->value);
        }

        $rows = $query->get()->map(function (Conversation $conversation): array {
            $postureAt = $conversation->posture_changed_at ?? $conversation->updated_at;

            return [
                'id' => (string) $conversation->id,
                'contact' => $conversation->contact_address,
                'status' => $conversation->status->value,
                'owner' => $conversation->owner?->name ?? '—',
                'waiting_on' => $conversation->waiting_on?->value ?? '—',
                'age_in_state' => $postureAt?->diffForHumans() ?? '—',
                'age_days' => $postureAt !== null ? (string) (int) $postureAt->diffInDays(now()) : '—',
                'reopens' => (string) $conversation->reopen_count,
            ];
        });

        if ($rows->isEmpty()) {
            $this->components->info('No conversations match.');

            return self::SUCCESS;
        }

        $shopWaiting = $rows->filter(fn (array $row): bool => $row['waiting_on'] === ConversationWaitingOn::Shop->value
            && $row['status'] === ConversationStatus::Open->value);
        $customerWaiting = $rows->filter(fn (array $row): bool => $row['waiting_on'] === ConversationWaitingOn::Customer->value
            && $row['status'] === ConversationStatus::Open->value);

        $this->components->info('Conversation posture observation');
        $this->line('Open · needs shop: '.$shopWaiting->count());
        $this->line('Open · waiting customer: '.$customerWaiting->count());
        $this->line('Needs shop > 2 days: '.$shopWaiting->filter(fn (array $row): bool => (int) $row['age_days'] > 2)->count());
        $this->line('Waiting customer > 14 days: '.$customerWaiting->filter(fn (array $row): bool => (int) $row['age_days'] > 14)->count());
        $this->newLine();

        $headers = ['ID', 'Contact', 'Status', 'Owner', 'Waiting on', 'Age in state', 'Days', 'Reopens'];
        $tableRows = $rows->map(fn (array $row): array => [
            $row['id'],
            $row['contact'],
            $row['status'],
            $row['owner'],
            $row['waiting_on'],
            $row['age_in_state'],
            $row['age_days'],
            $row['reopens'],
        ])->all();

        if ($this->option('markdown')) {
            $this->line('| '.implode(' | ', $headers).' |');
            $this->line('| '.implode(' | ', array_fill(0, count($headers), '---')).' |');

            foreach ($tableRows as $tableRow) {
                $this->line('| '.implode(' | ', $tableRow).' |');
            }

            return self::SUCCESS;
        }

        $this->table($headers, $tableRows);

        return self::SUCCESS;
    }
}
