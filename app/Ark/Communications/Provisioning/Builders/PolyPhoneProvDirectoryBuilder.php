<?php

namespace App\Ark\Communications\Provisioning\Builders;

/**
 * Poly directory.xml shell — matches Asterisk 000000000000-directory.xml without user rows.
 */
final class PolyPhoneProvDirectoryBuilder
{
    public function build(): string
    {
        return '<?xml version="1.0" standalone="yes"?>'."\n"
            ."<directory>\n"
            ."\t<item_list>\n"
            ."\t</item_list>\n"
            ."</directory>\n";
    }
}
