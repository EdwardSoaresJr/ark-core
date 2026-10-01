<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rte_carlvl3', function (Blueprint $table): void {
            $table->string('lvl123_code', 7);
            $table->string('car_id_code', 7);
            $table->string('car_desc', 39);
            $table->string('lo_yr', 5);
            $table->string('hi_yr', 5);

            $table->index('car_id_code', 'rte_carlvl3_car_idx');
            $table->index('lvl123_code', 'rte_carlvl3_lvl_idx');
        });

        Schema::create('rte_engtbl', function (Blueprint $table): void {
            $table->string('mod_id_code', 7);
            $table->string('eng_id_code', 7);
            $table->string('eng_desc', 39);
            $table->string('lo_yr', 5);
            $table->string('hi_yr', 5);

            $table->index('eng_id_code', 'rte_engtbl_eng_idx');
            $table->index('mod_id_code', 'rte_engtbl_mod_idx');
        });

        Schema::create('rte_job_lku', function (Blueprint $table): void {
            $table->string('job_id_code', 7);
            $table->string('job_desc', 39);
            $table->string('eng_req', 1);

            $table->index('job_id_code', 'rte_job_lku_code_idx');
        });

        Schema::create('rte_jobtbl', function (Blueprint $table): void {
            $table->string('jobid', 5);
            $table->string('mod_eng_code', 7);
            $table->string('exception', 1);

            $table->index('jobid', 'rte_jobtbl_job_idx');
            $table->index(['jobid', 'mod_eng_code'], 'rte_jobtbl_job_mod_idx');
        });

        Schema::create('rte_joblvl3', function (Blueprint $table): void {
            $table->string('lvl123', 7);
            $table->string('mod_eng_code', 7);
            $table->string('job_id_code', 5);
            $table->string('exception', 1);

            $table->index(['lvl123', 'mod_eng_code'], 'rte_joblvl3_menu_idx');
            $table->index('job_id_code', 'rte_joblvl3_job_idx');
        });

        Schema::create('rte_lab', function (Blueprint $table): void {
            $table->string('lab_id', 14);
            $table->string('lo_yr', 5);
            $table->string('hi_yr', 5);
            $table->string('com_flag', 1);
            $table->string('add_req', 1);
            $table->string('model1', 7)->nullable();
            $table->string('model2', 7)->nullable();
            $table->string('model3', 7)->nullable();
            $table->string('model4', 7)->nullable();
            $table->string('model5', 7)->nullable();
            $table->string('model6', 7)->nullable();
            $table->string('model7', 7)->nullable();
            $table->string('model8', 7)->nullable();
            $table->string('model9', 7)->nullable();
            $table->string('eng1', 7)->nullable();
            $table->string('eng2', 7)->nullable();
            $table->string('eng3', 7)->nullable();
            $table->string('eng4', 7)->nullable();
            $table->string('eng5', 7)->nullable();
            $table->string('eng6', 7)->nullable();
            $table->string('eng7', 7)->nullable();
            $table->string('eng8', 7)->nullable();
            $table->string('eng9', 7)->nullable();
            $table->string('add_id1', 6)->nullable();
            $table->decimal('add_hr1', 4, 1)->nullable();
            $table->string('add_id2', 6)->nullable();
            $table->decimal('add_hr2', 4, 1)->nullable();
            $table->string('add_id3', 6)->nullable();
            $table->decimal('add_hr3', 4, 1)->nullable();
            $table->string('add_id4', 6)->nullable();
            $table->decimal('add_hr4', 4, 1)->nullable();
            $table->string('add_id5', 6)->nullable();
            $table->decimal('add_hr5', 4, 1)->nullable();
            $table->string('add_id6', 6)->nullable();
            $table->decimal('add_hr6', 4, 1)->nullable();
            $table->string('add_id7', 6)->nullable();
            $table->decimal('add_hr7', 4, 1)->nullable();
            $table->string('add_id8', 6)->nullable();
            $table->decimal('add_hr8', 4, 1)->nullable();
            $table->string('add_id9', 6)->nullable();
            $table->decimal('add_hr9', 4, 1)->nullable();
            $table->decimal('hi_hr', 5, 2)->nullable();
            $table->decimal('avg_hr', 5, 2)->nullable();
            $table->decimal('lo_hr', 5, 2)->nullable();

            $table->index('lab_id', 'rte_lab_id_idx');
            $table->index('model1', 'rte_lab_model1_idx');
        });

        Schema::create('rte_labcom', function (Blueprint $table): void {
            $table->string('labid', 14);
            $table->string('labidx', 32)->nullable();
            $table->text('comment')->nullable();

            $table->index('labid', 'rte_labcom_labid_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rte_labcom');
        Schema::dropIfExists('rte_lab');
        Schema::dropIfExists('rte_joblvl3');
        Schema::dropIfExists('rte_jobtbl');
        Schema::dropIfExists('rte_job_lku');
        Schema::dropIfExists('rte_engtbl');
        Schema::dropIfExists('rte_carlvl3');
    }
};
