<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;
use Illuminate\Support\Str;

class ConversationResolver
{
    public function forEmail(string $email): Conversation
    {
        return $this->forContactKey(ConversationContactSurface::Email, $email);
    }

    public function findForContactKey(ConversationContactSurface $surface, string $contactKey): ?Conversation
    {
        $address = $this->normalizeContactKey($surface, $contactKey);

        if ($address === null) {
            return null;
        }

        return Conversation::query()
            ->where('contact_surface', $surface)
            ->where('contact_address', $address)
            ->first();
    }

    public function forContactKey(ConversationContactSurface $surface, string $contactKey): Conversation
    {
        $address = $this->normalizeContactKey($surface, $contactKey);

        abort_if($address === null, 422, 'A valid contact key is required for this conversation.');

        return Conversation::query()->firstOrCreate(
            [
                'contact_surface' => $surface,
                'contact_address' => $address,
            ],
            ['status' => ConversationStatus::Open],
        );
    }

    public function findForPhone(?string $phone): ?Conversation
    {
        return $this->findForContactKey(ConversationContactSurface::Phone, (string) $phone);
    }

    public function forPhone(?string $phone): Conversation
    {
        return $this->forContactKey(ConversationContactSurface::Phone, (string) $phone);
    }

    public function forCustomer(Customer $customer, ?string $preferredEmail = null): Conversation
    {
        if (filled($preferredEmail)) {
            return $this->forEmail($preferredEmail);
        }

        if (filled($customer->phone)) {
            return $this->forPhone($customer->phone);
        }

        if (filled($customer->email)) {
            return $this->forEmail($customer->email);
        }

        abort(422, 'Customer must have a phone or email to open a conversation.');
    }

    public function forWebsiteLead(?string $phone, ?string $websiteContactKey = null): Conversation
    {
        if (filled($phone)) {
            return $this->forPhone($phone);
        }

        $key = filled($websiteContactKey) ? $websiteContactKey : Str::uuid()->toString();

        return $this->forContactKey(ConversationContactSurface::Website, $key);
    }

    private function normalizeContactKey(ConversationContactSurface $surface, string $contactKey): ?string
    {
        return match ($surface) {
            ConversationContactSurface::Phone => PhoneNumber::normalize($contactKey),
            ConversationContactSurface::Email => Str::lower(trim($contactKey)) ?: null,
            ConversationContactSurface::Website => trim($contactKey) !== '' ? trim($contactKey) : null,
            ConversationContactSurface::Messenger => trim($contactKey) !== '' ? trim($contactKey) : null,
        };
    }
}
