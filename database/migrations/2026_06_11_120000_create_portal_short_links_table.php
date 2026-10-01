<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_short_links', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 12)->unique('portal_short_links_code_unique');
            $table->text('destination_url');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('expires_at', 'portal_short_links_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_short_links');
    }
};
