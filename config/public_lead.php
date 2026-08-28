<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Website lead phone verification
    |--------------------------------------------------------------------------
    |
    | When true, public website leads require a verified phone session from
    | Phone Verification Authority (shop Twilio + inbound SMS number).
    | See config/phone_verification.php — Twilio Verify is not used.
    |
    */

    'phone_verification_required' => env('PUBLIC_LEAD_PHONE_VERIFY', true),

    /** @deprecated Unused — Phone Verification Authority owns OTP delivery. */
    'twilio_verify_service_sid' => env('TWILIO_VERIFY_SERVICE_SID'),

    /** @deprecated Use phone_verification.session_ttl_minutes. */
    'verification_ttl_minutes' => (int) env('PUBLIC_LEAD_PHONE_VERIFY_TTL', 30),

    /*
    |--------------------------------------------------------------------------
    | Website lead submission confirmation
    |--------------------------------------------------------------------------
    |
    | When enabled, customers receive an SMS receipt after submitting the public
    | lead form (when Twilio SMS is configured). Email is sent when an address
    | was provided on the form.
    |
    */

    'send_confirmation' => env('PUBLIC_LEAD_SEND_CONFIRMATION', true),

];
