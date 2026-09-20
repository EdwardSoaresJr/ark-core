<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Customers\CustomerHubCommsPartition;
use Illuminate\Support\Collection;

test('customer hub comms partition splits sms email and logged channels', function () {
    $partition = app(CustomerHubCommsPartition::class);

    $messages = collect([
        makeHubCommsMessage(OperationalCommunicationChannel::Sms),
        makeHubCommsMessage(OperationalCommunicationChannel::Email),
        makeHubCommsMessage(OperationalCommunicationChannel::Phone),
    ]);

    $result = $partition->partition($messages);

    expect($result['text'])->toHaveCount(1)
        ->and($result['email'])->toHaveCount(1)
        ->and($result['logged'])->toHaveCount(1);
});

function makeHubCommsMessage(OperationalCommunicationChannel $channel): ConversationMessage
{
    $message = new ConversationMessage;
    $message->channel = $channel;

    return $message;
}
