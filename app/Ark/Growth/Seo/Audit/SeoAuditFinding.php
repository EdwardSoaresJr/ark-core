<?php

namespace App\Ark\Growth\Seo\Audit;

final class SeoAuditFinding
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $severity,
        public readonly string $category,
        public readonly string $message,
        public readonly string $recommendation,
        public readonly ?string $url = null,
        public readonly ?string $evidence = null,
        public readonly SeoAuditAuthoritySource $authoritySource = SeoAuditAuthoritySource::Registry,
        public readonly SeoAuditChannel $channel = SeoAuditChannel::Runtime,
        public readonly bool $passed = false,
        public readonly ?string $verifiedAt = null,
    ) {}

    public function isFailure(): bool
    {
        return ! $this->passed;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'analyzer' => $this->id,
            'title' => $this->title,
            'severity' => $this->severity,
            'category' => $this->category,
            'message' => $this->message,
            'recommendation' => $this->recommendation,
            'url' => $this->url,
            'path' => $this->url,
            'evidence' => $this->evidence,
            'evidence_list' => $this->evidence !== null ? [$this->evidence] : [],
            'authority_source' => $this->authoritySource->value,
            'authority_source_label' => $this->authoritySource->label(),
            'heals_on_deploy' => $this->authoritySource->healsOnDeploy(),
            'channel' => $this->channel->value,
            'channel_label' => $this->channel->label(),
            'passed' => $this->passed,
            'verified_at' => $this->verifiedAt,
        ];
    }
}
