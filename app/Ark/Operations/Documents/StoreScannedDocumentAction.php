<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StoreScannedDocumentAction
{
    public function __construct(
        private readonly DocumentStore $store,
        private readonly DocumentPdfAssembler $assembler,
        private readonly StoreDocumentAction $ownership,
        private readonly RecordDocumentEventAction $events,
    ) {}

    /**
     * @param  list<UploadedFile>  $pages
     */
    public function handle(
        Customer $customer,
        array $pages,
        User $actor,
        DocumentType $type,
        string $title,
        ?string $description = null,
        ?RepairOrder $repairOrder = null,
    ): Document {
        $title = trim($title);
        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => 'Document name is required.',
            ]);
        }

        if ($pages === []) {
            throw ValidationException::withMessages([
                'pages' => 'Capture at least one page.',
            ]);
        }

        if (count($pages) > 40) {
            throw ValidationException::withMessages([
                'pages' => 'A document may have at most 40 pages.',
            ]);
        }

        if ($repairOrder !== null) {
            $this->ownership->assertRepairOrderBelongsToCustomer($customer, $repairOrder);
        }

        $tempPaths = [];
        $assembled = null;

        try {
            foreach ($pages as $index => $page) {
                if (! $page instanceof UploadedFile) {
                    throw ValidationException::withMessages([
                        'pages' => 'Each page must be an image file.',
                    ]);
                }

                $mime = strtolower((string) ($page->getMimeType() ?? ''));
                if (! in_array($mime, ['image/jpeg', 'image/png', 'image/heic', 'image/heif'], true)) {
                    throw ValidationException::withMessages([
                        'pages.'.$index => 'Scan pages must be JPG, PNG, or HEIC.',
                    ]);
                }

                $realPath = $page->getRealPath();
                if ($realPath === false || ! is_file($realPath)) {
                    throw ValidationException::withMessages([
                        'pages.'.$index => 'A scan page could not be read.',
                    ]);
                }

                $temp = sys_get_temp_dir().'/ark-scan-page-'.uniqid('', true);
                if (@copy($realPath, $temp) !== true) {
                    throw ValidationException::withMessages([
                        'pages.'.$index => 'A scan page could not be stored temporarily.',
                    ]);
                }
                $tempPaths[] = $temp;
            }

            $assembled = $this->assembler->assemble($tempPaths);

            return DB::transaction(function () use (
                $customer,
                $actor,
                $type,
                $title,
                $description,
                $repairOrder,
                $assembled,
                $pages,
            ): Document {
                $stored = $this->store->storeAssembledPdf(
                    (int) $customer->id,
                    $assembled,
                    $this->pdfName($title),
                    count($pages),
                );

                $description = $description !== null ? trim($description) : null;
                if ($description === '') {
                    $description = null;
                }

                $document = Document::query()->create([
                    'customer_id' => $customer->id,
                    'repair_order_id' => $repairOrder?->id,
                    'type' => $type,
                    'title' => $title,
                    'description' => $description,
                    'storage_path' => $stored['storage_path'],
                    'content_type' => $stored['content_type'],
                    'original_name' => $stored['original_name'],
                    'byte_size' => $stored['byte_size'],
                    'page_count' => $stored['page_count'],
                    'source' => DocumentSource::Scan,
                    'uploaded_by_user_id' => $actor->id,
                    'captured_at' => now(),
                    'customer_visible' => false,
                ]);

                $this->events->handle($document, DocumentEventType::Scanned, $actor, [
                    'page_count' => count($pages),
                ]);

                if ($repairOrder !== null) {
                    $this->events->handle($document, DocumentEventType::Attached, $actor, [
                        'repair_order_id' => $repairOrder->id,
                    ]);
                }

                return $document;
            });
        } finally {
            foreach ($tempPaths as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            if (is_string($assembled) && is_file($assembled)) {
                @unlink($assembled);
            }
        }
    }

    private function pdfName(string $title): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $title) ?: 'document';

        return trim($safe, '-').'.pdf';
    }
}
