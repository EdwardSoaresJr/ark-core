<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 48);
            $table->string('channel', 32);
            $table->string('direction', 16);
            $table->text('summary');
            // FK added after conversation_messages exists (2026_06_08_100000).
            $table->unsignedBigInteger('conversation_message_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['repair_order_id', 'occurred_at'], 'comm_events_ro_occurred_idx');
            $table->index(['event_type', 'occurred_at'], 'comm_events_type_occurred_idx');
            $table->index('conversation_message_id', 'comm_events_conv_msg_idx');
        });

        if (Schema::hasTable('operational_communications')) {
            DB::table('operational_communications')
                ->orderBy('id')
                ->each(function (object $row): void {
                    DB::table('communication_events')->insert([
                        'repair_order_id' => $row->repair_order_id,
                        'created_by' => $row->created_by,
                        'event_type' => $row->communication_type,
                        'channel' => $row->channel,
                        'direction' => $row->direction,
                        'summary' => $row->summary,
                        'conversation_message_id' => null,
                        'occurred_at' => $row->occurred_at,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_events');
    }
};
