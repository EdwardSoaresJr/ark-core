<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Customers\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DocumentVisibilityController
{
    public function __invoke(
        Request $request,
        Customer $customer,
        Document $document,
        DocumentAuthorize $authorize,
        SetDocumentVisibilityAction $visibility,
    ): RedirectResponse {
        $authorize->assertBelongsToCustomer($customer, $document);

        $data = $request->validate([
            'customer_visible' => ['required', 'boolean'],
        ]);

        $visibility->handle($document, (bool) $data['customer_visible'], $request->user());

        $label = $data['customer_visible'] ? 'Document can be shown to the customer.' : 'Document is shop-only again.';

        return redirect()
            ->route('operations.customers.show', $customer)
            ->withFragment('customer-documents')
            ->with('status', $label);
    }
}
