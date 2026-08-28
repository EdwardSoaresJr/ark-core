<?php

namespace App\Ark\Operations\Documents;

use App\Models\User;

final class RecordDocumentEventAction
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function handle(
        Document $document,
        DocumentEventType $type,
        ?User $actor = null,
        ?array $meta = null,
    ): DocumentEvent {
        return DocumentEvent::query()->create([
            'document_id' => $document->id,
            'type' => $type,
            'actor_user_id' => $actor?->id,
            'meta' => $meta,
            'occurred_at' => now(),
        ]);
    }
}
