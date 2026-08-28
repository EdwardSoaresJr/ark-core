<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Day / week Scheduling Workspace — packages calendar once per request.
 * Soft-capacity day board + DayLens chips (projection-owned filters).
 */
final class SchedulingWorkspaceProjection
{
    public function __construct(
        private readonly AppointmentScheduleRowPresenter $rows,
        private readonly AppointmentStaffOptions $staff,
        private readonly OperationalCapacityProjection $capacity,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(
        Carbon $focusDay,
        string $view = 'day',
        string $lanes = 'agenda',
        ?User $viewer = null,
        bool $showEmptyLanes = false,
        string|DayLens|null $lens = null,
    ): array {
        $board = ScheduleBoardView::parse($view);
        $lanes = 'agenda';
        $selectedLens = $lens instanceof DayLens ? $lens : DayLens::parse(is_string($lens) ? $lens : null);

        $timezone = ShopDisplayTimezone::resolve();
        $focus = Carbon::parse($focusDay->toDateString(), $timezone)->startOfDay();
        $hours = ShopSettings::current()->schedulingHours();
        $dayKey = strtolower($focus->englishDayOfWeek);
        $dayHours = $hours[$dayKey] ?? ['enabled' => true, 'open' => '08:00', 'close' => '17:00'];

        $open = $dayHours['enabled']
            ? $focus->copy()->setTimeFromTimeString($dayHours['open'])
            : $focus->copy()->setTime(8, 0);
        $close = $dayHours['enabled']
            ? $focus->copy()->setTimeFromTimeString($dayHours['close'])
            : $focus->copy()->setTime(17, 0);

        $weekStart = $focus->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $monthStart = $focus->copy()->startOfMonth()->startOfDay();
        [$rangeStart, $rangeEnd] = $board->range($focus);
        $focusDate = $focus->toDateString();
        $isDay = $board === ScheduleBoardView::Day;

        $appointments = Appointment::query()
            ->with(['customer', 'vehicle', 'advisor', 'technician', 'workstation', 'repairOrder'])
            ->where('starts_at', '<', $rangeEnd->copy()->utc())
            ->where('ends_at', '>', $rangeStart->copy()->utc())
            ->whereNot('status', AppointmentStatus::Canceled)
            ->orderBy('starts_at')
            ->get();

        $rangeCards = $appointments->map(
            function (Appointment $appointment) use ($viewer, $open, $focusDate, $isDay): array {
                $card = $this->rows->present($appointment, $viewer);
                $start = ShopDisplayTimezone::present($appointment->starts_at);
                if ($isDay && $start->toDateString() === $focusDate) {
                    $card['minutes_from_open'] = max(0, (int) $open->diffInMinutes($start, false));
                }

                return $card;
            },
        )->values()->all();

        $dayCards = array_values(array_filter(
            $rangeCards,
            fn (array $card): bool => str_starts_with((string) $card['starts_at'], $focusDate),
        ));
        $chipSource = $isDay ? $dayCards : $rangeCards;

        $slotMinutes = AppointmentSlotMinutes::resolve();
        $slots = [];
        if ($isDay) {
            $cursor = $open->copy();
            while ($cursor->lt($close)) {
                $slotEnd = $cursor->copy()->addMinutes($slotMinutes);
                $slots[] = [
                    'starts_at' => $cursor->format('Y-m-d\TH:i'),
                    'ends_at' => $slotEnd->format('Y-m-d\TH:i'),
                    'label' => $cursor->format('g:i A'),
                    'minutes_from_open' => (int) $open->diffInMinutes($cursor),
                ];
                $cursor->addMinutes($slotMinutes);
            }
        }

        $chips = $this->buildDayLensChips($chipSource, $selectedLens);
        $selectedChip = collect($chips)->firstWhere('selected', true) ?? $chips[0];
        $selectedLens = DayLens::parse((string) ($selectedChip['key'] ?? DayLens::KIND_AGENDA));
        $visibleRangeCards = $selectedLens->filterCards($rangeCards);
        $visibleDayCards = $selectedLens->filterCards($dayCards);

        $laneBundle = $isDay
            ? $this->laneRows($lanes, $visibleDayCards, 'day', $showEmptyLanes)
            : ['rows' => [], 'show_empty_lanes' => false, 'empty_lanes_hidden' => 0];
        if ($isDay && isset($laneBundle['rows'][0])) {
            $laneBundle['rows'][0]['label'] = (string) ($selectedChip['label'] ?? 'Agenda');
        }
        $gridMinutes = max(60, (int) $open->diffInMinutes($close));
        $capacityView = $board === ScheduleBoardView::Day ? 'day' : 'week';

        return [
            'view' => $board->value,
            'view_options' => array_map(
                fn (ScheduleBoardView $option): array => [
                    'key' => $option->value,
                    'label' => $option->label(),
                    'selected' => $option === $board,
                ],
                ScheduleBoardView::cases(),
            ),
            'lanes' => $lanes,
            'lens' => $selectedLens->key(),
            'chips' => $chips,
            'focus_date' => $focusDate,
            'focus_label' => $board->focusLabel($focus),
            'prev_date' => $focus->copy()->subDay()->toDateString(),
            'next_date' => $focus->copy()->addDay()->toDateString(),
            'nav_prev_date' => $board->navPrev($focus)->toDateString(),
            'nav_next_date' => $board->navNext($focus)->toDateString(),
            'today_date' => ShopDisplayTimezone::now()->toDateString(),
            'open_time' => $open->format('H:i'),
            'close_time' => $close->format('H:i'),
            'grid_minutes' => $gridMinutes,
            'slot_minutes' => $slotMinutes,
            'slots' => $slots,
            'lane_rows' => $laneBundle['rows'],
            'show_empty_lanes' => $laneBundle['show_empty_lanes'],
            'empty_lanes_hidden' => $laneBundle['empty_lanes_hidden'],
            'cards' => $visibleDayCards,
            'total_count' => count($visibleRangeCards),
            'agenda_count' => count($dayCards),
            'capacity_rail' => $this->capacity->resolve($focus, $capacityView),
            'create_base_url' => route('operations.schedule'),
            'week_days' => $this->weekDays($weekStart, $visibleRangeCards, 7),
            'month_weeks' => $this->monthWeeks($monthStart, $visibleRangeCards),
            'week_label' => 'Week of '.$weekStart->format('M j, Y'),
            'lens_label' => (string) ($selectedChip['label'] ?? 'Agenda'),
        ];
    }

    /**
     * Projection-owned chip strip. Agenda always; others only when count > 0.
     *
     * @param  list<array<string, mixed>>  $cards
     * @return list<array{key: string, label: string, count: int, selected: bool}>
     */
    private function buildDayLensChips(array $cards, DayLens $selected): array
    {
        $chips = [[
            'key' => DayLens::KIND_AGENDA,
            'label' => 'Agenda',
            'count' => count($cards),
            'selected' => $selected->isAgenda(),
        ]];

        $unassignedCount = count(DayLens::unassigned()->filterCards($cards));
        if ($unassignedCount > 0) {
            $chips[] = [
                'key' => DayLens::KIND_UNASSIGNED,
                'label' => 'Unassigned',
                'count' => $unassignedCount,
                'selected' => $selected->kind === DayLens::KIND_UNASSIGNED,
            ];
        }

        /** @var array<int, array{id: int, label: string, count: int}> $technicians */
        $technicians = [];
        /** @var array<int, array{id: int, label: string, count: int}> $workstations */
        $workstations = [];

        foreach ($cards as $card) {
            $techId = (int) ($card['technician_user_id'] ?? 0);
            if ($techId > 0) {
                if (! isset($technicians[$techId])) {
                    $label = trim((string) ($card['technician_label'] ?? ''));
                    $technicians[$techId] = [
                        'id' => $techId,
                        'label' => $label !== '' ? $label : 'Technician '.$techId,
                        'count' => 0,
                    ];
                }
                $technicians[$techId]['count']++;
            }

            $wsId = (int) ($card['workstation_id'] ?? 0);
            if ($wsId > 0) {
                if (! isset($workstations[$wsId])) {
                    $label = trim((string) ($card['workstation_label'] ?? ''));
                    $workstations[$wsId] = [
                        'id' => $wsId,
                        'label' => $label !== '' ? $label : 'Bay '.$wsId,
                        'count' => 0,
                    ];
                }
                $workstations[$wsId]['count']++;
            }
        }

        uasort(
            $technicians,
            fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']),
        );
        uasort(
            $workstations,
            fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']),
        );

        foreach ($technicians as $technician) {
            if ($technician['count'] < 1) {
                continue;
            }
            $key = DayLens::technician($technician['id'])->key();
            $chips[] = [
                'key' => $key,
                'label' => $technician['label'],
                'count' => $technician['count'],
                'selected' => $selected->key() === $key,
            ];
        }

        foreach ($workstations as $workstation) {
            if ($workstation['count'] < 1) {
                continue;
            }
            $key = DayLens::workstation($workstation['id'])->key();
            $chips[] = [
                'key' => $key,
                'label' => $workstation['label'],
                'count' => $workstation['count'],
                'selected' => $selected->key() === $key,
            ];
        }

        $hasSelected = false;
        foreach ($chips as $chip) {
            if ($chip['selected']) {
                $hasSelected = true;
                break;
            }
        }

        if (! $hasSelected) {
            $chips[0]['selected'] = true;
        }

        return $chips;
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @return array{rows: list<array{key: string, label: string, resource_id: int|null, cards: list<array<string, mixed>>}>, show_empty_lanes: bool, empty_lanes_hidden: int}
     */
    private function laneRows(string $lanes, array $cards, string $view = 'day', bool $showEmptyLanes = false): array
    {
        // Agenda is one wide lane — reserve side-by-side columns even for a lone card.
        $minColumns = ($lanes === 'agenda' && $view === 'day') ? 3 : 1;

        if ($lanes === 'agenda') {
            return [
                'rows' => [[
                    'key' => 'agenda',
                    'label' => 'Schedule',
                    'resource_id' => null,
                    'cards' => $this->packLaneCards($cards, $minColumns),
                ]],
                'show_empty_lanes' => false,
                'empty_lanes_hidden' => 0,
            ];
        }

        if ($lanes === 'workstation') {
            $stations = $this->staff->schedulableWorkstations();
            $schedulableIds = $stations->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
            $rows = [];
            foreach ($stations as $station) {
                $laneCards = array_values(array_filter(
                    $cards,
                    fn (array $card): bool => (int) ($card['workstation_id'] ?? 0) === (int) $station->id,
                ));
                $rows[] = [
                    'key' => 'ws-'.$station->id,
                    'label' => $station->displayLocation(),
                    'resource_id' => (int) $station->id,
                    'cards' => $this->packLaneCards($laneCards, $minColumns),
                ];
            }
            $unassigned = array_values(array_filter(
                $cards,
                fn (array $card): bool => empty($card['workstation_id']),
            ));
            if ($unassigned !== [] || $rows === []) {
                $rows[] = [
                    'key' => 'ws-none',
                    'label' => 'Unassigned',
                    'resource_id' => null,
                    'cards' => $this->packLaneCards($unassigned, $minColumns),
                ];
            }

            $orphaned = array_values(array_filter(
                $cards,
                function (array $card) use ($schedulableIds): bool {
                    $workstationId = (int) ($card['workstation_id'] ?? 0);

                    return $workstationId > 0 && ! in_array($workstationId, $schedulableIds, true);
                },
            ));
            if ($orphaned !== []) {
                $rows[] = [
                    'key' => 'ws-legacy',
                    'label' => 'Unavailable assignment',
                    'resource_id' => null,
                    'cards' => $this->packLaneCards($orphaned, $minColumns),
                ];
            }

            if ($rows === []) {
                $rows = [[
                    'key' => 'ws-none',
                    'label' => 'Unassigned',
                    'resource_id' => null,
                    'cards' => $this->packLaneCards($cards, $minColumns),
                ]];
            }

            return $this->finalizeFloorPlannerLanes($rows, $showEmptyLanes);
        }

        $technicians = $this->staff->technicians();
        $techIds = $technicians->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $rows = [];
        foreach ($technicians as $technician) {
            $laneCards = array_values(array_filter(
                $cards,
                fn (array $card): bool => (int) ($card['technician_user_id'] ?? 0) === (int) $technician->id,
            ));
            $rows[] = [
                'key' => 'tech-'.$technician->id,
                'label' => $technician->name,
                'resource_id' => (int) $technician->id,
                'cards' => $this->packLaneCards($laneCards, $minColumns),
            ];
        }
        $unassigned = array_values(array_filter(
            $cards,
            fn (array $card): bool => empty($card['technician_user_id']),
        ));
        if ($unassigned !== [] || $rows === []) {
            $rows[] = [
                'key' => 'tech-none',
                'label' => 'Unassigned',
                'resource_id' => null,
                'cards' => $this->packLaneCards($unassigned, $minColumns),
            ];
        }

        $orphaned = array_values(array_filter(
            $cards,
            function (array $card) use ($techIds): bool {
                $technicianId = (int) ($card['technician_user_id'] ?? 0);

                return $technicianId > 0 && ! in_array($technicianId, $techIds, true);
            },
        ));
        if ($orphaned !== []) {
            $rows[] = [
                'key' => 'tech-legacy',
                'label' => 'Unavailable assignment',
                'resource_id' => null,
                'cards' => $this->packLaneCards($orphaned, $minColumns),
            ];
        }

        if ($rows === []) {
            $rows = [[
                'key' => 'tech-none',
                'label' => 'Unassigned',
                'resource_id' => null,
                'cards' => $this->packLaneCards($cards, $minColumns),
            ]];
        }

        // Technicians stay full lanes for now — Floor Planner honesty applies to Bays.
        return [
            'rows' => $rows,
            'show_empty_lanes' => false,
            'empty_lanes_hidden' => 0,
        ];
    }

    /**
     * Floor Planner: empty bay columns are absence, not information.
     *
     * @param  list<array{key: string, label: string, resource_id: int|null, cards: list<array<string, mixed>>}>  $rows
     * @return array{rows: list<array{key: string, label: string, resource_id: int|null, cards: list<array<string, mixed>>}>, show_empty_lanes: bool, empty_lanes_hidden: int}
     */
    private function finalizeFloorPlannerLanes(array $rows, bool $showEmptyLanes): array
    {
        $emptyCount = count(array_filter(
            $rows,
            fn (array $row): bool => $row['cards'] === [],
        ));

        if ($showEmptyLanes || $emptyCount === 0) {
            return [
                'rows' => $rows,
                'show_empty_lanes' => $showEmptyLanes,
                'empty_lanes_hidden' => 0,
            ];
        }

        $active = array_values(array_filter(
            $rows,
            fn (array $row): bool => $row['cards'] !== [],
        ));

        if ($active === []) {
            $active = [[
                'key' => 'ws-none',
                'label' => 'Unassigned',
                'resource_id' => null,
                'cards' => [],
            ]];
        }

        return [
            'rows' => $active,
            'show_empty_lanes' => false,
            'empty_lanes_hidden' => $emptyCount,
        ];
    }

    /**
     * Pack overlapping cards into side-by-side columns (calendar overlap layout).
     *
     * @param  list<array<string, mixed>>  $cards
     * @return list<array<string, mixed>>
     */
    private function packLaneCards(array $cards, int $minColumns = 1): array
    {
        if ($cards === []) {
            return [];
        }

        $indexed = array_values($cards);
        usort($indexed, function (array $a, array $b): int {
            $startCmp = ((int) ($a['minutes_from_open'] ?? 0)) <=> ((int) ($b['minutes_from_open'] ?? 0));
            if ($startCmp !== 0) {
                return $startCmp;
            }

            return ((int) ($b['duration_minutes'] ?? 0)) <=> ((int) ($a['duration_minutes'] ?? 0));
        });

        /** @var list<int> $columnEnds end minute of the last card placed in each column */
        $columnEnds = [];

        foreach ($indexed as $i => $card) {
            $start = (int) ($card['minutes_from_open'] ?? 0);
            $end = $start + max(1, (int) ($card['duration_minutes'] ?? 30));
            $column = null;

            foreach ($columnEnds as $c => $lastEnd) {
                if ($lastEnd <= $start) {
                    $column = $c;
                    break;
                }
            }

            if ($column === null) {
                $column = count($columnEnds);
                $columnEnds[] = $end;
            } else {
                $columnEnds[$column] = $end;
            }

            $indexed[$i]['column_index'] = $column;
            $indexed[$i]['_pack_start'] = $start;
            $indexed[$i]['_pack_end'] = $end;
        }

        $n = count($indexed);
        $parent = range(0, $n - 1);
        $find = function (int $i) use (&$parent, &$find): int {
            if ($parent[$i] === $i) {
                return $i;
            }

            return $parent[$i] = $find($parent[$i]);
        };
        $union = function (int $a, int $b) use (&$parent, $find): void {
            $rootA = $find($a);
            $rootB = $find($b);
            if ($rootA !== $rootB) {
                $parent[$rootA] = $rootB;
            }
        };

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if (
                    $indexed[$i]['_pack_start'] < $indexed[$j]['_pack_end']
                    && $indexed[$j]['_pack_start'] < $indexed[$i]['_pack_end']
                ) {
                    $union($i, $j);
                }
            }
        }

        /** @var array<int, int> $clusterMax */
        $clusterMax = [];
        for ($i = 0; $i < $n; $i++) {
            $root = $find($i);
            $clusterMax[$root] = max(
                $clusterMax[$root] ?? 0,
                ((int) $indexed[$i]['column_index']) + 1,
            );
        }

        $minColumns = max(1, $minColumns);
        for ($i = 0; $i < $n; $i++) {
            $indexed[$i]['column_count'] = max($minColumns, $clusterMax[$find($i)] ?? 1);
            unset($indexed[$i]['_pack_start'], $indexed[$i]['_pack_end']);
        }

        return $indexed;
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @return list<array{date: string, day_label: string, count: int, cards: list<array<string, mixed>>}>
     */
    private function weekDays(Carbon $weekStart, array $cards, int $days = 7): array
    {
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $weekStart->copy()->addDays($i);
            $date = $day->toDateString();
            $dayCards = array_values(array_filter(
                $cards,
                fn (array $card): bool => str_starts_with((string) $card['starts_at'], $date),
            ));
            $out[] = [
                'date' => $date,
                'day_label' => $day->format('D j'),
                'count' => count($dayCards),
                'cards' => $dayCards,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @return list<array{days: list<array{date: string, day_label: string, in_month: bool, count: int, cards: list<array<string, mixed>>}>}>
     */
    private function monthWeeks(Carbon $monthStart, array $cards): array
    {
        $inMonth = $monthStart->format('Y-m');
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $gridEnd = $monthStart->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY)->startOfDay();
        $weeks = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $days = $this->weekDays($cursor, $cards, 7);
            foreach ($days as $i => $day) {
                $days[$i]['in_month'] = str_starts_with($day['date'], $inMonth);
                $days[$i]['day_label'] = Carbon::parse($day['date'])->format('j');
            }
            $weeks[] = ['days' => $days];
            $cursor->addWeek();
        }

        return $weeks;
    }
}
