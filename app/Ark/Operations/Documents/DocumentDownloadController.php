<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentDownloadController
{
    public function __invoke(
        Request $request,
        Customer $customer,
        Document $document,
        DocumentAuthorize $authorize,
    ): StreamedResponse {
        abort_unless(
            $request->user()?->can(ArkCapability::CustomersManage->value)
            || $request->user()?->can(ArkCapability::RepairOrdersView->value),
            403,
        );

        $authorize->assertBelongsToCustomer($customer, $document);
        $authorize->assertStoragePresent($document);

        return Storage::disk('local')->download(
            $document->storage_path,
            $document->original_name ?? 'document',
            ['Content-Type' => $document->content_type],
        );
    }
}
