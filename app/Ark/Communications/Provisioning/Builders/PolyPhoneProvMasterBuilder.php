<?php

namespace App\Ark\Communications\Provisioning\Builders;

/**
 * Poly APPLICATION bootstrap — points the phone at config/{mac} only.
 *
 * Do not reference sip.cfg or sip.ld here. Poly reverts the entire provision
 * when any CONFIG_FILES entry or APP_FILE_PATH asset 404s — and ARK does not
 * host UC firmware blobs on the app origin.
 */
final class PolyPhoneProvMasterBuilder
{
    public function build(string $macLower): string
    {
        return '<?xml version="1.0" standalone="yes"?>'."\n"
            .'<APPLICATION CONFIG_FILES="config/'.$macLower.'" MISC_FILES="" LOG_FILE_DIRECTORY=""/>'."\n";
    }
}
