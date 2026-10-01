<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Settings\CommunicationsChannelSettings;
use App\Ark\Operations\Settings\ShopSettings;

class InboundMessageQueueChannels
{
    /**
     * @return list<OperationalCommunicationChannel>
     */
    public function enabled(): array
    {
        $settings = CommunicationsChannelSettings::fromShopSettings(ShopSettings::current());

        return collect(OperationalCommunicationChannel::inboundQueueCandidates())
            ->filter(function (OperationalCommunicationChannel $channel) use ($settings): bool {
                if ($channel === OperationalCommunicationChannel::Messenger) {
                    return $settings->messengerEnabled;
                }

                return true;
            })
            ->values()
            ->all();
    }
}
