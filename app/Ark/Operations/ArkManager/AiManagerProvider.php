<?php

namespace App\Ark\Operations\ArkManager;

interface AiManagerProvider
{
    public function morningBrief(ArkManagerContext $context, string $recommendedFocus): ArkManagerMorningBrief;

    public function explainRecommendation(ArkManagerContext $context, array $recommendation): ArkManagerRecommendationExplanation;

    /**
     * @param  array<string, mixed>  $draftContext
     */
    public function draftCommunication(ArkManagerContext $context, array $draftContext): ArkManagerCommunicationDraft;
}
