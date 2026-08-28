<?php

namespace App\Ark\Operations\Messaging;

use Illuminate\Http\Response;

final class TwilioMessagingTwimlResponse
{
    public static function empty(): Response
    {
        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200, [
            'Content-Type' => 'text/xml',
        ]);
    }

    public static function message(string $body): Response
    {
        $escaped = htmlspecialchars(trim($body), ENT_XML1);

        return response(
            '<?xml version="1.0" encoding="UTF-8"?><Response><Message>'.$escaped.'</Message></Response>',
            200,
            ['Content-Type' => 'text/xml'],
        );
    }
}
