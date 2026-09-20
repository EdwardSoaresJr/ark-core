<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->string('ingress_ip', 45)->nullable()->after('metadata');
            $table->text('ingress_user_agent')->nullable()->after('ingress_ip');
            $table->text('ingress_referrer')->nullable()->after('ingress_user_agent');
            $table->timestamp('form_rendered_at')->nullable()->after('ingress_referrer');
            $table->json('spam_signals')->nullable()->after('form_rendered_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn([
                'ingress_ip',
                'ingress_user_agent',
                'ingress_referrer',
                'form_rendered_at',
                'spam_signals',
            ]);
        });
    }
};
