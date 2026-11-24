<?php

use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\Support\JobBoardAcceptanceScenario;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

app(ArkAuthorizationSeeder::class)->run();
app(RepairOrderStatusCatalogSeeder::class)->run();

$email = 'jobboard-review-251@example.test';
$user = User::query()->where('email', $email)->first();
if (! $user) {
    $user = User::factory()->create([
        'name' => 'Job Board Reviewer',
        'email' => $email,
        'password' => Hash::make('Review251!'),
    ]);
} else {
    $user->forceFill(['password' => Hash::make('Review251!')])->save();
}
$user->syncRoles([ArkRole::Advisor->value, ArkRole::Admin->value]);

$rows = JobBoardAcceptanceScenario::seed($user);
echo 'seeded='.$rows->count().PHP_EOL;
foreach ($rows as $ro) {
    echo $ro->repair_order_id.' '.$ro->status->value.' '.(string) $ro->customer?->name
        .' tech='.(string) $ro->assignedTechnician?->name.PHP_EOL;
}
