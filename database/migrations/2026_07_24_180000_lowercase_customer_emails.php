<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('customers')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $normalized = strtolower(trim((string) $row->email));

                    if ($normalized === '' || $normalized === $row->email) {
                        continue;
                    }

                    DB::table('customers')
                        ->where('id', $row->id)
                        ->update(['email' => $normalized]);
                }
            });
    }

    public function down(): void
    {
        // Irreversible normalization.
    }
};
