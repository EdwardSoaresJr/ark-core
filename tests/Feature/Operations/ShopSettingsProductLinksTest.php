<?php

use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
});

function settingsNavXpath(string $html): DOMXPath
{
    $dom = new DOMDocument;
    @$dom->loadHTML($html);

    return new DOMXPath($dom);
}

test('the settings menu links Website and Book Service Builder only when a product registered them', function (): void {
    $nav = '//nav[@aria-label="Shop settings sections"]';
    $xpath = settingsNavXpath($this->actingAs($this->admin)->get(route('operations.settings.shop.edit'))->assertOk()->getContent());

    expect($xpath->query($nav.'//*[normalize-space()="Website" or normalize-space()="Book Service Builder"]')->length)->toBe(0)
        ->and($xpath->query($nav.'//a[normalize-space()="Online Requests"]')->length)->toBe(1);

    Route::middleware('web')->get('/app/website', fn () => 'website')->name('sites.settings.index');
    Route::middleware('web')->get('/app/booking-builder', fn () => 'builder')->name('booking.builder');
    app('router')->getRoutes()->refreshNameLookups();

    foreach ([route('operations.settings.shop.edit'), route('operations.settings.booking')] as $page) {
        $xpath = settingsNavXpath($this->actingAs($this->admin)->get($page)->assertOk()->getContent());

        expect($xpath->query($nav.'//a[normalize-space()="Website"][@href="'.route('sites.settings.index').'"]')->length)->toBe(1, $page)
            ->and($xpath->query($nav.'//a[normalize-space()="Book Service Builder"][@href="'.route('booking.builder').'"]')->length)->toBe(1, $page)
            ->and($xpath->query('//select[@id="settings-section"]//option[normalize-space()="Website"]')->length)->toBe(1, $page.' phone menu')
            ->and($xpath->query('//select[@id="settings-section"]//option[normalize-space()="Book Service Builder"]')->length)->toBe(1, $page.' phone menu');
    }

    $shop = settingsNavXpath($this->actingAs($this->admin)->get(route('operations.settings.shop.edit'))->getContent());
    expect($shop->query('//select[@id="settings-section"]//option[normalize-space()="Website"]')->item(0)->getAttribute('data-href'))->toBe(route('sites.settings.index'))
        ->and($shop->query('//select[@id="settings-section"]//option[normalize-space()="Online Requests"]')->item(0)->getAttribute('data-href'))->toBe(route('operations.settings.booking'));

    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $this->actingAs($technician)->get(route('operations.settings.shop.edit'))->assertRedirect()->assertSessionHasErrors();
    $this->actingAs($technician)->get(route('operations.settings.booking'))->assertRedirect()->assertSessionHasErrors();
});
