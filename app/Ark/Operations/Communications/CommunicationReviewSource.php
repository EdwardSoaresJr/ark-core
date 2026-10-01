<?php

namespace App\Ark\Operations\Communications;

enum CommunicationReviewSource: string
{
    case AiAnalysis = 'ai_analysis';
    case Manual = 'manual';
}
