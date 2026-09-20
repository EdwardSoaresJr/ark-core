<?php

use App\Ark\Import\ShopCsv\ShopCsvImporter;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

function shopCsvPath(string $contents): string
{
    $path = Storage::disk('local')->path('shop-csv-imports/test.csv');
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    file_put_contents($path, $contents);

    return $path;
}

it('previews and commits customers with vehicles from csv', function (): void {
    $csv = <<<'CSV'
first_name,last_name,phone,email,year,make,model,plate,plate_state
Ada,Wright,(719) 555-0111,ada@example.test,2019,Toyota,Camry,XYZ999,CO
CSV;

    $importer = app(ShopCsvImporter::class);
    $preview = $importer->preview(shopCsvPath($csv));

    expect($preview['ok'])->toBeTrue()
        ->and($preview['create'])->toBe(1)
        ->and($preview['vehicles'])->toBe(1)
        ->and(Customer::query()->count())->toBe(0);

    $commit = $importer->commit(shopCsvPath($csv));

    expect($commit['ok'])->toBeTrue()
        ->and($commit['create'])->toBe(1)
        ->and(Customer::query()->count())->toBe(1)
        ->and(Vehicle::query()->count())->toBe(1);

    $customer = Customer::query()->first();
    expect($customer->first_name)->toBe('Ada')
        ->and($customer->email)->toBe('ada@example.test')
        ->and($customer->vehicles()->first()->make)->toBe('Toyota');
});

it('updates existing customer matched by phone', function (): void {
    Customer::query()->create([
        'first_name' => 'Old',
        'last_name' => 'Name',
        'phone' => '7195550111',
        'email' => null,
        'customer_type' => 'Retail',
    ]);

    $csv = <<<'CSV'
first_name,last_name,phone,email
Ada,Wright,719-555-0111,ada@example.test
CSV;

    $report = app(ShopCsvImporter::class)->commit(shopCsvPath($csv));

    expect($report['update'])->toBe(1)
        ->and($report['create'])->toBe(0)
        ->and(Customer::query()->count())->toBe(1)
        ->and(Customer::query()->first()->first_name)->toBe('Ada')
        ->and(Customer::query()->first()->email)->toBe('ada@example.test');
});

it('lets an admin preview and import from settings', function (): void {
    $this->seed(\Database\Seeders\ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $tmp = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($tmp, "first_name,last_name,phone\nBen,Carter,7195550222\n");

    $this->actingAs($admin)
        ->post(route('operations.settings.shop.import.preview'), [
            'csv' => new UploadedFile($tmp, 'customers.csv', 'text/csv', null, true),
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'operations']))
        ->assertSessionHas('shop_csv_token')
        ->assertSessionHas('shop_csv_report');

    $token = session('shop_csv_token');

    $this->actingAs($admin)
        ->post(route('operations.settings.shop.import.commit'), [
            'token' => $token,
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'operations']));

    expect(Customer::query()->where('phone', '7195550222')->exists())->toBeTrue();
});

it('downloads the csv template', function (): void {
    $this->seed(\Database\Seeders\ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->get(route('operations.settings.shop.import.template'))
        ->assertOk()
        ->assertHeader('content-disposition');
});

it('forbids advisors from shop csv import', function (): void {
    $this->seed(\Database\Seeders\ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $tmp = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($tmp, "first_name,last_name,phone\nX,Y,7195550999\n");

    $this->actingAs($advisor)
        ->post(route('operations.settings.shop.import.preview'), [
            'csv' => new UploadedFile($tmp, 'customers.csv', 'text/csv', null, true),
        ])
        ->assertRedirect(route('operations.index'))
        ->assertSessionHasErrors('settings');

    expect(Customer::query()->count())->toBe(0);
});
