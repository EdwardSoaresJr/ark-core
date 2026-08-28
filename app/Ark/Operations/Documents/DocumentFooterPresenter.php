<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;

final class DocumentFooterPresenter
{
    /**
     * Customer types that receive a dedicated terms block instead of merging into Important Information.
     *
     * @var list<string>
     */
    private const SEPARATE_TERMS_CUSTOMER_TYPES = [
        'fleet',
        'warranty',
        'repairpal',
        'dealer',
        'wholesale',
        'internal',
    ];

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{
     *     important_information: list<string>,
     *     customer_type_terms: array{heading: string, bullets: list<string>}|null,
     *     authorization: list<string>,
     *     approval: array{
     *         status: string,
     *         status_label: string,
     *         approved_by: string|null,
     *         approved_at_display: string|null,
     *         source_label: string|null,
     *         approval_type_label: string|null,
     *         detail: string|null,
     *         show_signature_lines: bool
     *     },
     *     total_label: string
     * }
     */
    /**
     * Presentation-only PDF summary when the full disclaimer list is too tall.
     *
     * @param  list<string>  $bullets
     * @return list<string>
     */
    public function pdfImportantInformationBullets(array $bullets): array
    {
        if (count($bullets) <= 4) {
            return $bullets;
        }

        return [
            'Findings and pricing reflect inspection today. Additional work may be discovered during repair or disassembly.',
            'Parts availability, labor, and pricing may change within the estimate validity period.',
            'Approved work is covered by shop warranty unless noted. Customer-supplied and used parts may carry no warranty.',
            'Further testing or recommendations may change the repair path and require separate customer authorization.',
        ];
    }

    public function present(array $snapshot): array
    {
        $documents = is_array($snapshot['documents'] ?? null) ? $snapshot['documents'] : [];
        $settings = is_array($snapshot['settings'] ?? null) ? $snapshot['settings'] : [];

        $importantInformation = $this->bulletsFromText((string) ($documents['global_disclaimer'] ?? ''));
        $importantInformation = [
            ...$importantInformation,
            ...$this->bulletsFromText((string) ($settings['recommendation_disclaimer'] ?? '')),
        ];

        $customerTypeLabel = filled($documents['customer_type'] ?? null)
            ? trim((string) $documents['customer_type'])
            : trim((string) data_get($snapshot, 'customer.customer_type', ''));
        $customerTypeKey = mb_strtolower($customerTypeLabel);
        $customerTypeDisclaimer = trim((string) ($documents['customer_type_disclaimer'] ?? ''));

        $customerTypeTerms = null;

        if ($customerTypeDisclaimer !== '') {
            if ($this->usesSeparateTermsSection($customerTypeKey)) {
                $customerTypeTerms = [
                    'heading' => $this->termsHeading($customerTypeLabel),
                    'bullets' => $this->bulletsFromText($customerTypeDisclaimer),
                ];
            } else {
                $importantInformation = [
                    ...$importantInformation,
                    ...$this->bulletsFromText($customerTypeDisclaimer),
                ];
            }
        }

        $authorizationLanguage = trim((string) ($documents['authorization_language'] ?? $settings['authorization_language'] ?? ''));

        return [
            'important_information' => $this->uniqueBullets($importantInformation),
            'customer_type_terms' => $customerTypeTerms,
            'authorization' => $this->paragraphsFromText($authorizationLanguage),
            'approval' => $this->approvalStatus($snapshot),
            'total_label' => $this->footerTotalLabel($snapshot),
        ];
    }

    /**
     * Customer-facing footer total label. Math stays in EstimateTotalsCalculator;
     * this only names what the authoritative total represents.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function footerTotalLabel(array $snapshot): string
    {
        $documentType = FinancialDocumentType::tryFrom((string) data_get($snapshot, 'document_type', 'estimate'))
            ?? FinancialDocumentType::Estimate;

        if ($documentType !== FinancialDocumentType::Estimate) {
            return 'Total';
        }

        $hasApprovedWork = collect($snapshot['concerns'] ?? [])
            ->filter(fn (mixed $concern): bool => is_array($concern))
            ->contains(fn (array $concern): bool => ($concern['disposition'] ?? '') === 'approved');

        return $hasApprovedWork ? 'Approved Total' : 'Total';
    }

    /**
     * @return list<string>
     */
    public function bulletsFromText(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        if (str_contains($text, "\n")) {
            return array_values(array_filter(array_map(
                static fn (string $line): string => trim($line),
                preg_split('/\R+/', $text) ?: [],
            )));
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $text) ?: [$text];

        return array_values(array_filter(array_map(
            static fn (string $sentence): string => trim($sentence),
            $sentences,
        )));
    }

    /**
     * @param  list<string>  $bullets
     * @return list<string>
     */
    private function uniqueBullets(array $bullets): array
    {
        $seen = [];

        return collect($bullets)
            ->map(fn (string $bullet): string => trim($bullet))
            ->filter(function (string $bullet) use (&$seen): bool {
                if ($bullet === '') {
                    return false;
                }

                $key = mb_strtolower($bullet);

                if (isset($seen[$key])) {
                    return false;
                }

                $seen[$key] = true;

                return true;
            })
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function paragraphsFromText(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        if (str_contains($text, "\n\n")) {
            return array_values(array_filter(array_map(
                static fn (string $paragraph): string => trim($paragraph),
                preg_split('/\n\s*\n/', $text) ?: [],
            )));
        }

        if (str_contains($text, "\n")) {
            return array_values(array_filter(array_map(
                static fn (string $line): string => trim($line),
                preg_split('/\R+/', $text) ?: [],
            )));
        }

        return [$text];
    }

    private function usesSeparateTermsSection(string $customerTypeKey): bool
    {
        return in_array($customerTypeKey, self::SEPARATE_TERMS_CUSTOMER_TYPES, true);
    }

    private function termsHeading(string $customerTypeLabel): string
    {
        return match (mb_strtolower(trim($customerTypeLabel))) {
            'repairpal' => 'Warranty Terms',
            'internal' => 'Internal Terms',
            default => mb_convert_case(trim($customerTypeLabel), MB_CASE_TITLE).' Terms',
        };
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{
     *     status: string,
     *     status_label: string,
     *     approved_by: string|null,
     *     approved_at_display: string|null,
     *     source_label: string|null,
     *     approval_type_label: string|null,
     *     detail: string|null,
     *     show_signature_lines: bool
     * }
     */
    private function approvalStatus(array $snapshot): array
    {
        $documentType = FinancialDocumentType::tryFrom((string) data_get($snapshot, 'document_type', 'estimate'))
            ?? FinancialDocumentType::Estimate;

        $events = collect(data_get($snapshot, 'staff.approval_events', []))
            ->filter(fn (mixed $event): bool => is_array($event))
            ->sortByDesc(fn (array $event): string => (string) ($event['approved_at'] ?? ''))
            ->values();

        $latestEvent = $events->first();

        $concerns = collect($snapshot['concerns'] ?? [])
            ->filter(fn (mixed $concern): bool => is_array($concern))
            ->filter(function (array $concern): bool {
                $disposition = RepairOrderConcernDisposition::fromStored((string) ($concern['disposition'] ?? ''));

                return $disposition?->visibleToCustomer() ?? false;
            });

        if ($latestEvent) {
            $approvedAmountCents = (int) ($latestEvent['approved_amount_cents'] ?? 0);
            $hasApprovedConcerns = $concerns->contains(
                fn (array $concern): bool => ($concern['disposition'] ?? '') === RepairOrderConcernDisposition::Approved->value,
            );
            $hasDeferredConcerns = $concerns->contains(
                fn (array $concern): bool => ($concern['disposition'] ?? '') === RepairOrderConcernDisposition::Deferred->value,
            );
            $hasDeclinedConcerns = $concerns->contains(
                fn (array $concern): bool => ($concern['disposition'] ?? '') === RepairOrderConcernDisposition::Declined->value,
            );

            $status = match (true) {
                $hasApprovedConcerns => 'approved',
                $hasDeclinedConcerns && ! $hasDeferredConcerns => 'declined',
                $hasDeclinedConcerns || $hasDeferredConcerns => 'deferred',
                $approvedAmountCents > 0 => 'approved',
                default => 'pending',
            };

            $statusLabel = match ($status) {
                'approved' => $documentType === FinancialDocumentType::Invoice ? 'Invoice Authorized' : 'Approved',
                'declined' => 'Declined',
                'deferred' => 'Deferred',
                default => 'Response recorded',
            };

            return [
                'status' => $status,
                'status_label' => $statusLabel,
                'approved_by' => filled($latestEvent['approved_by'] ?? null)
                    ? (string) $latestEvent['approved_by']
                    : 'Customer',
                'approved_at_display' => filled($latestEvent['approved_at_display'] ?? null)
                    ? (string) $latestEvent['approved_at_display']
                    : null,
                'source_label' => filled($latestEvent['source_label'] ?? null)
                    ? (string) $latestEvent['source_label']
                    : null,
                'approval_type_label' => filled($latestEvent['approval_type_label'] ?? null)
                    ? (string) $latestEvent['approval_type_label']
                    : null,
                'detail' => null,
                'show_signature_lines' => false,
            ];
        }

        if ($documentType === FinancialDocumentType::Invoice) {
            return [
                'status' => 'issued',
                'status_label' => 'Invoice Issued',
                'approved_by' => null,
                'approved_at_display' => data_get($snapshot, 'generated_at_display'),
                'source_label' => null,
                'approval_type_label' => null,
                'detail' => null,
                'show_signature_lines' => false,
            ];
        }

        $concerns = collect($snapshot['concerns'] ?? [])
            ->filter(fn (mixed $concern): bool => is_array($concern))
            ->filter(function (array $concern): bool {
                $disposition = RepairOrderConcernDisposition::fromStored((string) ($concern['disposition'] ?? ''));

                return $disposition?->visibleToCustomer() ?? false;
            });
        $hasRecommended = $concerns->contains(
            fn (array $concern): bool => ($concern['disposition'] ?? '') === RepairOrderConcernDisposition::Recommended->value,
        );

        if ($hasRecommended) {
            return [
                'status' => 'pending',
                'status_label' => 'Pending Approval',
                'approved_by' => null,
                'approved_at_display' => null,
                'source_label' => null,
                'approval_type_label' => null,
                'detail' => null,
                'show_signature_lines' => true,
            ];
        }

        $approvedConcerns = $concerns->where('disposition', RepairOrderConcernDisposition::Approved->value);

        if ($approvedConcerns->isNotEmpty()) {
            return [
                'status' => 'approved',
                'status_label' => 'Approved',
                'approved_by' => null,
                'approved_at_display' => null,
                'source_label' => null,
                'approval_type_label' => null,
                'detail' => null,
                'show_signature_lines' => false,
            ];
        }

        $deferredConcerns = $concerns->where('disposition', RepairOrderConcernDisposition::Deferred->value);
        $declinedConcerns = $concerns->where('disposition', RepairOrderConcernDisposition::Declined->value);

        if ($declinedConcerns->isNotEmpty() && $deferredConcerns->isEmpty()) {
            return [
                'status' => 'declined',
                'status_label' => 'Declined',
                'approved_by' => null,
                'approved_at_display' => null,
                'source_label' => null,
                'approval_type_label' => null,
                'detail' => null,
                'show_signature_lines' => false,
            ];
        }

        if ($deferredConcerns->isNotEmpty() || $declinedConcerns->isNotEmpty()) {
            return [
                'status' => 'deferred',
                'status_label' => $declinedConcerns->isNotEmpty() ? 'Response recorded' : 'Deferred',
                'approved_by' => null,
                'approved_at_display' => null,
                'source_label' => null,
                'approval_type_label' => null,
                'detail' => null,
                'show_signature_lines' => false,
            ];
        }

        return [
            'status' => 'pending',
            'status_label' => 'Pending Approval',
            'approved_by' => null,
            'approved_at_display' => null,
            'source_label' => null,
            'approval_type_label' => null,
            'detail' => null,
            'show_signature_lines' => true,
        ];
    }
}
