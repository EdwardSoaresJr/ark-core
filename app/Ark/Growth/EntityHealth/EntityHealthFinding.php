<?php

namespace App\Ark\Growth\EntityHealth;

final class EntityHealthFinding
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $canonicalLabel,
        public readonly string $canonicalValue,
        public readonly string $projectionLabel,
        public readonly string $projectionValue,
        public readonly string $recommendedAction,
        public readonly string $notebookLine,
        public readonly string $severity = 'warning',
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'canonical_label' => $this->canonicalLabel,
            'canonical_value' => $this->canonicalValue,
            'projection_label' => $this->projectionLabel,
            'projection_value' => $this->projectionValue,
            'recommended_action' => $this->recommendedAction,
            'notebook_line' => $this->notebookLine,
            'severity' => $this->severity,
        ];
    }
}
