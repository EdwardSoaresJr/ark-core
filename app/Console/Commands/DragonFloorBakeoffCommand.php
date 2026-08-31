<?php

namespace App\Console\Commands;

use App\Ark\Dragon\Agent\Bakeoff\DragonFloorBakeoffCatalog;
use App\Ark\Dragon\Agent\ChatDragonAgentAction;
use App\Ark\Dragon\Agent\Contracts\DragonModelProvider;
use App\Ark\Dragon\Agent\DragonProviderUnavailable;
use App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorFactPreservationCheck;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

final class DragonFloorBakeoffCommand extends Command
{
    protected $signature = 'dragon:floor-bakeoff
        {--operator= : Staff email who owns the hosted conversation}
        {--hosted : Run ARK-hosted Dragon (OpenAI)}
        {--arkai-url= : Optional arkai chat URL; POST {"message":"..."} }
        {--dry-run : Print the frozen task set only}
        {--only= : Comma-separated task ids}';

    protected $description = 'Floor-certify ARK-hosted Dragon vs arkai/Qwen on the frozen Demo Auto Repair task set. Does not cut over.';

    public function handle(
        ChatDragonAgentAction $chat,
        ServiceAdvisorFactPreservationCheck $preservation,
    ): int {
        $tasks = $this->selectedTasks();

        if ($this->option('dry-run')) {
            $this->info('Dragon floor bake-off '.DragonFloorBakeoffCatalog::VERSION.' — '.count($tasks).' tasks');
            $this->line(DragonFloorBakeoffCatalog::ACCEPTANCE);
            $this->newLine();
            foreach ($tasks as $task) {
                $this->line($task['id'].'  ['.$task['family'].']  '.$task['prompt']);
            }

            return self::SUCCESS;
        }

        $runHosted = (bool) $this->option('hosted');
        $arkaiUrl = trim((string) $this->option('arkai-url'));

        if (! $runHosted && $arkaiUrl === '') {
            $this->error('Pass --hosted and/or --arkai-url, or --dry-run to inspect tasks.');

            return self::FAILURE;
        }

        $operator = null;
        if ($runHosted) {
            $email = trim((string) $this->option('operator'));
            if ($email === '') {
                $this->error('--operator=staff@shop is required for --hosted.');

                return self::FAILURE;
            }
            $operator = User::query()->where('email', $email)->first();
            if ($operator === null) {
                $this->error("No staff user for {$email}.");

                return self::FAILURE;
            }
        }

        $rows = [];
        foreach ($tasks as $task) {
            $this->comment($task['id'].' …');
            $row = [
                'id' => $task['id'],
                'family' => $task['family'],
                'prompt' => $task['prompt'],
                'expected_tools' => $task['expected_tools'],
                'notes' => $task['notes'],
                'hosted' => null,
                'arkai' => null,
                'human' => [
                    'hosted_feels_like_employee' => null,
                    'arkai_feels_like_employee' => null,
                    'winner' => null,
                    'comment' => null,
                    'scores' => array_fill_keys(DragonFloorBakeoffCatalog::SCORE_AXES, [
                        'hosted' => null,
                        'arkai' => null,
                    ]),
                ],
            ];

            if ($runHosted && $operator instanceof User) {
                $row['hosted'] = $this->runHosted($chat, $preservation, $operator, $task);
            }

            if ($arkaiUrl !== '') {
                $row['arkai'] = $this->runArkai($arkaiUrl, $preservation, $task);
            }

            $rows[] = $row;
        }

        $payload = [
            'catalog' => DragonFloorBakeoffCatalog::VERSION,
            'acceptance' => DragonFloorBakeoffCatalog::ACCEPTANCE,
            'cutover' => 'Do not cut over from architecture. Cut over only if hosted Dragon feels materially more like a competent employee.',
            'arkai' => 'Leave arkai running. Hybrid until the floor says otherwise.',
            'ran_at' => now()->toIso8601String(),
            'hosted_provider' => config('dragon.provider'),
            'hosted_model' => app(DragonModelProvider::class)->modelName(),
            'tasks' => $rows,
        ];

        $dir = storage_path('app/private/dragon-bakeoff');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/'.now()->format('Ymd-His').'-floor-bakeoff.json';
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info('Wrote '.$path);
        $this->newLine();
        $this->line('Score both systems 1–5 on: '.implode(', ', DragonFloorBakeoffCatalog::SCORE_AXES));
        $this->line('Then answer: '.DragonFloorBakeoffCatalog::ACCEPTANCE);
        $this->warn('If it is only marginally better, keep hybrid. Do not shut arkai down.');

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function selectedTasks(): array
    {
        $tasks = DragonFloorBakeoffCatalog::tasks();
        $only = trim((string) $this->option('only'));
        if ($only === '') {
            return $tasks;
        }

        $ids = array_filter(array_map('trim', explode(',', $only)));

        return array_values(array_filter(
            $tasks,
            fn (array $task): bool => in_array($task['id'], $ids, true),
        ));
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    private function runHosted(
        ChatDragonAgentAction $chat,
        ServiceAdvisorFactPreservationCheck $preservation,
        User $operator,
        array $task,
    ): array {
        $started = microtime(true);
        try {
            $result = $chat->handle($operator, (string) $task['prompt']);
        } catch (DragonProviderUnavailable $e) {
            return [
                'ok' => false,
                'error' => 'provider_unavailable',
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => 'hosted_failed',
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        }

        $reply = (string) ($result['reply'] ?? '');
        $tools = array_values(array_unique(array_map(
            fn (array $trace): string => (string) ($trace['tool'] ?? ''),
            $result['traces'] ?? [],
        )));

        return [
            'ok' => true,
            'reply' => $reply,
            'source' => $result['source'] ?? null,
            'provider' => $result['provider'] ?? null,
            'model' => $result['model'] ?? null,
            'tools' => $tools,
            'prompt_tokens' => $result['prompt_tokens'] ?? 0,
            'completion_tokens' => $result['completion_tokens'] ?? 0,
            'latency_ms' => $result['latency_ms'] ?? (int) round((microtime(true) - $started) * 1000),
            'preservation' => $this->preservation($preservation, $task, $reply),
        ];
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    private function runArkai(
        string $url,
        ServiceAdvisorFactPreservationCheck $preservation,
        array $task,
    ): array {
        $started = microtime(true);
        try {
            $response = Http::timeout(90)->acceptJson()->post($url, [
                'message' => $task['prompt'],
            ]);
        } catch (Throwable) {
            return [
                'ok' => false,
                'error' => 'arkai_unreachable',
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'note' => 'Paste arkai replies into the JSON human block if the HTTP shape does not match.',
            ];
        }

        $json = $response->json();
        $reply = is_array($json)
            ? (string) ($json['reply'] ?? $json['response'] ?? $json['text'] ?? $json['content'] ?? '')
            : (string) $response->body();

        return [
            'ok' => $response->successful(),
            'http' => $response->status(),
            'reply' => $reply,
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            'preservation' => $this->preservation($preservation, $task, $reply),
        ];
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array{ok: bool, reason: ?string}|null
     */
    private function preservation(
        ServiceAdvisorFactPreservationCheck $preservation,
        array $task,
        string $reply,
    ): ?array {
        $source = $task['preserve_source'] ?? null;
        if (! is_string($source) || trim($source) === '' || trim($reply) === '') {
            return null;
        }

        return $preservation->check($source, $reply);
    }
}
