<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Mail\EmailIntent;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\Mail\ArkMailClient;
use App\Ark\Platform\Mail\ManagedMailGate;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CustomerAccountStatementEmailDelivery
{
    public function __construct(
        private readonly CustomerAccountStatementBuilder $statements,
        private readonly CustomerAccountStatementPdf $pdf,
        private readonly ArkMailClient $mail,
    ) {}

    public function send(Customer $customer, User $actor, ?string $recipientEmail = null): void
    {
        $recipient = strtolower(trim((string) ($recipientEmail ?? $customer->email)));

        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => 'This customer needs a valid email address before a statement can be sent.',
            ]);
        }

        // Recipient isolation: only the customer's own email may receive the statement in this slice.
        $customerEmail = strtolower(trim((string) $customer->email));
        if ($customerEmail === '' || $recipient !== $customerEmail) {
            throw ValidationException::withMessages([
                'email' => 'Statements may only be emailed to the customer account email on file.',
            ]);
        }

        if (! ManagedMailGate::platformSend()) {
            throw new RuntimeException("Email isn't configured yet.");
        }

        $statement = $this->statements->forCustomer($customer);
        $bytes = $this->pdf->bytes($statement);
        $settings = ShopSettings::current();
        $shopName = $settings->shop_name ?: config('app.name', 'ARK');

        $result = $this->mail->sendTransactional(EmailIntent::payload(
            operation: 'statement.send',
            to: $recipient,
            variables: [
                'shop_name' => $shopName,
                'customer_name' => $customer->name,
                'balance_due' => '$'.number_format($statement['total_outstanding_cents'] / 100, 2),
                'as_of' => $statement['as_of']->timezone(config('app.timezone'))->toDateString(),
            ],
            idempotencyKey: 'statement-send-'.$customer->id.'-'.Str::uuid(),
            domainObjectType: 'customer',
            domainObjectId: (string) $customer->id,
            attachments: [[
                'filename' => sprintf('statement-customer-%d.pdf', $customer->id),
                'mime' => 'application/pdf',
                'content_base64' => base64_encode($bytes),
            ]],
            metadata: [
                'customer_id' => $customer->id,
                'actor_id' => $actor->id,
            ],
        ));

        if (($result['ok'] ?? false) !== true) {
            throw new RuntimeException(
                is_string($result['message'] ?? null)
                    ? $result['message']
                    : 'Statement email could not be sent. Platform may need a statement.send mail operation.',
            );
        }
    }
}
