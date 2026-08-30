<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class DocumentEmailController
{
    public function __invoke(
        Request $request,
        Customer $customer,
        Document $document,
        DocumentAuthorize $authorize,
        DocumentEmailDelivery $delivery,
    ): RedirectResponse {
        abort_unless(
            $request->user()?->can(ArkCapability::CustomersManage->value)
            || $request->user()?->can(ArkCapability::RepairOrdersManage->value),
            403,
        );

        $authorize->assertBelongsToCustomer($customer, $document);
        $authorize->assertStoragePresent($document);

        $data = $request->validate([
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $recipientEmail = strtolower(trim($data['email'] ?? $customer->email ?? ''));

        if ($recipientEmail === '') {
            throw ValidationException::withMessages([
                'email' => 'Add a customer email on file or enter one to send this document.',
            ])->redirectTo($this->redirectBack($request, $customer, $document));
        }

        try {
            $delivery->send(
                $document,
                $request->user(),
                $recipientEmail,
                $data['message'] ?? null,
            );
        } catch (\App\Ark\Mail\TransactionalMailException $exception) {
            $settingsUrl = route('operations.settings.shop.edit', [
                'section' => 'communications',
                'communications-tab' => 'email',
            ]);

            return redirect()
                ->to($this->redirectBack($request, $customer, $document))
                ->with('status', $exception->result->operatorMessage().' Open Settings → Email: '.$settingsUrl);
        }

        return redirect()
            ->to($this->redirectBack($request, $customer, $document))
            ->with('status', 'Document emailed to '.$recipientEmail.'.');
    }

    private function redirectBack(Request $request, Customer $customer, Document $document): string
    {
        $intended = $request->headers->get('referer');

        if (is_string($intended) && $intended !== '') {
            return $intended;
        }

        return route('operations.customers.documents.viewer', [$customer, $document]);
    }
}
