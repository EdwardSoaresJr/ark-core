<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Voice lab (handheld prototype only)
    |--------------------------------------------------------------------------
    |
    | Captures WAV from the ESP walkie and returns a transcript. This is not
    | VoiceDevice, not Dragon, not TTS, not OTA. Production stays off unless
    | VOICE_LAB_ENABLED is explicitly true and a secret is set.
    |
    */

    'lab_enabled' => (bool) env('VOICE_LAB_ENABLED', false),

    'lab_secret' => env('VOICE_LAB_SECRET'),

    'lab_prompt' => 'Automotive shop dictation. Preserve laterality (left/right, front/rear), numbers, units (mm, PSI, volts), component names, and DTCs exactly. Do not swap sides or values.',

];
