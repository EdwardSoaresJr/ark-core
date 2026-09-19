<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Str;

final class CommunicationsQuickReplyTemplates
{
    /**
     * @return list<array{key: string, label: string, body: string, color: string}>
     */
    public static function all(): array
    {
        $stored = ShopSettings::current()->quick_reply_templates;

        if (! is_array($stored)) {
            return self::defaults();
        }

        return self::normalize($stored);
    }

    /**
     * @return list<array{key: string, label: string, body: string, color: string}>
     */
    public static function defaults(): array
    {
        return [
            [
                'key' => 'estimate_received',
                'label' => 'Estimate received',
                'body' => 'Thanks for reaching out — we received your request and will follow up shortly with next steps.',
                'color' => CommunicationsAccentColor::NEUTRAL,
            ],
            [
                'key' => 'scheduling',
                'label' => 'Scheduling',
                'body' => 'We can get you on the schedule. What day works best for you to bring the vehicle in?',
                'color' => CommunicationsAccentColor::NEUTRAL,
            ],
            [
                'key' => 'running_behind',
                'label' => 'Running behind',
                'body' => 'Thanks for your patience — we are running a little behind today but will get back to you as soon as possible.',
                'color' => CommunicationsAccentColor::NEUTRAL,
            ],
            [
                'key' => 'financing',
                'label' => 'Financing',
                'body' => 'We offer several payment options at checkout. Let us know if you would like an estimate sent over to review.',
                'color' => CommunicationsAccentColor::NEUTRAL,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{key: string, label: string, body: string, color: string}>
     */
    public static function normalize(array $rows): array
    {
        $templates = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $body = trim((string) ($row['body'] ?? ''));

            if ($label === '' || $body === '') {
                continue;
            }

            $templates[] = [
                'key' => Str::slug($label) !== '' ? Str::slug($label) : 'reply',
                'label' => $label,
                'body' => $body,
                'color' => CommunicationsAccentColor::normalize($row['color'] ?? null),
            ];
        }

        return array_slice($templates, 0, 40);
    }
}
