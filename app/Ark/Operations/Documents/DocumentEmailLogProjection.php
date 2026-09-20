<?php

namespace App\Ark\Operations\Documents;

use Illuminate\Support\Collection;

/**
 * Disposable send history for a document — who received the file by email.
 * Rebuilds from document_events (Emailed + transitional Presented/channel=email).
 */
final class DocumentEmailLogProjection
{
    /**
     * Newest first.
     *
     * @return list<array{
     *     recipient_email: string,
     *     actor_name: string|null,
     *     staff_note: string|null,
     *     occurred_at: \Illuminate\Support\Carbon|null,
     *     occurred_label: string,
     * }>
     */
    public function forDocument(Document $document): array
    {
        return $this->rowsForEvents(
            DocumentEvent::query()
                ->where('document_id', $document->id)
                ->whereIn('type', [
                    DocumentEventType::Emailed->value,
                    DocumentEventType::Presented->value,
                ])
                ->with(['actor:id,name'])
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->get(),
        );
    }

    /**
     * Batch summaries for list rows — avoids N+1.
     *
     * @param  list<int>|Collection<int, int>  $documentIds
     * @return array<int, array{count: int, last_label: string|null}>
     */
    public function summariesForDocumentIds(array|Collection $documentIds): array
    {
        $ids = collect($documentIds)->map(fn ($id): int => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        /** @var Collection<int, DocumentEvent> $events */
        $events = DocumentEvent::query()
            ->whereIn('document_id', $ids->all())
            ->whereIn('type', [
                DocumentEventType::Emailed->value,
                DocumentEventType::Presented->value,
            ])
            ->with(['actor:id,name'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('document_id');

        $out = [];

        foreach ($ids as $id) {
            $rows = $this->rowsForEvents($events->get($id, collect()));
            if ($rows === []) {
                $out[$id] = ['count' => 0, 'last_label' => null];

                continue;
            }

            $last = $rows[0];
            $out[$id] = [
                'count' => count($rows),
                'last_label' => sprintf(
                    'Emailed %s · %s',
                    $last['recipient_email'],
                    $last['occurred_label'],
                ),
            ];
        }

        return $out;
    }

    /**
     * @param  Collection<int, DocumentEvent>  $events
     * @return list<array{
     *     recipient_email: string,
     *     actor_name: string|null,
     *     staff_note: string|null,
     *     occurred_at: \Illuminate\Support\Carbon|null,
     *     occurred_label: string,
     * }>
     */
    private function rowsForEvents(Collection $events): array
    {
        $rows = [];

        foreach ($events as $event) {
            if (! $this->isEmailSendEvent($event)) {
                continue;
            }

            $meta = is_array($event->meta) ? $event->meta : [];
            $recipient = strtolower(trim((string) ($meta['recipient_email'] ?? '')));

            if ($recipient === '') {
                continue;
            }

            $note = isset($meta['staff_note']) ? trim((string) $meta['staff_note']) : '';
            $occurred = $event->occurred_at;

            $rows[] = [
                'recipient_email' => $recipient,
                'actor_name' => $event->actor?->name,
                'staff_note' => $note !== '' ? $note : null,
                'occurred_at' => $occurred,
                'occurred_label' => $occurred
                    ? $occurred->timezone(config('app.timezone'))->format('M j, Y g:i A')
                    : '—',
            ];
        }

        return $rows;
    }

    private function isEmailSendEvent(DocumentEvent $event): bool
    {
        if ($event->type === DocumentEventType::Emailed) {
            return true;
        }

        if ($event->type !== DocumentEventType::Presented) {
            return false;
        }

        $meta = is_array($event->meta) ? $event->meta : [];

        return ($meta['channel'] ?? null) === 'email';
    }
}
