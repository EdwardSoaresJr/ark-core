<?php

use App\Ark\Operations\Encounters\EncounterSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('customers')
            ->where('customer_type', 'RepairPal')
            ->whereNull('referral_source')
            ->update([
                'customer_type' => 'Warranty',
                'referral_source' => EncounterSource::RepairPal->value,
            ]);

        DB::table('customers')
            ->where('customer_type', 'RepairPal')
            ->update(['customer_type' => 'Warranty']);

        $settings = DB::table('shop_settings')->first();

        if ($settings === null) {
            return;
        }

        $customerTypes = json_decode((string) ($settings->customer_types ?? '[]'), true);

        if (! is_array($customerTypes)) {
            $customerTypes = [];
        }

        $disclaimers = json_decode((string) ($settings->customer_type_disclaimers ?? '[]'), true);

        if (! is_array($disclaimers)) {
            $disclaimers = [];
        }

        $repairPalRow = null;
        $warrantyIndex = null;

        foreach ($customerTypes as $index => $row) {
            $name = (string) ($row['name'] ?? '');

            if (strcasecmp($name, 'RepairPal') === 0) {
                $repairPalRow = $row;
            }

            if (strcasecmp($name, 'Warranty') === 0) {
                $warrantyIndex = $index;
            }
        }

        if ($repairPalRow !== null) {
            $repairPalRow['name'] = 'Warranty';

            if ($warrantyIndex !== null) {
                $existing = $customerTypes[$warrantyIndex];
                $customerTypes[$warrantyIndex] = array_merge($existing, array_filter($repairPalRow, fn ($value) => $value !== null && $value !== ''));
                $customerTypes = array_values(array_filter(
                    $customerTypes,
                    fn (array $row): bool => strcasecmp((string) ($row['name'] ?? ''), 'RepairPal') !== 0,
                ));
            } else {
                $customerTypes = array_map(
                    fn (array $row): array => strcasecmp((string) ($row['name'] ?? ''), 'RepairPal') === 0
                        ? $repairPalRow
                        : $row,
                    $customerTypes,
                );
            }
        }

        if (! isset($disclaimers['warranty']) && isset($disclaimers['repairpal'])) {
            $disclaimers['warranty'] = $disclaimers['repairpal'];
        }

        DB::table('shop_settings')->where('id', $settings->id)->update([
            'customer_types' => json_encode(array_values($customerTypes)),
            'customer_type_disclaimers' => json_encode($disclaimers),
        ]);
    }

    public function down(): void
    {
        // Data migration is intentionally one-way.
    }
};
