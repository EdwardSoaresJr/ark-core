<?php

namespace App\Console\Commands;

use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use App\Ark\Operations\Communications\DailyCoachingDigestProjection;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\Mail\EmailIntent;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\Mail\ArkMailClient;
use App\Ark\Platform\Mail\ManagedMailGate;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SendDailyCoachingDigestCommand extends Command
{
    protected $signature = 'communications:daily-coaching-digest {--date= : Shop date (Y-m-d) to summarize} {--email= : Send to one address instead of configured recipients}';

    protected $description = 'Email the daily coaching digest (strongest call + highest coaching opportunity)';

    public function handle(DailyCoachingDigestProjection $projection): int
    {
        if (! ShopExcellenceTargets::coachingDigestEnabled() && ! filled($this->option('email'))) {
            $this->info('Daily coaching digest disabled in shop excellence targets.');

            return self::SUCCESS;
        }

        $digest = filled($this->option('date'))
            ? $projection->forShopDate((string) $this->option('date'))
            : $projection->forShopDate();

        if ($digest['review_count'] === 0) {
            $this->info('No communication reviews for digest date.');

            return self::SUCCESS;
        }

        $recipients = filled($this->option('email'))
            ? collect([(object) ['email' => (string) $this->option('email')]])
            : $this->recipients();

        if ($recipients->isEmpty()) {
            $this->warn('No recipients for daily coaching digest.');

            return self::SUCCESS;
        }

        if (! ManagedMailGate::platformSend()) {
            $this->error("Email isn't configured yet.");

            return self::FAILURE;
        }

        $mail = app(ArkMailClient::class);
        $shopName = ShopSettings::current()->shop_name ?: config('app.name', 'ARK');

        foreach ($recipients as $recipient) {
            $variables = [
                'shop_name' => $shopName,
                'range_label' => (string) ($digest['range_label'] ?? ''),
                'review_count' => (string) ($digest['review_count'] ?? 0),
            ];
            if (is_array($digest['strongest_call'] ?? null)) {
                $variables['strongest_call'] = $digest['strongest_call'];
            }
            if (is_array($digest['coaching_opportunity'] ?? null)) {
                $variables['coaching_opportunity'] = $digest['coaching_opportunity'];
            }
            if (filled($digest['calls_url'] ?? null)) {
                $variables['calls_url'] = (string) $digest['calls_url'];
            }

            $result = $mail->sendTransactional(EmailIntent::payload(
                operation: 'coaching.digest',
                to: (string) $recipient->email,
                variables: $variables,
                idempotencyKey: 'coaching-digest-'.Str::uuid(),
            ));
            if (($result['ok'] ?? false) !== true) {
                $this->error(is_string($result['message'] ?? null) ? $result['message'] : 'Email could not be sent.');

                return self::FAILURE;
            }
            $this->line('Sent to '.$recipient->email);
        }

        return self::SUCCESS;
    }

  /**
     * @return \Illuminate\Support\Collection<int, object{email: string}>
     */
    private function recipients()
    {
        $explicit = ShopExcellenceTargets::coachingDigestRecipientEmails();

        if ($explicit !== []) {
            return collect($explicit)
                ->map(fn (string $email): object => (object) ['email' => $email]);
        }

        $emails = collect(ShopExcellenceTargets::coachingDigestExtraEmails());

        User::query()
            ->active()
            ->whereNotNull('email')
            ->whereHas('roles', fn ($query) => $query->where('name', ArkRole::Admin->value))
            ->pluck('email')
            ->each(fn (string $email) => $emails->push($email));

        return $emails
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->map(fn (string $email): object => (object) ['email' => $email]);
    }
}
