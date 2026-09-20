<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Collection;

/**
 * Disposable packaging of Documents for staff surfaces.
 * Presentation multiplies; authority does not.
 * Document Timeline is a later projection over document_events.
 */
final class DocumentProjection
{
    public function __construct(
        private readonly DocumentEmailLogProjection $emailLog,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forCustomer(Customer $customer, ?string $type = null, ?string $q = null): Collection
    {
        $query = Document::query()
            ->where('customer_id', $customer->id)
            ->whereNull('deleted_at')
            ->with(['repairOrder:id,repair_order_id', 'uploadedBy:id,name'])
            ->latest('created_at')
            ->latest('id');

        if ($type !== null && $type !== '') {
            $query->where('type', $type);
        }

        if ($q !== null && trim($q) !== '') {
            $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($q)).'%';
            $query->where('title', 'like', $needle);
        }

        return $this->presentMany($query->get());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forRepairOrder(RepairOrder $repairOrder): Collection
    {
        return $this->presentMany(
            Document::query()
                ->where('repair_order_id', $repairOrder->id)
                ->where('customer_id', $repairOrder->customer_id)
                ->whereNull('deleted_at')
                ->latest('created_at')
                ->latest('id')
                ->get(),
            $repairOrder,
        );
    }

    /**
     * Customer docs not yet attached to this RO (attach picker).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function attachableForRepairOrder(RepairOrder $repairOrder): Collection
    {
        return $this->presentMany(
            Document::query()
                ->where('customer_id', $repairOrder->customer_id)
                ->whereNull('deleted_at')
                ->where(function ($query) use ($repairOrder): void {
                    $query->whereNull('repair_order_id')
                        ->orWhere('repair_order_id', '!=', $repairOrder->id);
                })
                ->latest('created_at')
                ->limit(100)
                ->get(),
        );
    }

    /**
     * @param  Collection<int, Document>  $documents
     * @return Collection<int, array<string, mixed>>
     */
    private function presentMany(Collection $documents, ?RepairOrder $repairOrder = null): Collection
    {
        $summaries = $this->emailLog->summariesForDocumentIds($documents->pluck('id'));

        return $documents->map(function (Document $document) use ($repairOrder, $summaries): array {
            return $this->present(
                $document,
                $repairOrder,
                $summaries[(int) $document->id] ?? ['count' => 0, 'last_label' => null],
            );
        });
    }

    /**
     * @param  array{count: int, last_label: string|null}|null  $emailSummary
     * @return array<string, mixed>
     */
    public function present(
        Document $document,
        ?RepairOrder $repairOrder = null,
        ?array $emailSummary = null,
    ): array {
        $customerId = (int) $document->customer_id;
        $ro = $repairOrder ?? $document->repairOrder;
        $emailSummary ??= ['count' => 0, 'last_label' => null];

        return [
            'id' => (int) $document->id,
            'title' => $document->title,
            'type' => $document->type?->value,
            'type_label' => $document->type?->label() ?? 'Document',
            'description' => $document->description,
            'source' => $document->source?->value,
            'source_label' => $document->source?->label(),
            'content_type' => $document->content_type,
            'original_name' => $document->original_name,
            'byte_size' => (int) $document->byte_size,
            'page_count' => $document->page_count,
            'customer_visible' => (bool) $document->customer_visible,
            'is_pdf' => $document->isPdf(),
            'is_image' => $document->isImage(),
            'created_at' => $document->created_at,
            'created_label' => $document->created_at?->timezone(config('app.timezone'))->format('M j, Y'),
            'repair_order_id' => $document->repair_order_id,
            'repair_order_number' => $ro?->repair_order_id,
            'email_send_count' => $emailSummary['count'],
            'email_last_label' => $emailSummary['last_label'],
            'viewer_url' => route('operations.customers.documents.viewer', [$customerId, $document->id]),
            'url' => route('operations.customers.documents.show', [$customerId, $document->id]),
            'download_url' => route('operations.customers.documents.download', [$customerId, $document->id]),
            'email_url' => route('operations.customers.documents.email', [$customerId, $document->id]),
            'rotate_url' => route('operations.customers.documents.rotate', [$customerId, $document->id]),
        ];
    }
}
