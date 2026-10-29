---
title: Listen for a domain event
description: Register a listener for ConversationMessageReceived in ARK Core.
---

# Listen for a domain event

<!-- source: app/Ark/Operations/Messaging/Events/ConversationMessageReceived.php -->

`ConversationMessageReceived` is dispatched when Core records an inbound conversation message. The event carries a public array `$payload` and also broadcasts on a private channel. A listener runs in-process. It is not an HTTP webhook.

## Listener

```php
<?php

namespace Acme\ShopNotes;

use App\Ark\Operations\Messaging\Events\ConversationMessageReceived;

final class NoteInboundMessage
{
    public function handle(ConversationMessageReceived $event): void
    {
        // $event->payload is the array Core broadcast.
        // Read keys defensively. They are not a frozen schema.
        logger()->info('inbound-message', [
            'keys' => array_keys($event->payload),
        ]);
    }
}
```

Log keys, not message bodies, until you know the payload. Customer message text is shop data.

## Registration

In a service provider `boot()` method:

```php
use Acme\ShopNotes\NoteInboundMessage;
use App\Ark\Operations\Messaging\Events\ConversationMessageReceived;
use Illuminate\Support\Facades\Event;

Event::listen(ConversationMessageReceived::class, NoteInboundMessage::class);
```

Core registers its own listener for mobile push in `AppServiceProvider`. Your listener is additional. It does not replace the push listener.

## Compatibility

The class name and the fact of a payload array are what this tutorial depends on. Payload keys can change without a package major version. Re-read the constructor and `broadcastWith()` after you pull Core.

This does not run when a message is never dispatched. Outbound staff notes and other writes have their own classes. Search before you listen for a class that is not imported anywhere.
