<?php

// Stock Core has no hosted model provider. Tests may set DRAGON_PROVIDER=fake.

return [

    'provider' => env('DRAGON_PROVIDER', 'none'),

    'hosted_chat_enabled' => (bool) env('DRAGON_HOSTED_CHAT', false),

    'max_tool_rounds' => (int) env('DRAGON_MAX_TOOL_ROUNDS', 8),

    'timeout_seconds' => (int) env('DRAGON_TIMEOUT', 60),

];
