<?php

namespace App\Console\Commands;

use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationLink;
use App\Ark\Operations\Conversations\ConversationParticipant;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Email threads that a document send attached to the repair order's customer although the document went to someone
 * else (a warranty company, a fleet manager). Repairing one removes only the customer link, so the thread stops
 * presenting itself as the customer's while staying on the repair order, and in the customer's history through it.
 * With --file-as-history it is also filed as resolved, since a document send is history rather than inbox work.
 * Messages, attachments, send logs and audit history are never touched.
 */
class RepairDocumentEmailRecipientsCommand extends Command
{
    protected $signature = 'ark:documents:repair-email-recipients
                            {--apply : Remove the customer links of the threads that are safe to repair}
                            {--file-as-history : With --apply, also file those threads as resolved history, as a document send now does}
                            {--rollback= : Restore the links and recipients removed or added by an earlier --apply, from its backup file}';

    protected $description = 'Report, repair or restore email threads attached to a customer who was not the recipient of an emailed document.';

    public function handle(): int
    {
        if (filled($this->option('rollback'))) {
            return $this->rollback((string) $this->option('rollback'));
        }

        $candidates = $this->candidates();

        if ($candidates === []) {
            $this->components->info('No email threads are attached to a customer they were not sent to.');

            return self::SUCCESS;
        }

        $this->table(
            ['Thread', 'Recipient', 'Customer', 'Repair orders', 'Messages', 'Replies', 'Lane', 'Verdict'],
            array_map(fn (array $row): array => [
                $row['conversation_id'],
                $row['recipient'],
                $row['customer_id'],
                implode(', ', $row['repair_orders']),
                $row['messages'],
                $row['inbound'],
                $row['lane'],
                $row['safe'] ? 'safe to repair' : 'manual review: '.$row['reason'],
            ], $candidates),
        );

        $safe = array_values(array_filter($candidates, fn (array $row): bool => $row['safe']));
        $this->components->info(count($safe).' safe to repair, '.(count($candidates) - count($safe)).' for manual review.');

        if (! $this->option('apply')) {
            $this->components->info('Dry run: nothing changed. Run with --apply to repair the safe threads.');

            return self::SUCCESS;
        }

        if ($safe === []) {
            return self::SUCCESS;
        }

        $backup = ['created_at' => now()->toIso8601String(), 'links' => [], 'participants' => [], 'postures' => []];
        $fileAsHistory = (bool) $this->option('file-as-history');

        DB::transaction(function () use ($safe, $fileAsHistory, &$backup): void {
            foreach ($safe as $row) {
                $link = ConversationLink::query()->findOrFail($row['link_id']);
                $backup['links'][] = $link->getAttributes();
                $link->delete();

                $hasRecipient = ConversationParticipant::query()
                    ->where('conversation_id', $row['conversation_id'])
                    ->where('participant_type', ConversationParticipantType::Customer->value)
                    ->exists();

                if (! $hasRecipient) {
                    $participant = ConversationParticipant::query()->create([
                        'conversation_id' => $row['conversation_id'],
                        'participant_type' => ConversationParticipantType::Customer,
                        'display_name' => $row['recipient'],
                    ]);
                    $backup['participants'][] = $participant->id;
                }

                $conversation = Conversation::query()->findOrFail($row['conversation_id']);
                if ($fileAsHistory && $conversation->status !== ConversationStatus::Resolved) {
                    $backup['postures'][$conversation->id] = array_intersect_key($conversation->getAttributes(),
                        array_flip(['status', 'waiting_on', 'follow_up_due_at', 'resolved_at', 'posture_changed_at']));
                    $conversation->forceFill([
                        'status' => ConversationStatus::Resolved,
                        'waiting_on' => ConversationWaitingOn::Customer,
                        'resolved_at' => now(),
                        'follow_up_due_at' => null,
                        'posture_changed_at' => now(),
                    ])->save();
                }
            }
        });

        $path = storage_path('app/remediation/document-email-recipients-'.now()->format('Ymd-His').'.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($backup, JSON_PRETTY_PRINT));

        $this->components->info('Repaired '.count($safe).' thread(s)'.($backup['postures'] !== [] ? ', '.count($backup['postures']).' filed as history' : '').'. Backup: '.$path);
        $this->components->info('Undo with --rollback='.$path);

        return self::SUCCESS;
    }

    /**
     * @return list<array{conversation_id: int, link_id: int, recipient: string, customer_id: int, repair_orders: list<string>, messages: int, inbound: int, lane: string, safe: bool, reason: string|null}>
     */
    private function candidates(): array
    {
        $customerType = (new Customer)->getMorphClass();
        $repairOrderType = (new RepairOrder)->getMorphClass();
        $rows = [];

        $conversations = Conversation::query()
            ->where('contact_surface', ConversationContactSurface::Email->value)
            ->whereHas('links', fn ($query) => $query->where('linkable_type', $customerType))
            ->orderBy('id')
            ->get();

        foreach ($conversations as $conversation) {
            $links = ConversationLink::query()->where('conversation_id', $conversation->id)->get();
            $customerLinks = $links->where('linkable_type', $customerType);
            $customers = Customer::query()->whereIn('id', $customerLinks->pluck('linkable_id'))->get()->keyBy('id');

            $mismatched = $customerLinks->filter(function (ConversationLink $link) use ($customers, $conversation): bool {
                $customer = $customers->get($link->linkable_id);

                return $customer === null || ! $conversation->isWithCustomer($customer);
            });

            if ($mismatched->isEmpty()) {
                continue;
            }

            $messages = $conversation->messages()->get(['id', 'direction', 'metadata']);
            $inbound = $messages->where('direction', OperationalCommunicationDirection::Inbound)->count();
            $repairOrderIds = $links->where('linkable_type', $repairOrderType)->pluck('linkable_id');
            $customerParticipant = ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->whereIn('customer_id', $mismatched->pluck('linkable_id'))
                ->exists();
            $allRecorded = $messages->every(fn ($message): bool => $message->direction === OperationalCommunicationDirection::Outbound
                && filled($message->metadata['repair_order_id'] ?? null));

            foreach ($mismatched as $link) {
                $customer = $customers->get($link->linkable_id);
                $reason = match (true) {
                    $inbound > 0 => 'the recipient replied',
                    $customerParticipant => 'the customer is a participant',
                    $customerLinks->count() > 1 => 'more than one customer is linked',
                    $customer === null => 'the linked customer no longer exists',
                    blank($customer->email) => 'the customer has no email on file to compare',
                    $repairOrderIds->isEmpty() => 'no repair order is linked',
                    ! $allRecorded => 'a message was not recorded by a document send',
                    default => null,
                };

                $rows[] = [
                    'conversation_id' => $conversation->id,
                    'link_id' => $link->id,
                    'recipient' => (string) $conversation->contact_address,
                    'customer_id' => (int) $link->linkable_id,
                    'repair_orders' => RepairOrder::query()->whereIn('id', $repairOrderIds)->pluck('repair_order_id')->map(fn ($number): string => '#'.$number)->all(),
                    'messages' => $messages->count(),
                    'inbound' => $inbound,
                    'lane' => app(ConversationWork::class)->lane($conversation),
                    'safe' => $reason === null,
                    'reason' => $reason,
                ];
            }
        }

        return $rows;
    }

    private function rollback(string $path): int
    {
        if (! File::exists($path)) {
            $this->components->error('No backup at '.$path);

            return self::FAILURE;
        }

        $backup = json_decode(File::get($path), true);
        $restored = 0;

        DB::transaction(function () use ($backup, &$restored): void {
            foreach ($backup['links'] ?? [] as $attributes) {
                if (! ConversationLink::query()->whereKey($attributes['id'])->exists()) {
                    DB::table((new ConversationLink)->getTable())->insert($attributes);
                    $restored++;
                }
            }

            foreach ($backup['postures'] ?? [] as $conversationId => $attributes) {
                DB::table((new Conversation)->getTable())->where('id', $conversationId)->update($attributes);
            }

            ConversationParticipant::query()
                ->whereIn('id', $backup['participants'] ?? [])
                ->whereNull('customer_id')
                ->whereDoesntHave('messages')
                ->delete();
        });

        $this->components->info('Restored '.$restored.' link(s) from '.$path);

        return self::SUCCESS;
    }
}
