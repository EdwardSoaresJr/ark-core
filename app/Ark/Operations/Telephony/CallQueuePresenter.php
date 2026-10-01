<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Conversations\CustomerCallContext;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Models\User;

class CallQueuePresenter
{
    public function __construct(
        private readonly IncomingCallContextPresenter $incomingCallContextPresenter,
        private readonly ShopIntegrationCredentials $credentials,
        private readonly CallRecordingPlayback $recordingPlayback,
        private readonly TelephonyCallbackPresenter $callbackPresenter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(CallSession $session, ?CustomerCallContext $context, ?User $viewer = null): array
    {
        $base = $this->incomingCallContextPresenter->present($session, $context);
        $openRepairOrders = $base['open_repair_orders'] ?? [];
        $ownedByViewer = $viewer !== null && $session->owned_by_user_id === $viewer->id;

        $customerUrl = $base['customer_url'] ?? null;
        $canTextCustomer = $this->canTextCustomer($base);

        $audio = $this->recordingPlayback->projectFor($session);
        $headline = $base['matched']
            ? (string) $base['customer_name']
            : 'Unknown Caller';

        return array_merge($base, $audio, $this->callbackPresenter->forCallContext($base, $session), [
            'is_actively_live' => $session->isActivelyLive(),
            'waiting_label' => $session->started_at?->diffForHumans(short: true) ?? '',
            'occurred_at' => $session->started_at?->toIso8601String(),
            'occurred_at_label' => $session->started_at
                ?->timezone(config('app.display_timezone'))
                ->format('M j, g:i A') ?? '',
            'headline' => $headline,
            'context_summary' => $this->contextSummary($base, $openRepairOrders),
            'primary_ro_url' => count($openRepairOrders) === 1
                ? ($openRepairOrders[0]['url'] ?? null)
                : null,
            'open_ros_url' => count($openRepairOrders) > 1 && filled($customerUrl)
                ? $customerUrl.'#open-repair-orders'
                : null,
            'show_text_customer_action' => $canTextCustomer,
            'text_customer_url' => $canTextCustomer && filled($customerUrl)
                ? $customerUrl.'?compose=text#customer-communication'
                : null,
            'is_owned_by_me' => $ownedByViewer,
            'show_claim_action' => ! $ownedByViewer,
            'show_handled_action' => true,
            'dropdown_label' => $this->dropdownLabel($session, $base, $headline, $audio),
        ]);
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $audio
     */
    private function dropdownLabel(CallSession $session, array $base, string $headline, array $audio): string
    {
        $parts = ['Call', (string) ($base['status_label'] ?? 'Call')];

        if ($audio['has_voicemail'] ?? false) {
            $parts[] = 'Voicemail';
        } elseif ($audio['has_recording'] ?? false) {
            $parts[] = 'Recording';
        }

        $parts[] = $headline;

        return implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $base
     */
    private function canTextCustomer(array $base): bool
    {
        if (! ($base['matched'] ?? false)) {
            return false;
        }

        if (! filled($base['display_phone'] ?? null)) {
            return false;
        }

        return $this->credentials->twilioConfigured();
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<int, array<string, mixed>>  $openRepairOrders
     */
    private function contextSummary(array $base, array $openRepairOrders): string
    {
        $orientation = is_array($base['orientation'] ?? null) ? $base['orientation'] : null;

        if ($orientation !== null && filled($orientation['situation'] ?? null)) {
            return (string) $orientation['situation'];
        }

        $count = count($openRepairOrders);

        if ($count === 1) {
            return 'RO '.$openRepairOrders[0]['status_label'];
        }

        if ($count > 1) {
            return $count.' Open ROs';
        }

        if ($base['matched'] ?? false) {
            return (string) ($base['customer_type'] ?? 'Customer');
        }

        return (string) ($base['display_phone'] ?? '');
    }
}
