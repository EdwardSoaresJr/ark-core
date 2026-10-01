<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Customers\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DocumentRetireController
{
    public function __invoke(
        Request $request,
        Customer $customer,
        Document $document,
        DocumentAuthorize $authorize,
        RetireDocumentAction $retire,
    ): RedirectResponse {
        $authorize->assertBelongsToCustomer($customer, $document);
        $ro = $document->repairOrder;
        $retire->handle($document, $request->user());

        if ($request->filled('return_to_ro') && $ro !== null) {
            return redirect()
                ->route('operations.repair-orders.show', $ro)
                ->with('status', 'Document retired.');
        }

        return redirect()
            ->route('operations.customers.show', $customer)
            ->withFragment('customer-documents')
            ->with('status', 'Document retired.');
    }
}
