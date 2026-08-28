<?php

namespace App\Ark\Operations\LaborGuides\Rte;

final class RteImportManifest
{
    /** @var array<string, array{columns: list<string>, file: string, expected_rows: int}> */
    public const TABLES = [
        'rte_carlvl3' => [
            'file' => 'rte_carlvl3.csv',
            'columns' => ['lvl123_code', 'car_id_code', 'car_desc', 'lo_yr', 'hi_yr'],
            'expected_rows' => 2820,
        ],
        'rte_engtbl' => [
            'file' => 'rte_engtbl.csv',
            'columns' => ['mod_id_code', 'eng_id_code', 'eng_desc', 'lo_yr', 'hi_yr'],
            'expected_rows' => 32484,
        ],
        'rte_job_lku' => [
            'file' => 'rte_job_lku.csv',
            'columns' => ['job_id_code', 'job_desc', 'eng_req'],
            'expected_rows' => 2024,
        ],
        'rte_jobtbl' => [
            'file' => 'rte_jobtbl.csv',
            'columns' => ['jobid', 'mod_eng_code', 'exception'],
            'expected_rows' => 85944,
        ],
        'rte_joblvl3' => [
            'file' => 'rte_joblvl3.csv',
            'columns' => ['lvl123', 'mod_eng_code', 'job_id_code', 'exception'],
            'expected_rows' => 299480,
        ],
        'rte_lab' => [
            'file' => 'rte_lab.csv',
            'columns' => [
                'lab_id', 'lo_yr', 'hi_yr', 'com_flag', 'add_req',
                'model1', 'model2', 'model3', 'model4', 'model5', 'model6', 'model7', 'model8', 'model9',
                'eng1', 'eng2', 'eng3', 'eng4', 'eng5', 'eng6', 'eng7', 'eng8', 'eng9',
                'add_id1', 'add_hr1', 'add_id2', 'add_hr2', 'add_id3', 'add_hr3',
                'add_id4', 'add_hr4', 'add_id5', 'add_hr5', 'add_id6', 'add_hr6',
                'add_id7', 'add_hr7', 'add_id8', 'add_hr8', 'add_id9', 'add_hr9',
                'hi_hr', 'avg_hr', 'lo_hr',
            ],
            'expected_rows' => 155088,
        ],
        'rte_labcom' => [
            'file' => 'rte_labcom.csv',
            'columns' => ['labid', 'labidx', 'comment'],
            'expected_rows' => 32619,
        ],
    ];

    /**
     * @return list<string>
     */
    public static function tableNames(): array
    {
        return array_keys(self::TABLES);
    }
}
