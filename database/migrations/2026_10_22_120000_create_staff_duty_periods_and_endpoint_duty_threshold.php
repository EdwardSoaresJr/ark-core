<?php

use App\Ark\Operations\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_duty_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 32);
            $table->timestamp('started_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 32)->nullable();
            $table->foreignId('authorized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'ended_at'], 'staff_duty_user_open_idx');
        });

        Schema::table('telephony_endpoints', function (Blueprint $table) {
            $table->unsignedSmallInteger('on_duty_after_minutes')
                ->default(0)
                ->after('presence_timeout_minutes');
        });

        // Cell endpoints now own their number. Keep each one ringing the number it rings today.
        DB::table('telephony_endpoints')
            ->where('type', 'cell')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->get(['id', 'user_id', 'destination'])
            ->each(function (object $endpoint): void {
                $profile = PhoneNumber::normalize(DB::table('users')->where('id', $endpoint->user_id)->value('phone'));

                if ($profile !== null && $profile !== $endpoint->destination) {
                    DB::table('telephony_endpoints')->where('id', $endpoint->id)->update(['destination' => $profile]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('telephony_endpoints', function (Blueprint $table) {
            $table->dropColumn('on_duty_after_minutes');
        });

        Schema::dropIfExists('staff_duty_periods');
    }
};
