<?php

namespace App\Ark\Dragon\Agent;

use App\Ark\Dragon\Agent\Contracts\DragonModelProvider;
use InvalidArgumentException;
use Throwable;

final class DragonAgentLoop
{
    public function __construct(
        private readonly DragonModelProvider $provider,
        private readonly DragonToolRegistry $registry,
        private readonly DragonEmployeeContext $employee,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @return array{
     *     reply: string,
     *     traces: list<array<string, mixed>>,
     *     prompt_tokens: int,
     *     completion_tokens: int,
     *     tool_calls: int,
     *     latency_ms: int,
     *     provider: string,
     *     model: string
     * }
     */
    public function run(string $userMessage, array $history = [], bool $sharedGlass = false): array
    {
        $started = microtime(true);
        $messages = [
            ['role' => 'system', 'content' => $this->employee->promptBlock($sharedGlass)],
        ];
        foreach ($history as $turn) {
            $messages[] = $turn;
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $tools = $this->registry->openaiTools();
        $traces = [];
        $promptTokens = 0;
        $completionTokens = 0;
        $toolCalls = 0;
        $maxRounds = max(1, (int) config('dragon.max_tool_rounds', 8));

        for ($round = 1; $round <= $maxRounds; $round++) {
            $turn = $this->provider->complete($messages, $tools);
            $promptTokens += $turn->promptTokens;
            $completionTokens += $turn->completionTokens;

            if (! $turn->wantsTools()) {
                $reply = trim((string) $turn->content);
                if ($reply === '') {
                    $reply = 'I looked, but I do not have a clean answer yet.';
                }

                return $this->finish($reply, $traces, $promptTokens, $completionTokens, $toolCalls, $started);
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => $turn->content,
                'tool_calls' => array_map(fn (array $call): array => [
                    'id' => $call['id'],
                    'type' => 'function',
                    'function' => [
                        'name' => $call['name'],
                        'arguments' => json_encode($call['arguments']),
                    ],
                ], $turn->toolCalls),
            ];

            foreach ($turn->toolCalls as $call) {
                $toolCalls++;
                $callStarted = microtime(true);
                $canonicalName = (string) $call['name'];
                try {
                    $tool = $this->registry->get($call['name']);
                    $canonicalName = $tool->name();
                    $observation = $tool->invoke($call['arguments']);
                } catch (InvalidArgumentException $e) {
                    $observation = ['ok' => false, 'error' => $e->getMessage()];
                } catch (Throwable) {
                    $observation = ['ok' => false, 'error' => 'Tool failed.'];
                }
                $categories = [];
                if (isset($observation['_ark_categories']) && is_array($observation['_ark_categories'])) {
                    $categories = array_values(array_map('strval', $observation['_ark_categories']));
                }
                unset($observation['_ark_categories']);
                $tracePayload = $observation;
                if (isset($observation['_trace']) && is_array($observation['_trace'])) {
                    $tracePayload = $observation['_trace'];
                    $tracePayload['tool'] = $canonicalName;
                    unset($observation['_trace']);
                }
                $observation = DragonObservationSanitizer::scrub($observation);
                $latency = (int) round((microtime(true) - $callStarted) * 1000);
                if (in_array($canonicalName, ['memory.recall', 'memory.propose'], true)) {
                    $tracePayload['latency_ms'] = $latency;
                    $summary = json_encode($tracePayload, JSON_UNESCAPED_SLASHES) ?: 'memory';
                } else {
                    $summary = DragonObservationSanitizer::summarize($observation);
                }
                $traces[] = [
                    'round' => $round,
                    'tool' => $canonicalName,
                    'arguments' => $call['arguments'],
                    'observation_summary' => $summary,
                    'data_categories' => $categories,
                    'latency_ms' => $latency,
                ];
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call['id'],
                    'content' => json_encode($observation, JSON_UNESCAPED_SLASHES),
                ];
            }
        }

        return $this->finish(
            'I hit the tool-round limit before I could finish. Ask me a narrower question.',
            $traces,
            $promptTokens,
            $completionTokens,
            $toolCalls,
            $started,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $traces
     * @return array<string, mixed>
     */
    private function finish(
        string $reply,
        array $traces,
        int $promptTokens,
        int $completionTokens,
        int $toolCalls,
        float $started,
    ): array {
        return [
            'reply' => $reply,
            'traces' => $traces,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'tool_calls' => $toolCalls,
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            'provider' => $this->provider->providerName(),
            'model' => $this->provider->modelName(),
        ];
    }
}
