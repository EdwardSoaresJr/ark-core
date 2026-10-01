<?php

namespace App\Console\Commands;

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\ProcessIncomingCallAction;
use App\Ark\Operations\Telephony\TelephonyProviderManager;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class SimulateIncomingCallCommand extends Command
{
    protected $signature = 'ark:simulate-incoming-call {phone : Caller phone number}';

    protected $description = 'Simulate an inbound call screen pop for local/testing verification.';

    public function handle(
        TelephonyProviderManager $providers,
        ProcessIncomingCallAction $process,
    ): int {
        if (! app()->environment('local', 'testing')) {
            $this->components->error('This command is only available in local or testing environments.');

            return self::FAILURE;
        }

        $phone = (string) $this->argument('phone');
        $provider = $providers->current();

        $simulated = Request::create('/', 'POST', [
            'CallSid' => 'sim-cli-'.uniqid(),
            'From' => $phone,
            'To' => ShopSettings::current()->telephony_inbound_number ?? '+15550000000',
            'CallStatus' => 'ringing',
            'Direction' => 'inbound',
        ]);

        $payload = $provider->parseIncomingVoiceRequest($simulated);
        $result = $process->execute($payload);

        $this->components->info('Incoming call simulated.');
        $this->line('Call session #'.$result['session']->id);
        $this->line('Matched customer: '.($result['context']?->customer?->name ?? 'none'));
        $this->line('Open ROs: '.($result['context']?->openRepairOrders->count() ?? 0));
        $this->line('Broadcast: '.($result['created'] ? 'sent (if Reverb enabled)' : 'skipped (duplicate sid)'));

        return self::SUCCESS;
    }
}
