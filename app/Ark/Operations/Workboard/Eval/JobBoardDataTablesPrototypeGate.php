<?php

namespace App\Ark\Operations\Workboard\Eval;

/**
 * Review-only Job Board DataTables prototype. Never available outside local/testing
 * unless ARK_EVAL_JOB_BOARD_DATATABLES is explicitly true.
 */
final class JobBoardDataTablesPrototypeGate
{
    public static function enabled(): bool
    {
        if ((bool) config('ark.eval_job_board_datatables', false)) {
            return true;
        }

        return app()->environment(['local', 'testing']);
    }

    public static function abortUnlessEnabled(): void
    {
        abort_unless(self::enabled(), 404);
    }
}
