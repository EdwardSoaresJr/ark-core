<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trustworthy Report Data Starts At
    |--------------------------------------------------------------------------
    |
    | Operational reports ignore repair orders opened before this date.
    | Temporary guard while legacy/imported history is cleaned up.
    |
    */

    'trustworthy_data_starts_at' => env('OPERATIONAL_REPORT_TRUSTWORTHY_START', '2026-06-01'),

];
