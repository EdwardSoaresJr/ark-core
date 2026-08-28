<?php

/**
 * ARK-hosted Dragon agent (OpenAI primary).
 *
 * gpt-4o is the inherited default until floor certification names a better
 * DRAGON_OPENAI_MODEL. Do not silently swap production models.
 *
 * arkai/Qwen remains recoverable until arkai-off certification.
 */
return [

    'provider' => env('DRAGON_PROVIDER', 'openai'),

    'hosted_chat_enabled' => (bool) env('DRAGON_HOSTED_CHAT', true),

    'openai_api_key' => env('OPENAI_API_KEY'),

    'openai_model' => env('DRAGON_OPENAI_MODEL', 'gpt-4o'),

    'openai_base_url' => rtrim((string) env('DRAGON_OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),

    'max_tool_rounds' => (int) env('DRAGON_MAX_TOOL_ROUNDS', 8),

    'timeout_seconds' => (int) env('DRAGON_OPENAI_TIMEOUT', 60),

];
