<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class DocumentViewerController
{
    public function __invoke(
        Request $request,
        Customer $customer,
        Document $document,
        DocumentAuthorize $authorize,
        DocumentProjection $projection,
        DocumentEmailLogProjection $emailLog,
    ): View {
        abort_unless(
            $request->user()?->can(ArkCapability::CustomersManage->value)
            || $request->user()?->can(ArkCapability::RepairOrdersView->value),
            403,
        );

        $authorize->assertBelongsToCustomer($customer, $document);
        $authorize->assertStoragePresent($document);

        $emailSends = $emailLog->forDocument($document);
        $emailSummary = [
            'count' => count($emailSends),
            'last_label' => $emailSends[0]['occurred_label'] ?? null,
        ];
        if ($emailSends !== []) {
            $emailSummary['last_label'] = sprintf(
                'Emailed %s · %s',
                $emailSends[0]['recipient_email'],
                $emailSends[0]['occurred_label'],
            );
        }

        return view('operations.documents.viewer', [
            'customer' => $customer,
            'document' => $document,
            'row' => $projection->present($document, emailSummary: $emailSummary),
            'emailSends' => $emailSends,
            'canManage' => (bool) $request->user()?->can(ArkCapability::CustomersManage->value),
        ]);
    }
}
