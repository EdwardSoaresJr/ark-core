<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SMS_SLICE_FK = 'comm_reviews_sms_slice_fk';

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $this->upMysql();

            return;
        }

        $this->upDefault();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $this->downMysql();

            return;
        }

        $this->downDefault();
    }

    private function upDefault(): void
    {
        Schema::table('communication_reviews', function (Blueprint $table): void {
            $table->dropForeign(['call_session_id']);
            $table->dropUnique(['call_session_id']);
        });

        Schema::table('communication_reviews', function (Blueprint $table): void {
            $table->unsignedBigInteger('call_session_id')->nullable()->change();
            $table->unsignedBigInteger('conversation_sms_intelligence_slice_id')
                ->nullable()
                ->after('call_session_id');

            $table->foreign('conversation_sms_intelligence_slice_id', self::SMS_SLICE_FK)
                ->references('id')
                ->on('conversation_sms_intelligence_slices')
                ->cascadeOnDelete();

            $table->foreign('call_session_id')
                ->references('id')
                ->on('call_sessions')
                ->cascadeOnDelete();

            $table->unique('call_session_id', 'comm_reviews_call_session_unique');
            $table->unique('conversation_sms_intelligence_slice_id', 'comm_reviews_sms_slice_unique');
        });
    }

    private function downDefault(): void
    {
        Schema::table('communication_reviews', function (Blueprint $table): void {
            $table->dropForeign(['call_session_id']);
            $table->dropForeign(self::SMS_SLICE_FK);
            $table->dropUnique('comm_reviews_call_session_unique');
            $table->dropUnique('comm_reviews_sms_slice_unique');
            $table->dropColumn('conversation_sms_intelligence_slice_id');
        });

        Schema::table('communication_reviews', function (Blueprint $table): void {
            $table->unsignedBigInteger('call_session_id')->nullable(false)->change();
            $table->unique('call_session_id');
            $table->foreign('call_session_id')->references('id')->on('call_sessions')->cascadeOnDelete();
        });
    }

    private function upMysql(): void
    {
        $this->dropForeignKeyIfExists('communication_reviews', 'communication_reviews_call_session_id_foreign');
        $this->dropIndexIfExists('communication_reviews', 'communication_reviews_call_session_id_unique');

        Schema::table('communication_reviews', function (Blueprint $table): void {
            $table->unsignedBigInteger('call_session_id')->nullable()->change();
        });

        if (! Schema::hasColumn('communication_reviews', 'conversation_sms_intelligence_slice_id')) {
            Schema::table('communication_reviews', function (Blueprint $table): void {
                $table->unsignedBigInteger('conversation_sms_intelligence_slice_id')
                    ->nullable()
                    ->after('call_session_id');
            });
        }

        $this->dropForeignKeyIfExists('communication_reviews', self::SMS_SLICE_FK);
        $this->dropForeignKeyIfExists(
            'communication_reviews',
            'communication_reviews_conversation_sms_intelligence_slice_id_foreign',
        );

        if (! $this->foreignKeyExists('communication_reviews', 'communication_reviews_call_session_id_foreign')) {
            Schema::table('communication_reviews', function (Blueprint $table): void {
                $table->foreign('call_session_id')
                    ->references('id')
                    ->on('call_sessions')
                    ->cascadeOnDelete();
            });
        }

        if (! $this->foreignKeyExists('communication_reviews', self::SMS_SLICE_FK)) {
            Schema::table('communication_reviews', function (Blueprint $table): void {
                $table->foreign('conversation_sms_intelligence_slice_id', self::SMS_SLICE_FK)
                    ->references('id')
                    ->on('conversation_sms_intelligence_slices')
                    ->cascadeOnDelete();
            });
        }

        if (! $this->indexExists('communication_reviews', 'comm_reviews_call_session_unique')) {
            Schema::table('communication_reviews', function (Blueprint $table): void {
                $table->unique('call_session_id', 'comm_reviews_call_session_unique');
            });
        }

        if (! $this->indexExists('communication_reviews', 'comm_reviews_sms_slice_unique')) {
            Schema::table('communication_reviews', function (Blueprint $table): void {
                $table->unique('conversation_sms_intelligence_slice_id', 'comm_reviews_sms_slice_unique');
            });
        }
    }

    private function downMysql(): void
    {
        $this->dropForeignKeyIfExists('communication_reviews', 'communication_reviews_call_session_id_foreign');
        $this->dropForeignKeyIfExists('communication_reviews', self::SMS_SLICE_FK);
        $this->dropForeignKeyIfExists(
            'communication_reviews',
            'communication_reviews_conversation_sms_intelligence_slice_id_foreign',
        );
        $this->dropIndexIfExists('communication_reviews', 'comm_reviews_call_session_unique');
        $this->dropIndexIfExists('communication_reviews', 'comm_reviews_sms_slice_unique');

        if (Schema::hasColumn('communication_reviews', 'conversation_sms_intelligence_slice_id')) {
            Schema::table('communication_reviews', function (Blueprint $table): void {
                $table->dropColumn('conversation_sms_intelligence_slice_id');
            });
        }

        Schema::table('communication_reviews', function (Blueprint $table): void {
            $table->unsignedBigInteger('call_session_id')->nullable(false)->change();
            $table->unique('call_session_id');
            $table->foreign('call_session_id')->references('id')->on('call_sessions')->cascadeOnDelete();
        });
    }

    private function dropForeignKeyIfExists(string $table, string $constraintName): void
    {
        if (! $this->foreignKeyExists($table, $constraintName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($constraintName): void {
            $blueprint->dropForeign($constraintName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
            $blueprint->dropIndex($indexName);
        });
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        return DB::selectOne(
            'SELECT 1 AS present
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = ?',
            [$table, $constraintName, 'FOREIGN KEY'],
        ) !== null;
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return DB::selectOne(
            'SELECT 1 AS present
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?',
            [$table, $indexName],
        ) !== null;
    }
};
