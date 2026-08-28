<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * public/community/giveaways shadowed the Laravel route on nginx (static dir → 403).
 * Artwork lives under public/assets/community/giveaways/ instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('community_giveaways')
            ->where('image_path', 'community/giveaways/window-ac.webp')
            ->update(['image_path' => 'assets/community/giveaways/window-ac.webp']);

        DB::table('community_giveaways')
            ->where('og_image_path', 'community/giveaways/window-ac.webp')
            ->update(['og_image_path' => 'assets/community/giveaways/window-ac.webp']);
    }

    public function down(): void
    {
        DB::table('community_giveaways')
            ->where('image_path', 'assets/community/giveaways/window-ac.webp')
            ->update(['image_path' => 'community/giveaways/window-ac.webp']);

        DB::table('community_giveaways')
            ->where('og_image_path', 'assets/community/giveaways/window-ac.webp')
            ->update(['og_image_path' => 'community/giveaways/window-ac.webp']);
    }
};
