<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('location')->nullable();
            $table->string('host_company_name');
            $table->string('host_logo_path')->nullable();
            $table->string('partner_company_name')->nullable();
            $table->string('partner_logo_path')->nullable();
            $table->string('headline');
            $table->string('subheadline', 1000);
            $table->string('giveaway_name')->nullable();
            $table->timestamp('drawing_time')->nullable();
            $table->string('marketing_permission_text', 2000);
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['is_active', 'starts_at'], 'events_active_starts');
        });

        Schema::create('event_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email');
            $table->string('phone', 32)->nullable();
            $table->string('vehicle')->nullable();
            $table->string('referral_source', 32)->nullable();
            $table->boolean('marketing_opt_in')->default(false);
            $table->boolean('giveaway_opt_in')->default(true);
            $table->string('winner_for_prize')->nullable();
            $table->timestamp('winner_drawn_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'created_at'], 'evt_att_event_created');
            $table->index(['event_id', 'giveaway_opt_in'], 'evt_att_giveaway');
            $table->index(['event_id', 'winner_drawn_at'], 'evt_att_winner');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendees');
        Schema::dropIfExists('events');
    }
};
