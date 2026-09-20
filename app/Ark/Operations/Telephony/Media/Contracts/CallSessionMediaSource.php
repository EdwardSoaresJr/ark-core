<?php

namespace App\Ark\Operations\Telephony\Media\Contracts;

use App\Ark\Operations\Telephony\Media\CallSessionMediaPayload;
use App\Ark\Operations\Telephony\Media\CallSessionMediaUri;

interface CallSessionMediaSource
{
    public function scheme(): string;

    public function supports(CallSessionMediaUri $uri): bool;

    public function canStream(CallSessionMediaUri $uri): bool;

    public function fetch(CallSessionMediaUri $uri): ?CallSessionMediaPayload;

    public function streamPath(CallSessionMediaUri $uri): ?string;
}
