<?php

use App\Ark\Operations\Labor\TechnicianLaborPayBasis;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_compensation_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('labor_pay_basis', 16);
            $table->unsignedInteger('flag_rate_cents')->nullable();
            $table->unsignedInteger('floor_rate_cents')->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('superseded_by_agreement_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'effective_from'], 'tca_user_effective_idx');
            $table->index(['user_id', 'effective_to'], 'tca_user_effective_to_idx');
            $table->foreign('superseded_by_agreement_id', 'tca_superseded_fk')
                ->references('id')
                ->on('technician_compensation_agreements')
                ->nullOnDelete();
        });

        // Honest adoption boundary — do not invent pre-Phase-1A history.
        $adoptedAt = now();

        User::query()
            ->where(function ($query): void {
                $query->where('labor_pay_basis', TechnicianLaborPayBasis::Flag->value)
                    ->orWhereNotNull('flag_rate_cents')
                    ->orWhereNotNull('floor_rate_cents');
            })
            ->orderBy('id')
            ->each(function (User $user) use ($adoptedAt): void {
                DB::table('technician_compensation_agreements')->insert([
                    'user_id' => $user->id,
                    'labor_pay_basis' => $user->labor_pay_basis ?: TechnicianLaborPayBasis::Hourly->value,
                    'flag_rate_cents' => $user->flag_rate_cents,
                    'floor_rate_cents' => $user->floor_rate_cents,
                    'effective_from' => $adoptedAt,
                    'effective_to' => null,
                    'created_by_user_id' => null,
                    'superseded_by_agreement_id' => null,
                    'created_at' => $adoptedAt,
                    'updated_at' => $adoptedAt,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_compensation_agreements');
    }
};
