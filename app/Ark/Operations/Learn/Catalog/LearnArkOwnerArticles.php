<?php

namespace App\Ark\Operations\Learn\Catalog;

use App\Ark\Operations\Learn\LearnArkSection;

final class LearnArkOwnerArticles
{
    /**
     * @return list<array{slug: string, title: string, summary: string, view: string}>
     */
    public static function all(): array
    {
        return [
            [
                'slug' => 'shop-margins-five-steps',
                'title' => 'Five steps to healthier margins',
                'summary' => 'Labor rate, parts matrix, inspections, ELR, and workflow.',
                'view' => 'operations.learn.owner.shop-margins-five-steps',
            ],
            [
                'slug' => 'daily-kpis',
                'title' => 'KPIs to review daily',
                'summary' => 'Gross margin, ARO, ELR, parts margin, and tech efficiency.',
                'view' => 'operations.learn.owner.daily-kpis',
            ],
            [
                'slug' => 'daily-rhythm',
                'title' => 'Day Review + daily reports',
                'summary' => 'Know why you are profitable, not just busy.',
                'view' => 'operations.learn.owner.daily-rhythm',
            ],
            [
                'slug' => 'bookend-walkthrough',
                'title' => 'Using Day Review end-of-day',
                'summary' => 'Digest lines, tomorrow queue pressure, weekly follow-up.',
                'view' => 'operations.learn.owner.bookend-walkthrough',
            ],
            [
                'slug' => 'ark-reports-guide',
                'title' => 'What ARK reports mean',
                'summary' => 'Sales Posted, Cash Collected, margin health, production, Financial tab.',
                'view' => 'operations.learn.owner.ark-reports-guide',
            ],
            [
                'slug' => 'payments-reconciliation',
                'title' => 'Payments reconciliation',
                'summary' => 'Bridge Cash Collected to Sales Posted — buckets, drill-down, daily habit.',
                'view' => 'operations.learn.owner.payments-reconciliation',
            ],
            [
                'slug' => 'weekly-owner-review',
                'title' => 'Weekly owner review',
                'summary' => 'Friday checklist — KPIs, margin health, next week focus.',
                'view' => 'operations.learn.owner.weekly-owner-review',
            ],
            [
                'slug' => 'quarterly-target-review',
                'title' => 'Quarterly target review',
                'summary' => 'Refresh owner targets so Margin Health stays honest.',
                'view' => 'operations.learn.owner.quarterly-target-review',
            ],
            [
                'slug' => 'parts-matrix-tune',
                'title' => 'Tuning the parts matrix',
                'summary' => 'Closed-data simulation before changing matrix rows.',
                'view' => 'operations.learn.owner.parts-matrix-tune',
            ],
            [
                'slug' => 'communications-setup',
                'title' => 'Communications setup',
                'summary' => 'Twilio, webhooks, ring group, accountability gate, escalation, Reverb.',
                'view' => 'operations.learn.owner.communications-setup',
            ],
        ];
    }

    public static function roleKey(): string
    {
        return LearnArkSection::OWNER;
    }
}
