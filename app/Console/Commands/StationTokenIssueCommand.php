<?php

namespace App\Console\Commands;

use App\Ark\Station\StationDeviceToken;
use Illuminate\Console\Command;

final class StationTokenIssueCommand extends Command
{
    protected $signature = 'station:token-issue
        {name=front-counter-glass : Label for this physical device}
        {--shop= : Override shop identity (defaults to config shop.identity)}';

    protected $description = 'Issue a read-only Front Counter glass token (plaintext printed once)';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $shop = $this->option('shop');
        $shopIdentity = is_string($shop) && $shop !== '' ? $shop : null;

        $issued = StationDeviceToken::issue($name, $shopIdentity);
        /** @var StationDeviceToken $token */
        $token = $issued['token'];
        $plain = $issued['plain_text'];

        $this->info('Station device token created.');
        $this->line('id='.$token->id);
        $this->line('name='.$token->name);
        $this->line('prefix='.$token->token_prefix);
        $this->line('shop_identity='.$token->shop_identity);
        $this->newLine();
        $this->warn('Store this on the glass now — it will not be shown again:');
        $this->line($plain);

        return self::SUCCESS;
    }
}
