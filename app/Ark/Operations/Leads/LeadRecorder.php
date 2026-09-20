<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Leads\Jobs\SendWebsiteLeadConfirmationJob;
use App\Ark\Operations\PhoneNumber;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;

class LeadRecorder
{
    public function __construct(
        private readonly ConversationRecorder $conversations,
    ) {}

    /**
     * @param  array{
     *     concern: string,
     *     contact_phone: string,
     *     contact_name?: string|null,
     *     contact_email?: string|null,
     *     contact_preference?: LeadContactPreference|null,
     *     vehicle_year?: int|null,
     *     vehicle_make?: string|null,
     *     vehicle_model?: string|null,
     *     vehicle_vin?: string|null,
     *     vehicle_id?: int|null,
     *     customer_id?: int|null,
     *     source?: LeadSource,
     *     metadata?: array<string, mixed>|null,
     * }  $data
     */
    public function recordWebsiteSubmission(
        array $data,
        ?User $actor = null,
        ?LeadIngressContext $ingress = null,
        ?LeadState $forcedState = null,
        array $spamSignals = [],
    ): Lead {
        $source = $data['source'] ?? LeadSource::Website;
        $phone = PhoneNumber::normalize($data['contact_phone']);
        $concern = trim($data['concern']);
        $state = $forcedState ?? LeadState::Received;
        $contactPreference = $data['contact_preference'] ?? LeadContactPreference::Text;
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        if (isset($data['preferred_period']) && is_string($data['preferred_period']) && $data['preferred_period'] !== '') {
            $metadata['preferred_period'] = $data['preferred_period'];
        }
        $vehicleAttributes = [
            'vehicle_id' => Arr::get($data, 'vehicle_id'),
            'customer_id' => Arr::get($data, 'customer_id'),
            'vehicle_year' => Arr::get($data, 'vehicle_year'),
            'vehicle_make' => filled($data['vehicle_make'] ?? null) ? trim((string) $data['vehicle_make']) : null,
            'vehicle_model' => filled($data['vehicle_model'] ?? null) ? trim((string) $data['vehicle_model']) : null,
            'vehicle_vin' => filled($data['vehicle_vin'] ?? null) ? trim((string) $data['vehicle_vin']) : null,
        ];

        if ($state === LeadState::Spam) {
            return Lead::query()->create([
                'source' => $source,
                'state' => LeadState::Spam,
                'concern' => $concern,
                'contact_name' => filled($data['contact_name'] ?? null) ? trim((string) $data['contact_name']) : null,
                'contact_phone' => $phone,
                'contact_email' => filled($data['contact_email'] ?? null) ? trim((string) $data['contact_email']) : null,
                'contact_preference' => $contactPreference,
                ...$vehicleAttributes,
                'metadata' => $metadata !== [] ? $metadata : null,
                'spam_signals' => $spamSignals !== [] ? $spamSignals : null,
                ...($ingress?->observationAttributes() ?? []),
            ]);
        }

        $message = $this->conversations->recordWebsiteLead(
            $actor,
            $concern,
            $phone,
            $data['contact_name'] ?? null,
            $source->value,
        );

        return tap($this->createFromConversation($message, [
            'source' => $source,
            'state' => $state,
            'concern' => $concern,
            'contact_name' => filled($data['contact_name'] ?? null) ? trim((string) $data['contact_name']) : null,
            'contact_phone' => $phone,
            'contact_email' => filled($data['contact_email'] ?? null) ? trim((string) $data['contact_email']) : null,
            'contact_preference' => $contactPreference,
            ...$vehicleAttributes,
            'metadata' => $metadata !== [] ? $metadata : null,
            'spam_signals' => $spamSignals !== [] ? $spamSignals : null,
            ...($ingress?->observationAttributes() ?? []),
        ]), function (Lead $lead): void {
            Bus::dispatchAfterResponse(new SendWebsiteLeadConfirmationJob($lead->id));
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createFromConversation(ConversationMessage $message, array $attributes): Lead
    {
        $message->loadMissing('conversation');

        return Lead::query()->create([
            ...$attributes,
            'conversation_id' => $message->conversation_id,
        ]);
    }
}
