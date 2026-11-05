<?php

namespace App\Ark\Operations\Booking;

use App\Ark\Operations\Leads\Public\LeadEmailVerification;
use App\Ark\Operations\Leads\Public\LeadPhoneVerification;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Crypt;

/**
 * Optional identity check on online booking (a code ARK Shop sends by text or email).
 *
 * The booking form often runs in a cross-site iframe where the browser drops cookies, so the
 * verification proof travels in an encrypted form field instead of the session. It is consumed
 * once when the booking is saved, and a proof whose verification was already used is refused.
 */
final class BookingIdentity
{
    public function __construct(
        private readonly LeadPhoneVerification $phones,
        private readonly LeadEmailVerification $emails,
    ) {}

    public function phoneReady(): bool
    {
        return config('website.book_identity_verification', true) !== false && $this->phones->bookIdentityGateReady();
    }

    public function emailReady(): bool
    {
        return config('website.book_identity_verification', true) !== false && $this->emails->ready();
    }

    public function required(): bool
    {
        return $this->phoneReady() || $this->emailReady();
    }

    /**
     * A session holding the proof from the form token, plus the browser session's proof when it has one.
     */
    public function store(?string $token, ?Session $browser = null): Store
    {
        $store = new Store('ark_booking', new ArraySessionHandler(30));
        $store->start();

        foreach ($this->decode($token) as $key => $value) {
            $store->put($key, $value);
        }

        if ($browser !== null) {
            foreach ($this->keys() as $key) {
                if (! $store->has($key) && $browser->has($key)) {
                    $store->put($key, $browser->get($key));
                }
            }
        }

        return $store;
    }

    public function token(Session $store): string
    {
        $proof = [];
        foreach ($this->keys() as $key) {
            if ($store->has($key)) {
                $proof[$key] = $store->get($key);
            }
        }

        return $proof === [] ? '' : Crypt::encryptString(json_encode($proof, JSON_THROW_ON_ERROR));
    }

    public function verifiedPhone(Session $store): ?string
    {
        return $this->phones->verifiedPhone($store);
    }

    public function verifiedEmail(Session $store): ?string
    {
        return $this->emails->verifiedEmail($store);
    }

    public function sendPhone(string $phone, ?string $ip, ?string $userAgent): ?string
    {
        return $this->phoneReady() ? $this->phones->send($phone, $ip, $userAgent) : 'Phone verification is not available right now. Call the shop.';
    }

    public function checkPhone(Session $store, string $phone, string $code): ?string
    {
        return $this->phoneReady() ? $this->phones->check($store, $phone, $code) : 'Phone verification is not available right now. Call the shop.';
    }

    public function sendEmail(string $email, ?string $ip, ?string $userAgent): ?string
    {
        return $this->emailReady() ? $this->emails->send($email, $ip, $userAgent) : 'Email verification is not available right now.';
    }

    public function checkEmail(Session $store, string $email, string $code): ?string
    {
        return $this->emailReady() ? $this->emails->check($store, $email, $code) : 'Email verification is not available right now.';
    }

    /**
     * Consumes the proof for this contact. True when no check is required.
     */
    public function accept(Session $store, string $phone, ?string $email): bool
    {
        if (! $this->required()) {
            return true;
        }

        if ($this->phoneReady() && $this->phones->proofUnused($store) && $this->phones->consumeBookProof($store, $phone)) {
            return true;
        }

        return $this->emailReady()
            && filled($email)
            && $this->emails->proofUnused($store)
            && $this->emails->consumeBookProof($store, $email);
    }

    /**
     * @return list<string>
     */
    private function keys(): array
    {
        return [$this->phones->proofKey(), $this->emails->proofKey()];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(?string $token): array
    {
        if (! is_string($token) || trim($token) === '') {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString(trim($token)), true, 8, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return [];
        }

        return is_array($decoded) ? array_intersect_key($decoded, array_flip($this->keys())) : [];
    }
}
