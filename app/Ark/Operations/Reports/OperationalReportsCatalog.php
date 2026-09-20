<?php

namespace App\Ark\Operations\Reports;

use App\Ark\Operations\ShopExcellence\OwnerWorkspaceAccess;
use App\Models\User;

/**
 * Tekmetric-style report picker — routes to existing ARK report surfaces.
 */
final readonly class OperationalReportsCatalog
{
    public function __construct(
        private bool $canAccessBookend,
    ) {}

    public static function forUser(?User $user): self
    {
        return new self(OwnerWorkspaceAccess::allows($user));
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     cards: list<array{key: string, title: string, hint: string, url: string}>
     * }>
     */
    public function sections(): array
    {
        $today = OperationalReportDateScope::shopDateString(OperationalReportDateScope::shopNow());

        return [
            [
                'key' => 'daily',
                'label' => 'Daily rhythm',
                'description' => 'Close the shop day before you leave.',
                'cards' => [
                    [
                        'key' => 'end-of-day',
                        'title' => 'End of Day',
                        'hint' => 'Posted sales, ELR, RO summary, and cash reconciliation.',
                        'url' => $this->canAccessBookend
                            ? route('operations.owner.day-review', ['date' => $today])
                            : route('operations.reports.end-of-day', ['date' => $today]),
                    ],
                ],
            ],
            [
                'key' => 'financial',
                'label' => 'Financial',
                'description' => 'Posted sales truth, payments, margin, and owner P&L.',
                'cards' => [
                    [
                        'key' => 'sales-payments',
                        'title' => 'Sales & Payments',
                        'hint' => 'Financial mix, payments reconciliation, and recent posts.',
                        'url' => $this->operationalUrl(OperationalReportTab::Financial),
                    ],
                    [
                        'key' => 'margin-health',
                        'title' => 'Margin Health',
                        'hint' => 'ELR, parts margin, ARO, and mix vs shop targets.',
                        'url' => $this->operationalUrl(OperationalReportTab::MarginHealth),
                    ],
                    [
                        'key' => 'owner-pl',
                        'title' => 'Owner P&L',
                        'hint' => 'Management estimate from posted sales and operating income.',
                        'url' => $this->operationalUrl(OperationalReportTab::OwnerPl),
                    ],
                ],
            ],
            [
                'key' => 'shop',
                'label' => 'Shop operations',
                'description' => 'Queue pressure, workflow truth, and production.',
                'cards' => [
                    [
                        'key' => 'operations-pulse',
                        'title' => 'Operations Pulse',
                        'hint' => 'Queue buckets, approval drag, labor liability, and conversion.',
                        'url' => $this->operationalUrl(OperationalReportTab::Operations),
                    ],
                    [
                        'key' => 'production',
                        'title' => 'Production',
                        'hint' => 'Live pressure, advisor throughput, and technician efficiency.',
                        'url' => $this->operationalUrl(OperationalReportTab::Production),
                    ],
                    [
                        'key' => 'technician-production-assist',
                        'title' => 'Technician production assist',
                        'hint' => 'Recognized vs pending flag, clock hours, and base compensation assist — not a paycheck.',
                        'url' => $this->canAccessBookend
                            ? route('operations.owner.technician-production.index')
                            : route('operations.reports.index'),
                    ],
                ],
            ],
        ];
    }

    private function operationalUrl(OperationalReportTab $tab): string
    {
        return route('operations.reports.operational', ['tab' => $tab->value]);
    }
}
