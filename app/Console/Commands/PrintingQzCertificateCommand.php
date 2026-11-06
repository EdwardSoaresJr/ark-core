<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ark\Operations\Printing\QzSigningPairGenerator;
use App\Ark\Operations\Printing\QzTraySigning;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Console\Command;
use RuntimeException;

class PrintingQzCertificateCommand extends Command
{
    protected $signature = 'ark:printing:qz-certificate
        {--generate : Create this shop\'s QZ Tray signing pair (only when it has none)}
        {--organization= : Organization shown in QZ Tray (default: the shop name)}';

    protected $description = 'Create the shop\'s QZ Tray signing certificate, key and workstation trust root when it has none';

    public function handle(QzSigningPairGenerator $generator): int
    {
        if (! $this->option('generate')) {
            $this->line('signing_source='.QzTraySigning::source());
            $this->line('Run with --generate to create a pair for a shop that has none. An existing pair is never replaced.');

            return self::SUCCESS;
        }

        $organization = (string) ($this->option('organization') ?: (ShopSettings::current()->shop_name ?? ''));

        try {
            $created = $generator->generate($organization);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('certificate='.$created['certificate']['common_name'].' / '.$created['certificate']['organization']);
        $this->line('certificate_not_after='.$created['certificate']['not_after']);
        $this->line('trust_root_sha256='.$created['trust_root']['sha256']);
        $this->info('Created in '.QzTraySigning::SHOP_DIRECTORY.'. The root key was not kept. Each workstation runs Set Up Printing once.');

        return self::SUCCESS;
    }
}
