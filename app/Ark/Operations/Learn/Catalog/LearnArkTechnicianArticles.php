<?php

namespace App\Ark\Operations\Learn\Catalog;

use App\Ark\Runtime\Authorization\ArkRole;

final class LearnArkTechnicianArticles
{
    /**
     * @return list<array{slug: string, title: string, summary: string, view: string}>
     */
    public static function all(): array
    {
        return [
            [
                'slug' => 'getting-started',
                'title' => 'Technician basics',
                'summary' => 'Workboard, assigned jobs, and reading the RO.',
                'view' => 'operations.learn.technician.getting-started',
            ],
            [
                'slug' => 'reading-estimates',
                'title' => 'Reading approved work',
                'summary' => 'How repair actions group labor, parts, and notes.',
                'view' => 'operations.learn.technician.reading-estimates',
            ],
            [
                'slug' => 'ro-status',
                'title' => 'RO status for techs',
                'summary' => 'What each lane means and when work should move.',
                'view' => 'operations.learn.technician.ro-status',
            ],
            [
                'slug' => 'writing-findings',
                'title' => 'Documenting findings',
                'summary' => 'Customer states vs verified findings; advisor handoff quality.',
                'view' => 'operations.learn.technician.writing-findings',
            ],
            [
                'slug' => 'multi-point-inspection',
                'title' => 'Multi-point inspection discipline',
                'summary' => 'Complete findings before the advisor presents; photos and MMS.',
                'view' => 'operations.learn.technician.multi-point-inspection',
            ],
            [
                'slug' => 'worksheet-collaboration',
                'title' => 'Live worksheet collaboration',
                'summary' => 'Session locks, banners, and when not to fight another tab.',
                'view' => 'operations.learn.technician.worksheet-collaboration',
            ],
            [
                'slug' => 'tech-production-sheet',
                'title' => 'Tech sheet and assignment',
                'summary' => 'Assigned tech, tech PDF, bay status from the floor.',
                'view' => 'operations.learn.technician.tech-production-sheet',
            ],
            [
                'slug' => 'ark-mobile-field-work',
                'title' => 'ARK Mobile field work',
                'summary' => 'My Work rhythm, next action, and assigned RO production.',
                'view' => 'operations.learn.technician.ark-mobile-field-work',
            ],
            [
                'slug' => 'ark-mobile-concern-workspace',
                'title' => 'Concern workspace on mobile',
                'summary' => 'Findings, photos, notes, handoff, and complete scope beside the vehicle.',
                'view' => 'operations.learn.technician.ark-mobile-concern-workspace',
            ],
        ];
    }

    public static function roleKey(): string
    {
        return ArkRole::Technician->value;
    }
}
