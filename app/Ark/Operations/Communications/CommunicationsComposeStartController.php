<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Compose Anywhere - open a Communications thread for a customer or a raw phone/email.
 *
 * Phone and email conversations reuse ConversationResolver contact keys; no customer
 * record is created. Later association uses ConversationLinker.
 */
class CommunicationsComposeStartController
{
    public function create(Request $request, ConversationResolver $resolver): View|RedirectResponse
    {
        if ($request->boolean('start')) {
            return $this->open($request, $resolver, fromQuery: true);
        }

        return view('operations.communications.compose', [
            'phone' => (string) $request->query('phone', ''),
            'email' => (string) $request->query('email', ''),
            'channel' => $this->defaultChannel($request),
        ]);
    }

    public function __invoke(Request $request, ConversationResolver $resolver): RedirectResponse
    {
        return $this->open($request, $resolver, fromQuery: false);
    }

    private function open(Request $request, ConversationResolver $resolver, bool $fromQuery): RedirectResponse
    {
        $source = $fromQuery ? $request->query() : $request->all();

        $validated = validator($source, [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'string', 'email:filter', 'max:255'],
        ])->validate();

        $customerId = filled($validated['customer_id'] ?? null) ? (int) $validated['customer_id'] : null;
        $phone = filled($validated['phone'] ?? null) ? (string) $validated['phone'] : null;
        $email = filled($validated['email'] ?? null) ? (string) $validated['email'] : null;

        $provided = collect([
            $customerId !== null ? 'customer_id' : null,
            $phone !== null ? 'phone' : null,
            $email !== null ? 'email' : null,
        ])->filter()->values();

        if ($provided->count() !== 1) {
            throw ValidationException::withMessages([
                'phone' => 'Provide a customer, phone number, or email address.',
            ]);
        }

        try {
            $conversation = match (true) {
                $customerId !== null => $this->forCustomer($resolver, $customerId),
                $phone !== null => $this->forPhone($resolver, $phone),
                default => $resolver->forEmail((string) $email),
            };
        } catch (HttpException $exception) {
            if ($customerId !== null) {
                return redirect()
                    ->route('operations.customers.show', $customerId)
                    ->with('error', 'Add a phone or email before composing.');
            }

            throw ValidationException::withMessages([
                $phone !== null ? 'phone' : 'email' => $exception->getMessage() ?: 'A valid contact is required.',
            ]);
        }

        return redirect()->to(CommunicationsNeedsYou::url([
            'conversation' => $conversation->id,
            'compose' => 1,
        ]));
    }

    private function forCustomer(ConversationResolver $resolver, int $customerId): Conversation
    {
        $customer = Customer::query()->findOrFail($customerId);

        return $resolver->forCustomer($customer);
    }

    private function forPhone(ConversationResolver $resolver, string $phone): Conversation
    {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null || strlen(PhoneNumber::digits($normalized)) < 10) {
            throw ValidationException::withMessages([
                'phone' => 'Enter a valid phone number.',
            ]);
        }

        return $resolver->forPhone($normalized);
    }

    private function defaultChannel(Request $request): string
    {
        if (filled($request->query('email'))) {
            return 'email';
        }

        if (filled($request->query('phone'))) {
            return 'sms';
        }

        $channel = strtolower((string) $request->query('channel', 'sms'));

        return in_array($channel, ['sms', 'email'], true) ? $channel : 'sms';
    }
}
