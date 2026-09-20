<?php

namespace App\Ark\Operations\Documents;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Soft-retire only. Bytes remain on disk in v1. */
final class RetireDocumentAction
{
    public function __construct(
        private readonly RecordDocumentEventAction $events,
    ) {}

    public function handle(Document $document, ?User $actor = null): void
    {
        if (! $document->isActive()) {
            return;
        }

        DB::transaction(function () use ($document, $actor): void {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
            $this->events->handle($locked, DocumentEventType::Retired, $actor);
            $locked->delete();
        });
    }
}
