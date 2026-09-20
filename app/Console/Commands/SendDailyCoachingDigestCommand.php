<?php

namespace App\Console\Commands;

use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use App\Ark\Operations\Communications\DailyCoachingDigestProjection;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Mail\DailyCoachingDigestMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

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

        foreach ($recipients as $recipient) {
            Mail::to($recipient->email)->send(new DailyCoachingDigestMail($digest));
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
