<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_giveaways', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('hero_title');
            $table->string('hero_subtitle', 1000);
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('prize_label')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->string('og_image_path')->nullable();
            $table->string('entry_button_label')->default('Enter Giveaway');
            $table->string('success_headline')->default("You're entered!");
            $table->text('success_body')->nullable();
            $table->text('share_prompt')->nullable();
            $table->string('closes_label')->nullable();
            $table->string('draw_label')->nullable();
            $table->json('acknowledgements')->nullable();
            $table->json('rules')->nullable();
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->timestamp('draw_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('duplicate_attempts')->default(0);
            $table->foreignId('winner_entry_id')->nullable();
            $table->timestamp('winner_selected_at')->nullable();
            $table->timestamp('winner_confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'closes_at'], 'cg_active_closes');
        });

        Schema::create('community_giveaway_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_giveaway_id')
                ->constrained('community_giveaways')
                ->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone', 32);
            $table->string('city')->nullable();
            $table->string('status', 32)->default('entered');
            $table->string('source', 64)->nullable();
            $table->string('utm_source', 120)->nullable();
            $table->string('utm_medium', 120)->nullable();
            $table->string('utm_campaign', 120)->nullable();
            $table->json('acknowledgements')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['community_giveaway_id', 'email'], 'cg_entry_email_unique');
            $table->unique(['community_giveaway_id', 'phone'], 'cg_entry_phone_unique');
            $table->index('email', 'cg_entry_email');
            $table->index('phone', 'cg_entry_phone');
            $table->index(['community_giveaway_id', 'status'], 'cg_entry_giveaway_status');
            $table->index(['community_giveaway_id', 'submitted_at'], 'cg_entry_giveaway_submitted');
        });

        Schema::table('community_giveaways', function (Blueprint $table) {
            $table->foreign('winner_entry_id')
                ->references('id')
                ->on('community_giveaway_entries')
                ->nullOnDelete();
        });

        if (! DB::table('community_giveaways')->where('slug', 'window-ac-2026')->exists()) {
            $now = now();
            $denver = 'America/Denver';

            DB::table('community_giveaways')->insert([
                'slug' => 'window-ac-2026',
                'title' => 'Community Window A/C Giveaway',
                'hero_title' => 'Helping Our Community Stay Cool',
                'hero_subtitle' => "We're giving away a brand-new window A/C unit to one member of our community.\n\nNo purchase necessary.",
                'description' => 'Enter for a chance to receive a brand-new window air conditioner from Demo Auto Repair. Pickup only — no delivery or installation.',
                // Under public/assets/ — never public/community/ (nginx treats that as a static dir and 403s the Laravel route).
                'image_path' => 'assets/community/giveaways/window-ac.webp',
                'prize_label' => 'window A/C unit',
                'seo_title' => 'Community Window A/C Giveaway | Demo Auto Repair',
                'seo_description' => 'Enter to win a free window air conditioner from Demo Auto Repair. No purchase necessary.',
                'og_image_path' => 'assets/community/giveaways/window-ac.webp',
                'entry_button_label' => 'Enter Giveaway',
                'success_headline' => "You're entered!",
                'success_body' => "Thank you for participating.\n\nThe recipient will be selected at random after entries close.\n\nGood luck—and stay cool!",
                'share_prompt' => "Want to help someone else beat the heat?\n\nShare this giveaway with your friends and family.",
                'closes_label' => 'Friday, August 7 · 6:00 PM',
                'draw_label' => 'Saturday',
                'acknowledgements' => json_encode([
                    [
                        'key' => 'rules',
                        'label' => 'I have read the giveaway rules.',
                    ],
                ], JSON_THROW_ON_ERROR),
                'rules' => json_encode([
                    'No purchase necessary.',
                    'One entry per person.',
                    'Must be at least 18 years old.',
                    'Pickup only.',
                    'No delivery.',
                    'No installation.',
                    'Recipient is responsible for ensuring compatibility with their window/home.',
                    'Entries close Friday at 6:00 PM.',
                    'Recipient selected randomly Saturday.',
                    'Demo Auto Repair reserves the right to verify eligibility.',
                    'Void where prohibited.',
                ], JSON_THROW_ON_ERROR),
                'opens_at' => \Illuminate\Support\Carbon::parse('2026-07-28 00:00:00', $denver)->utc(),
                'closes_at' => \Illuminate\Support\Carbon::parse('2026-08-07 18:00:00', $denver)->utc(),
                'draw_at' => \Illuminate\Support\Carbon::parse('2026-08-08 12:00:00', $denver)->utc(),
                'is_active' => true,
                'duplicate_attempts' => 0,
                'winner_entry_id' => null,
                'winner_selected_at' => null,
                'winner_confirmed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('community_giveaways', function (Blueprint $table) {
            $table->dropForeign(['winner_entry_id']);
        });

        Schema::dropIfExists('community_giveaway_entries');
        Schema::dropIfExists('community_giveaways');
    }
};
