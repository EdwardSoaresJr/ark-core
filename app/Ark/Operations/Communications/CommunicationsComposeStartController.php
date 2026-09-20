<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Customers\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Compose Anywhere — search → open Thread for customer (ConversationResolver).
 */
class CommunicationsComposeStartController
{
    public function __invoke(Request $request, ConversationResolver $resolver): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
        ]);

        $customer = Customer::query()->findOrFail((int) $validated['customer_id']);

        try {
            $conversation = $resolver->forCustomer($customer);
        } catch (HttpException $exception) {
            return redirect()
                ->route('operations.customers.show', $customer)
                ->with('error', 'Add a phone or email before composing.');
        }

        return redirect()->to(CommunicationsNeedsYou::url([
            'conversation' => $conversation->id,
            'compose' => 1,
        ]));
    }
}
