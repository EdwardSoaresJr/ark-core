<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\Today\AdvisorHomeCardSurface;

/**
 * Card "Next" / attention presentation. Prefer omission over a misleading instruction.
 *
 * - action: concrete workflow next step (shown with "Next")
 * - warning: actionable attention/critical exception (no "Next" prefix)
 * - watch: understated observation
 * - none: omit the band content
 */
final class JobBoardCardNextPresentation
{
    /**
     * Concrete instructions only — never generic attention copy.
     *
     * @var list<string>
     */
    private const ACTIONABLE = [
        'Call for pickup',
        'Collect balance',
        'Respond',
        'Follow up',
        'Send estimate',
        'Present recommendation',
        'Check parts',
        'Assign tech',
        'Diagnose',
        'Finish estimate',
        'Confirm vehicle',
    ];

    /**
     * @return array{kind: 'action'|'warning'|'watch'|'none', label: ?string}
     */
    public static function forSurface(AdvisorHomeCardSurface $surface): array
    {
        $next = trim((string) ($surface->nextMoveLabel ?? ''));

        if ($next !== '' && self::isActionable($next)) {
            return ['kind' => 'action', 'label' => $next];
        }

        if (in_array($surface->attention, ['attention', 'critical'], true)) {
            $warning = trim((string) ($surface->exceptionLabel ?? ''));
            if ($warning !== '' && ! self::isGenericAttentionCopy($warning)) {
                return ['kind' => 'warning', 'label' => $warning];
            }
        }

        if ($surface->attention === 'watch') {
            $watch = trim((string) ($surface->exceptionLabel ?? ''));
            if ($watch !== '') {
                return ['kind' => 'watch', 'label' => $watch === 'Watch' ? 'Watch' : $watch];
            }

            return ['kind' => 'watch', 'label' => 'Watch'];
        }

        return ['kind' => 'none', 'label' => null];
    }

    public static function isActionable(string $label): bool
    {
        $normalized = trim($label);

        foreach (self::ACTIONABLE as $allowed) {
            if (strcasecmp($normalized, $allowed) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function isGenericAttentionCopy(string $label): bool
    {
        $normalized = strtolower(trim($label));

        return in_array($normalized, [
            'needs attention',
            'attention',
            'critical',
            'watch',
            'review',
        ], true);
    }
}
