<?php

namespace App\Ark\Operations\Documents;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SetDocumentVisibilityAction
{
    public function __construct(
        private readonly RecordDocumentEventAction $events,
    ) {}

    public function handle(Document $document, bool $customerVisible, User $actor): Document
    {
        if (! $document->isActive()) {
            return $document;
        }

        return DB::transaction(function () use ($document, $customerVisible, $actor): Document {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
            $previous = (bool) $locked->customer_visible;
            $locked->customer_visible = $customerVisible;
            $locked->save();

            if ($previous !== $customerVisible) {
                $this->events->handle(
                    $locked,
                    $customerVisible ? DocumentEventType::Shared : DocumentEventType::Unshared,
                    $actor,
                );
            }

            return $locked->fresh() ?? $locked;
        });
    }
}
