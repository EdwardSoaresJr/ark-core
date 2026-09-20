<?php

return [
    /*
    | Sprint 1 default — FakeSessionProvider drives realtime without transport.
    | Sprint 2 adds twilio and asterisk adapters behind the same SessionEvent pipeline.
    */
    'session_provider' => env('COMMUNICATIONS_SESSION_PROVIDER', 'fake'),
];
