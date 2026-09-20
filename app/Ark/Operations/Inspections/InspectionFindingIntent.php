<?php

namespace App\Ark\Operations\Inspections;

enum InspectionFindingIntent: string
{
    case Safety = 'safety';
    case Maintenance = 'maintenance';
    case Diagnostic = 'diagnostic';
    case Verification = 'verification';
    case Observation = 'observation';

    public function label(): string
    {
        return match ($this) {
            self::Safety => 'Safety',
            self::Maintenance => 'Maintenance',
            self::Diagnostic => 'Diagnostic',
            self::Verification => 'Verification',
            self::Observation => 'Observation',
        };
    }

    public function helpText(): string
    {
        return match ($this) {
            self::Safety => 'Condition affects safe operation or should not wait.',
            self::Maintenance => 'Wear or interval item observed during inspection.',
            self::Diagnostic => 'Finding needs further testing before recommending repair.',
            self::Verification => 'Post-repair or quality check result.',
            self::Observation => 'Document-only context — installed equipment, cosmetic note, customer-supplied hardware.',
        };
    }

    public function defaultObservedState(): InspectionObservedState
    {
        return match ($this) {
            self::Safety => InspectionObservedState::Fail,
            self::Maintenance => InspectionObservedState::Measure,
            self::Diagnostic => InspectionObservedState::Measure,
            self::Verification => InspectionObservedState::Pass,
            self::Observation => InspectionObservedState::Pass,
        };
    }

    public function notesPrefix(): string
    {
        return '['.$this->label().']';
    }

    public static function tryFromNotes(?string $notes): ?self
    {
        if (! is_string($notes) || $notes === '') {
            return null;
        }

        foreach (self::cases() as $intent) {
            if (str_starts_with($notes, $intent->notesPrefix())) {
                return $intent;
            }
        }

        return null;
    }

    public static function stripNotesPrefix(?string $notes): ?string
    {
        if (! is_string($notes) || $notes === '') {
            return null;
        }

        $intent = self::tryFromNotes($notes);

        if ($intent === null) {
            return $notes;
        }

        $stripped = ltrim(substr($notes, strlen($intent->notesPrefix())));

        return $stripped === '' ? null : $stripped;
    }

    /**
     * @return list<InspectionFindingIntent>
     */
    public static function ordered(): array
    {
        return [
            self::Safety,
            self::Maintenance,
            self::Diagnostic,
            self::Verification,
            self::Observation,
        ];
    }
}
