<?php

namespace App\Console\Commands;

use App\Ark\Website\WebsitePublicationCutover;
use Illuminate\Console\Command;
use Throwable;

class WebsitePublicationCutoverCommand extends Command
{
    protected $signature = 'website:cutover-publication
        {--from-host= : Current website_sites.public_host. Defaults to the custom domain when that site exists}
        {--to-host= : Native ARK host to store on the site}
        {--apply : Write the merge and host change. Omit for a dry run}
        {--revert-site= : Site id to roll back}
        {--revert-version= : Publication version to make current again}
        {--revert-host= : public_host to restore}';

    protected $description = 'Dry-run or apply the website publication merge and native host change';

    public function handle(WebsitePublicationCutover $cutover): int
    {
        if ($this->option('revert-version') !== null || $this->option('revert-site') !== null) {
            return $this->revert($cutover);
        }

        $toHost = (string) ($this->option('to-host') ?: (config('website.custom_domains.0.site_host') ?? ''));

        $plan = $cutover->plan($this->option('from-host') ?: null, $toHost);
        $this->report($plan, (bool) $this->option('apply'));

        if (($plan['ok'] ?? false) !== true) {
            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->line('dry_run: yes');

            return self::SUCCESS;
        }

        try {
            $cutover->apply($plan);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('applied: yes');
        $this->line('rollback_site_id: '.$plan['site_id']);
        $this->line('rollback_version: '.$plan['current_version']);
        $this->line('rollback_host: '.$plan['old_public_host']);

        return self::SUCCESS;
    }

    private function revert(WebsitePublicationCutover $cutover): int
    {
        $siteId = (int) $this->option('revert-site');
        $version = (int) $this->option('revert-version');
        $host = (string) $this->option('revert-host');

        $this->line('revert_site_id: '.$siteId);
        $this->line('revert_version: '.$version);
        $this->line('revert_host: '.$host);

        if ($siteId < 1 || $version < 1 || trim($host) === '') {
            $this->error('Revert needs --revert-site, --revert-version, and --revert-host.');

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->line('dry_run: yes');

            return self::SUCCESS;
        }

        try {
            $cutover->revert($siteId, $version, $host);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('reverted: yes');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function report(array $plan, bool $apply): void
    {
        foreach ([
            'site_id',
            'old_public_host',
            'new_public_host',
            'current_version',
            'current_hash',
            'proposed_version',
            'proposed_hash',
            'preferred_canonical',
            'common_problems_before',
            'common_problems_after',
            'shop_photos_before',
            'shop_photos_after',
            'composition_photos_before',
            'composition_photos_after',
            'customer_reviews_before',
            'customer_reviews_after',
            'reviews_before',
            'reviews_after',
        ] as $key) {
            if (array_key_exists($key, $plan)) {
                $this->line($key.': '.$plan[$key]);
            }
        }

        $preserved = $plan['preserved_keys'] ?? [];
        $changed = $plan['catalog_keys_changed'] ?? [];
        $this->line('preserved_keys: '.(is_array($preserved) && $preserved !== [] ? implode(',', $preserved) : 'none'));
        $this->line('catalog_keys_changed: '.(is_array($changed) && $changed !== [] ? implode(',', $changed) : 'none'));

        $warnings = $plan['warnings'] ?? [];
        if (! is_array($warnings) || $warnings === []) {
            $this->line('warnings: none');
        } else {
            foreach ($warnings as $warning) {
                $this->line('warning: '.$warning);
            }
        }

        if ($apply) {
            $this->line('apply_requested: yes');
        }
    }
}
