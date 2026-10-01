<?php

namespace App\Console\Commands;

use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\ShopExcellence\OwnerOperationalPulse;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\Mail\EmailIntent;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\Mail\ArkMailClient;
use App\Ark\Platform\Mail\ManagedMailGate;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SendOwnerDailyDigestCommand extends Command
{
    protected $signature = 'shop-excellence:owner-digest {--date= : Shop date (Y-m-d) to summarize} {--email= : Send to one address instead of all admins}';

    protected $description = 'Email the daily owner digest to shop admins';

    public function handle(OwnerOperationalPulse $pulse): int
    {
        if (! ShopExcellenceTargets::ownerDigestEnabled() && ! filled($this->option('email'))) {
            $this->info('Owner digest disabled in shop excellence targets.');

            return self::SUCCESS;
        }

        if (filled($this->option('date'))) {
            [$from, $to] = OperationalReportDateScope::resolveRange(
                (string) $this->option('date'),
                (string) $this->option('date'),
            );
            $digest = $pulse->dailyDigest($from, $to);
        } else {
            $digest = $pulse->dailyDigest();
        }

        $recipients = filled($this->option('email'))
            ? collect([(object) ['email' => (string) $this->option('email')]])
            : User::query()
                ->active()
                ->whereNotNull('email')
                ->whereHas('roles', fn ($query) => $query->where('name', ArkRole::Admin->value))
                ->get();

        if ($recipients->isEmpty()) {
            $this->error('No admin recipients for owner digest.');

            return self::FAILURE;
        }

        if (! ManagedMailGate::platformSend()) {
            $this->error("Email isn't configured yet.");

            return self::FAILURE;
        }

        $mail = app(ArkMailClient::class);
        $shopName = ShopSettings::current()->shop_name ?: config('app.name', 'ARK');

        foreach ($recipients as $recipient) {
            try {
                $result = $mail->sendTransactional(EmailIntent::payload(
                    operation: 'owner.digest',
                    to: (string) $recipient->email,
                    variables: [
                        'shop_name' => $shopName,
                        'range_label' => (string) ($digest['range_label'] ?? ''),
                        'headlines' => $digest['headlines'] ?? [],
                        'reconciliation' => $digest['reconciliation'] ?? [],
                        'priorities' => $digest['priorities'] ?? [],
                        'financial_url' => (string) ($digest['financial_url'] ?? ''),
                        'day_review_url' => (string) ($digest['day_review_url'] ?? $digest['bookend_url'] ?? ''),
                        'owner_pl_url' => (string) ($digest['owner_pl_url'] ?? ''),
                    ],
                    idempotencyKey: 'owner-digest-'.Str::uuid(),
                ));
                if (($result['ok'] ?? false) !== true) {
                    throw new \RuntimeException(is_string($result['message'] ?? null) ? $result['message'] : 'Email could not be sent.');
                }
                $this->line('Sent to '.$recipient->email);
            } catch (Throwable $exception) {
                Log::error('Owner daily digest mail failed.', [
                    'recipient' => $recipient->email,
                    'exception_class' => $exception::class,
                    'exception_message' => $exception->getMessage(),
                ]);

                report($exception);

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
