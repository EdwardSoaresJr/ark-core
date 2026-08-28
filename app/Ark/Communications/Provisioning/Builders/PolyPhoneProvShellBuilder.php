<?php

namespace App\Ark\Communications\Provisioning\Builders;

/**
 * Empty Poly shell cfg — matches Asterisk 000000000000-phone.cfg.
 */
final class PolyPhoneProvShellBuilder
{
    public function build(): string
    {
        return '<?xml version="1.0" standalone="yes"?>'."\n";
    }
}
