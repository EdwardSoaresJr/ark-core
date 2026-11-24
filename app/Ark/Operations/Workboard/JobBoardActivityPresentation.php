<?php

namespace App\Ark\Operations\Workboard;

/**
 * Compact Job Board list Activity strip: active marks only, fixed category order, no overflow.
 */
final class JobBoardActivityPresentation
{
    /** @var list<string> */
    public const CATEGORY_ORDER = ['estimate', 'sms', 'email', 'phone', 'dvi', 'scheduled'];

    /**
     * @param  list<WorkboardCardActivityMark>  $marks
     * @return array{visible: list<WorkboardCardActivityMark>}
     */
    public static function forList(array $marks): array
    {
        $active = array_values(array_filter(
            $marks,
            static fn (WorkboardCardActivityMark $mark): bool => $mark->active,
        ));

        usort($active, static function (WorkboardCardActivityMark $left, WorkboardCardActivityMark $right): int {
            return self::categoryRank($left->key) <=> self::categoryRank($right->key);
        });

        return [
            'visible' => $active,
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

    private static function categoryRank(string $key): int
    {
        $index = array_search($key, self::CATEGORY_ORDER, true);

        return $index === false ? 99 : $index;
    }
}
