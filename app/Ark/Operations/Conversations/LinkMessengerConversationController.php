<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Operations\Customers\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LinkMessengerConversationController
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        ConversationLinker $linker,
    ): RedirectResponse|JsonResponse {
        abort_unless(
            $conversation->contact_surface === ConversationContactSurface::Messenger,
            422,
            'Only Messenger conversations can be linked with this action.',
        );

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
        ]);

        $customer = Customer::query()->findOrFail($data['customer_id']);
        $psid = trim((string) $conversation->contact_address);

        abort_if($psid === '', 422, 'Messenger conversation is missing a contact key.');

        $existingPsid = trim((string) $customer->messenger_psid);

        abort_if(
            $existingPsid !== '' && $existingPsid !== $psid,
            422,
            $customer->name.' is already linked to a different Messenger profile.',
        );

        $customer->update(['messenger_psid' => $psid]);
        $linker->link($conversation, $customer);

        $status = 'Messenger thread linked to '.$customer->name.'.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $status,
                'customer_id' => $customer->id,
            ]);
        }

        return redirect()
            ->back()
            ->with('status', $status);
    }
}
