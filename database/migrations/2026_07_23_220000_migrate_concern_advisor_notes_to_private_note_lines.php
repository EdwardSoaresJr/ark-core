<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Advisor Note on the concern is retired. Staff-only context lives on Note /
 * Supporting Note lines with is_private. Copy existing concern.notes into a
 * private note line, then clear the scope field.
 */
return new class extends Migration
{
    public function up(): void
    {
        $concerns = DB::table('repair_order_concerns')
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->orderBy('id')
            ->get(['id', 'repair_order_id', 'notes']);

        $now = now();

        foreach ($concerns as $concern) {
            $notes = trim((string) $concern->notes);

            if ($notes === '') {
                DB::table('repair_order_concerns')
                    ->where('id', $concern->id)
                    ->update(['notes' => null]);

                continue;
            }

            $alreadyExists = DB::table('repair_order_lines')
                ->where('repair_order_concern_id', $concern->id)
                ->where('type', 'note')
                ->where('description', $notes)
                ->exists();

            if (! $alreadyExists) {
                DB::table('repair_order_lines')->insert([
                    'repair_order_id' => $concern->repair_order_id,
                    'repair_order_concern_id' => $concern->id,
                    'type' => 'note',
                    'description' => $notes,
                    'quantity' => 1,
                    'unit_price_cents' => 0,
                    'part_cost_cents' => 0,
                    'subtotal_cents' => 0,
                    'tax_cents' => 0,
                    'shop_fee_cents' => 0,
                    'standing_discount_cents' => 0,
                    'total_cents' => 0,
                    'is_private' => true,
                    'is_overridden' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('repair_order_concerns')
                ->where('id', $concern->id)
                ->update(['notes' => null]);
        }
    }

    public function down(): void
    {
        // One-way: private note lines stay; concern.notes stays null.
    }
};
