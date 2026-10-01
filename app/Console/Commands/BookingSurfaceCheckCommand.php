<?php

namespace App\Console\Commands;

use App\Ark\Runtime\Booking\BookingSurface;
use App\Ark\Runtime\Booking\BookingSurfaceGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use RuntimeException;

class BookingSurfaceCheckCommand extends Command
{
    protected $signature = 'ark:booking-surface:check {--probe : HTTP GET external /book when configured}';

    protected $description = 'Verify Public Core does not claim marketing /book without an external booking surface';

    public function handle(): int
    {
        $this->info('Booking surface check');
        $this->line('base_url: '.(BookingSurface::baseUrl() !== '' ? BookingSurface::baseUrl() : '[empty]'));
        $this->line('enforce: '.(BookingSurface::enforce() ? 'yes' : 'no'));
        $this->line('protected_hosts: '.implode(', ', BookingSurface::protectedHosts()));
        $this->line('claimed_hosts: '.implode(', ', BookingSurface::claimedHosts()) ?: '[none]');
        $this->line('claimed_protected: '.implode(', ', BookingSurface::claimedProtectedHosts()) ?: '[none]');
        $this->line('route public.book: '.(Route::has('public.book') ? 'YES (forbidden)' : 'absent (ok)'));

        try {
            BookingSurfaceGuard::assertCutoverSafe();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (Route::has('public.book')) {
            $this->error('public.book must not be registered on Public Core.');

            return self::FAILURE;
        }

        if ($this->option('probe') && BookingSurface::isConfigured()) {
            $url = BookingSurface::bookUrl();
            $code = Http::timeout(8)->withOptions(['allow_redirects' => false])->get($url)->status();
            $this->line("probe {$url} => HTTP {$code}");

            if ($code < 200 || $code >= 400) {
                $this->error('External booking surface /book is not healthy.');

                return self::FAILURE;
            }
        }

        $this->info('Booking surface check passed.');

        return self::SUCCESS;
    }
}
