<?php

/**
 * Deployment-owned SIP transport — not part of the shop product model.
 *
 * Provisioning reads these values; operators never configure them in UI.
 * Desk phones register to Twilio Elastic SIP — not a shop PBX.
 *
 * @see docs/platform/shop-identity-v1.md
 */
return [

    'sip_registrar' => env('VOICE_SIP_REGISTRAR'),

    'sip_port' => (int) env('VOICE_SIP_PORT', 5060),

    'sip_outbound_proxy' => env('VOICE_SIP_OUTBOUND_PROXY'),

    'sip_public_ip' => env('VOICE_SIP_PUBLIC_IP'),

];
