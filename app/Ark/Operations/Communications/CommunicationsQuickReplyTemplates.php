<?php

namespace App\Ark\Operations\Communications;

/**
 * Static operational reply snippets — advisors pick and edit; not AI auto-send.
 */
final class CommunicationsQuickReplyTemplates
{
    /**
     * @return list<array{key: string, label: string, body: string}>
     */
    public static function all(): array
    {
        return [
            [
                'key' => 'estimate_received',
                'label' => 'Estimate received',
                'body' => 'Thanks for reaching out — we received your request and will follow up shortly with next steps.',
            ],
            [
                'key' => 'scheduling',
                'label' => 'Scheduling',
                'body' => 'We can get you on the schedule. What day works best for you to bring the vehicle in?',
            ],
            [
                'key' => 'running_behind',
                'label' => 'Running behind',
                'body' => 'Thanks for your patience — we are running a little behind today but will get back to you as soon as possible.',
            ],
            [
                'key' => 'financing',
                'label' => 'Financing',
                'body' => 'We offer several payment options at checkout. Let us know if you would like an estimate sent over to review.',
            ],
        ];
    }
}
