<?php

namespace App\Ark\Dragon\ServiceAdvisor;

enum ServiceAdvisorMode: string
{
    case ServiceAdvisorRewrite = 'service_advisor_rewrite';
    case CleanUp = 'clean_up';
    case CustomerFriendly = 'customer_friendly';
    case Concise = 'concise';
    case StrongerExplanation = 'stronger_explanation';

    public function label(): string
    {
        return match ($this) {
            self::ServiceAdvisorRewrite => 'Service Advisor Rewrite',
            self::CleanUp => 'Clean Up',
            self::CustomerFriendly => 'Customer Friendly',
            self::Concise => 'Concise',
            self::StrongerExplanation => 'Stronger Explanation',
        };
    }

    public function instruction(): string
    {
        return match ($this) {
            self::ServiceAdvisorRewrite => 'Rewrite like a seasoned Demo Auto Repair service advisor: calm, direct, dense, professional. Fact-preserving. No chatbot cheer or school-report filler. Connect finding to recommendation only when both are documented.',
            self::CleanUp => 'Preserve wording closely. Fix spelling, grammar, and readability only.',
            self::CustomerFriendly => 'Translate technician shorthand into customer-understandable language without inventing facts.',
            self::Concise => 'Make the note shorter while preserving every documented finding and recommendation.',
            self::StrongerExplanation => 'Clarify why the documented finding leads to the documented recommendation. Do not add urgency, scare language, or new diagnosis.',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $m): string => $m->value, self::cases());
    }
}
