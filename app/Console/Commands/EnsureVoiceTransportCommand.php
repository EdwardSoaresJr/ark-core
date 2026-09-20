<?php

namespace App\Console\Commands;

use App\Ark\Platform\VoiceTransportConfiguration;
use Illuminate\Console\Command;

class EnsureVoiceTransportCommand extends Command
{
    protected $signature = 'ark:voice:ensure-transport-config';

    protected $description = 'Ensure VOICE_SIP_REGISTRAR exists for endpoint provisioning (env or shared secrets)';

    public function handle(): int
    {
        $before = VoiceTransportConfiguration::resolveRegistrar();
        $registrar = VoiceTransportConfiguration::ensure();

        if ($registrar === null) {
            $this->warn('Voice SIP registrar is not configured ('.VoiceTransportConfiguration::sourceLabel().').');

            return self::FAILURE;
        }

        if ($before !== null) {
            $this->info('Voice SIP registrar already configured ('.VoiceTransportConfiguration::sourceLabel().').');
            $this->line('  VOICE_SIP_REGISTRAR='.$registrar);

            return self::SUCCESS;
        }

        $this->warn('Voice SIP registrar resolved from deployment transport env.');
        $this->line('  VOICE_SIP_REGISTRAR='.$registrar);
        $this->line('  Runtime storage: '.VoiceTransportConfiguration::storagePath());

        if ($shared = VoiceTransportConfiguration::sharedSecretsPath()) {
            $this->line('  Shared secrets: '.$shared);
        }

        $this->newLine();
        $this->comment('Add to Coolify arksms env if not already present:');
        $this->line('  VOICE_SIP_REGISTRAR='.$registrar);

        return self::SUCCESS;
    }
}
