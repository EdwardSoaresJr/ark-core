<?php

namespace App\Ark\Operations\Today;

final readonly class TodayRecommendation
{
    /**
     * @param  list<string>  $whyReasons
     */
    public function __construct(
        public string $ruleKey,
        public int $rankScore,
        public string $title,
        public array $whyReasons,
        public TodayImpactKind $impactKind,
        public string $impactLabel,
        public string $suggestedAction,
        public int $repairOrderId,
        public string $repairOrderUrl,
        public ?string $textUrl,
        public ?string $callUrl,
        public string $customerName = 'Customer',
        public bool $canCloseLost = false,
    ) {}

    public function withCanCloseLost(bool $canCloseLost): self
    {
        return new self(
            ruleKey: $this->ruleKey,
            rankScore: $this->rankScore,
            title: $this->title,
            whyReasons: $this->whyReasons,
            impactKind: $this->impactKind,
            impactLabel: $this->impactLabel,
            suggestedAction: $this->suggestedAction,
            repairOrderId: $this->repairOrderId,
            repairOrderUrl: $this->repairOrderUrl,
            textUrl: $this->textUrl,
            callUrl: $this->callUrl,
            customerName: $this->customerName,
            canCloseLost: $canCloseLost,
        );
    }
}
