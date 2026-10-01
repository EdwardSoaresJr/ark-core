<?php

namespace App\Ark\Tech;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use RuntimeException;

final class TechFindingRewriteService
{
    public function propose(string $source): string
    {
        $source = trim($source);
        if ($source === '') {
            throw new RuntimeException('Nothing to rewrite.');
        }

        if (! ShopIntegrationCredentials::forCurrentShop()->openaiConfigured()) {
            throw new RuntimeException('Dragon rewrite is unavailable. Keep the original note.');
        }

        throw new RuntimeException('Dragon rewrite is unavailable. Keep the original note.');
    }
}
