<?php

namespace App\Ark\Operations\Workboard\Eval;

use App\Ark\Operations\Workboard\JobBoardListPage;
use App\Ark\Operations\Workboard\JobBoardListRow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Maps Job Board list rows into the DataTables server-side JSON shape without Yajra.
 * Adopting Yajra later would wrap the same builder; this bridge proves the protocol.
 */
final class JobBoardDataTablesPayload
{
    /**
     * Column order must match the prototype table header.
     *
     * @var list<string|null>
     */
    public const COLUMNS = [
        JobBoardListPage::SORT_RO,
        JobBoardListPage::SORT_CUSTOMER,
        null, // concern — non-sortable
        JobBoardListPage::SORT_STATUS,
        JobBoardListPage::SORT_TECH,
        JobBoardListPage::SORT_TOTAL,
        JobBoardListPage::SORT_ATTENTION,
        null, // activity — non-sortable
        JobBoardListPage::SORT_UPDATED,
        null, // open chevron
    ];

    /**
     * @return array{sort: string, dir: string, page: int, per_page: int, draw: int, q: string}
     */
    public static function mapRequest(Request $request): array
    {
        $draw = max(0, (int) $request->input('draw', 1));
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', JobBoardListPage::PAGE_SIZE);
        $perPage = max(1, min(100, $length > 0 ? $length : JobBoardListPage::PAGE_SIZE));
        $page = (int) floor($start / $perPage) + 1;

        $orderCol = (int) data_get($request->input('order'), '0.column', 8);
        $orderDir = strtolower((string) data_get($request->input('order'), '0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sort = self::COLUMNS[$orderCol] ?? JobBoardListPage::SORT_UPDATED;
        if ($sort === null) {
            $sort = JobBoardListPage::SORT_UPDATED;
            $orderDir = 'desc';
        }

        $dtSearch = trim((string) data_get($request->input('search'), 'value', ''));
        $q = trim((string) $request->query('q', $dtSearch));

        return [
            'sort' => $sort,
            'dir' => $orderDir,
            'page' => $page,
            'per_page' => $perPage,
            'draw' => $draw,
            'q' => $q,
        ];
    }

    /**
     * @param  list<JobBoardListRow>  $rows
     * @return array{draw: int, recordsTotal: int, recordsFiltered: int, data: list<array<string, string>>, meta: array<string, mixed>}
     */
    public static function fromPage(JobBoardListPage $page, int $draw): array
    {
        $data = array_map(
            fn (JobBoardListRow $row): array => self::rowCells($row),
            $page->rows,
        );

        return [
            'draw' => $draw,
            'recordsTotal' => $page->queueTotal,
            'recordsFiltered' => $page->filteredTotal,
            'data' => $data,
            'meta' => [
                'lane' => $page->laneKey,
                'queue_total' => $page->queueTotal,
                'filtered_total' => $page->filteredTotal,
                'page' => $page->page,
                'per_page' => $page->perPage,
                'sort' => $page->sortKey,
                'dir' => $page->sortDir,
                'lane_tabs' => $page->laneTabs,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function rowCells(JobBoardListRow $row): array
    {
        $html = View::make('operations.workboard.eval.datatables-row-cells', ['row' => $row])->render();
        $cells = json_decode($html, true);

        return is_array($cells) ? $cells : [];
    }
}
