<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * @deprecated Voice policy is authored on ARK Platform only.
 */
final class ReportVoiceInboundPolicyCommand extends Command
{
    protected $signature = 'ark:platform:report-voice-inbound-policy';

    protected $description = 'Deprecated - Voice configuration is owned by ARK Platform';

    public function handle(): int
    {
        $this->warn('Voice inbound policy is authored on ARK Platform (Cloud). Core no longer syncs telephony configuration.');

        return self::FAILURE;
    }
}
