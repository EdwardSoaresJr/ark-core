<?php

namespace App\Console\Commands;

use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\ShopExcellence\OwnerOperationalPulse;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Mail\OwnerDailyDigestMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient->email)->send(new OwnerDailyDigestMail($digest));
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
