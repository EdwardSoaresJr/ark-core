<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arkademy_content_registry', function (Blueprint $table): void {
            $table->id();
            $table->string('source_type', 32);
            $table->unsignedBigInteger('bookstack_id');
            $table->string('visibility', 16);
            $table->string('legacy_key')->nullable();
            $table->string('title')->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->foreignId('promoted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'bookstack_id']);
            $table->index(['visibility', 'source_type']);
            $table->index('legacy_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arkademy_content_registry');
    }
};
