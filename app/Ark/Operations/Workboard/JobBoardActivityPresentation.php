<?php

namespace App\Ark\Operations\Workboard;

/**
 * Compact Job Board list Activity strip: active marks only, attention-first, max four + overflow.
 */
final class JobBoardActivityPresentation
{
    public const MAX_VISIBLE = 4;

    /** @var list<string> */
    public const CATEGORY_ORDER = ['estimate', 'sms', 'email', 'phone', 'dvi', 'scheduled'];

    /**
     * @param  list<WorkboardCardActivityMark>  $marks
     * @return array{
     *     visible: list<WorkboardCardActivityMark>,
     *     overflow: list<WorkboardCardActivityMark>,
     *     overflowCount: int,
     *     overflowTooltip: string|null
     * }
     */
    public static function forList(array $marks): array
    {
        $active = array_values(array_filter(
            $marks,
            static fn (WorkboardCardActivityMark $mark): bool => $mark->active,
        ));

        usort($active, static function (WorkboardCardActivityMark $left, WorkboardCardActivityMark $right): int {
            $priority = self::statePriority($left->state) <=> self::statePriority($right->state);

            if ($priority !== 0) {
                return $priority;
            }

            return self::categoryRank($left->key) <=> self::categoryRank($right->key);
        });

        $visible = array_slice($active, 0, self::MAX_VISIBLE);
        $overflow = array_slice($active, self::MAX_VISIBLE);

        return [
            'visible' => $visible,
            'overflow' => $overflow,
            'overflowCount' => count($overflow),
            'overflowTooltip' => self::overflowTooltip($overflow),
        ];
    }

    public static function destinationFor(string $key, string $repairOrderUrl): ?string
    {
        $base = rtrim($repairOrderUrl, '#');

        return match ($key) {
            'estimate' => $base.'#builder',
            'sms', 'email', 'phone' => $base.'#comms',
            'dvi' => $base.'#inspect',
            default => null,
        };
    }

    private static function statePriority(WorkboardCardActivityState $state): int
    {
        return match ($state) {
            WorkboardCardActivityState::Attention => 0,
            WorkboardCardActivityState::Engaged => 1,
            WorkboardCardActivityState::Sent => 2,
            WorkboardCardActivityState::Ready => 3,
            WorkboardCardActivityState::None => 9,
        };
    }

    private static function categoryRank(string $key): int
    {
        $index = array_search($key, self::CATEGORY_ORDER, true);

        return $index === false ? 99 : $index;
    }

    /**
     * @param  list<WorkboardCardActivityMark>  $overflow
     */
    private static function overflowTooltip(array $overflow): ?string
    {
        if ($overflow === []) {
            return null;
        }

        $parts = array_map(
            static fn (WorkboardCardActivityMark $mark): string => $mark->tooltip,
            $overflow,
        );

        return '+'.count($overflow).' more: '.implode('; ', $parts);
    }
}
