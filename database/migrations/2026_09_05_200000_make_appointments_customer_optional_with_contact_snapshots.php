<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Appointment becomes an independent booking record:
 * - customer_id optional (nullOnDelete — history survives customer deletion)
 * - booking-time contact snapshots
 * - optional lead provenance
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'contact_name')) {
                $table->string('contact_name')->nullable();
            }
            if (! Schema::hasColumn('appointments', 'contact_phone')) {
                $table->string('contact_phone', 32)->nullable();
            }
            if (! Schema::hasColumn('appointments', 'contact_email')) {
                $table->string('contact_email')->nullable();
            }
            if (! Schema::hasColumn('appointments', 'lead_id')) {
                $table->unsignedBigInteger('lead_id')->nullable()->index();
            }
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreign('lead_id')
                ->references('id')
                ->on('leads')
                ->nullOnDelete();
        });

        $this->backfillSnapshotsFromCustomers();

        $this->makeCustomerIdNullableNullOnDelete();
    }

    public function down(): void
    {
        $orphans = (int) DB::table('appointments')->whereNull('customer_id')->count();
        if ($orphans > 0) {
            throw new \RuntimeException(
                "Cannot reverse independent appointments: {$orphans} appointment(s) have no customer_id.",
            );
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $drops = array_values(array_filter([
                Schema::hasColumn('appointments', 'contact_name') ? 'contact_name' : null,
                Schema::hasColumn('appointments', 'contact_phone') ? 'contact_phone' : null,
                Schema::hasColumn('appointments', 'contact_email') ? 'contact_email' : null,
                Schema::hasColumn('appointments', 'lead_id') ? 'lead_id' : null,
            ]));
            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });

        $this->makeCustomerIdRequiredCascade();
    }

    /**
     * Protect historical appointments: if Customer is later nullOnDelete'd,
     * booking identity must already live on the Appointment row.
     */
    private function backfillSnapshotsFromCustomers(): void
    {
        $rows = DB::table('appointments')
            ->whereNotNull('customer_id')
            ->where(function ($query): void {
                $query->whereNull('contact_name')
                    ->orWhereNull('contact_phone')
                    ->orWhereNull('contact_email');
            })
            ->orderBy('id')
            ->get(['id', 'customer_id', 'contact_name', 'contact_phone', 'contact_email']);

        if ($rows->isEmpty()) {
            return;
        }

        $customers = DB::table('customers')
            ->whereIn('id', $rows->pluck('customer_id')->unique()->filter()->all())
            ->get(['id', 'first_name', 'last_name', 'phone', 'email'])
            ->keyBy('id');

        foreach ($rows as $row) {
            $customer = $customers->get($row->customer_id);
            if ($customer === null) {
                continue;
            }

            $name = trim(implode(' ', array_filter([
                trim((string) ($customer->first_name ?? '')),
                trim((string) ($customer->last_name ?? '')),
            ])));
            $phone = trim((string) ($customer->phone ?? ''));
            $email = strtolower(trim((string) ($customer->email ?? '')));

            $update = [];
            if ($row->contact_name === null && $name !== '') {
                $update['contact_name'] = $name;
            }
            if ($row->contact_phone === null && $phone !== '') {
                $update['contact_phone'] = $phone;
            }
            if ($row->contact_email === null && $email !== '') {
                $update['contact_email'] = $email;
            }

            if ($update !== []) {
                DB::table('appointments')->where('id', $row->id)->update($update);
            }
        }
    }

    private function makeCustomerIdNullableNullOnDelete(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropForeign(['customer_id']);
            });
            DB::statement('ALTER TABLE appointments MODIFY customer_id BIGINT UNSIGNED NULL');
            Schema::table('appointments', function (Blueprint $table) {
                $table->foreign('customer_id')
                    ->references('id')
                    ->on('customers')
                    ->nullOnDelete();
            });

            return;
        }

        // SQLite (tests) and other drivers: rebuild nullability + FK action together.
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->change();
        });
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->nullOnDelete();
        });
    }

    private function makeCustomerIdRequiredCascade(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE appointments MODIFY customer_id BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('appointments', function (Blueprint $table) {
                $table->unsignedBigInteger('customer_id')->nullable(false)->change();
            });
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->cascadeOnDelete();
        });
    }
};
