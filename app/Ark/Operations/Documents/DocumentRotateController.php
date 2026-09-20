<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Customers\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class DocumentRotateController
{
    public function __invoke(
        Request $request,
        Customer $customer,
        Document $document,
        DocumentAuthorize $authorize,
        RotateDocumentAction $rotate,
    ): RedirectResponse {
        $authorize->assertBelongsToCustomer($customer, $document);

        $data = $request->validate([
            'direction' => ['required', Rule::in(['left', 'right'])],
        ]);

        $rotate->handle($document, $data['direction'], $request->user());

        return redirect()
            ->route('operations.customers.documents.viewer', [$customer, $document])
            ->with('status', 'Document rotated.');
    }
}
