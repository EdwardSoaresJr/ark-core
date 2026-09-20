<?php

namespace App\Ark\Dragon\Agent;

use App\Ark\Dragon\DragonWorkProjection;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Station\StationDeviceToken;
use App\Models\User;
use Illuminate\Support\Str;

final class ChatDragonAgentAction
{
    public function __construct(
        private readonly DragonAgentLoop $loop,
        private readonly DragonWorkProjection $work,
        private readonly HandleDragonMemoryIntent $memoryIntent,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, string $message, ?string $conversationUuid = null, ?string $situation = null): array
    {
        $conversation = $this->conversation($user, $conversationUuid);
        $workstation = Workstation::query()
            ->where('current_operator_user_id', $user->id)
            ->first();
        $this->bindMemoryContext(new DragonMemoryContext(
            $user,
            $workstation,
            $conversation,
            $user->name,
            $situation !== null && str_contains($situation, 'ark_desk') ? 'ark_desk' : 'staff',
        ));

        return $this->complete($conversation, $message, sharedGlass: false, situation: $situation);
    }

    /**
     * Read-only hosted chat for the shared Shop Glass. Station token only — not a staff PAT.
     *
     * @return array<string, mixed>
     */
    public function handleForStation(StationDeviceToken $token, string $message, ?string $conversationUuid = null): array
    {
        $conversation = $this->stationConversation($token, $conversationUuid);
        $workstationId = is_array($token->glass_config) ? ($token->glass_config['workstation_id'] ?? null) : null;
        $workstation = is_numeric($workstationId)
            ? Workstation::query()->find((int) $workstationId)
            : null;
        $this->bindMemoryContext(new DragonMemoryContext(
            null,
            $workstation,
            $conversation,
            $token->auditLabel(),
            'station',
        ));

        return $this->complete($conversation, $message, sharedGlass: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function complete(DragonAgentConversation $conversation, string $message, bool $sharedGlass = false, ?string $situation = null): array
    {
        $hadTurns = $conversation->messages()->exists();
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $message,
        ]);

        $memory = $this->memoryIntent->handle($message, app(DragonMemoryContext::class));
        if ($memory !== null) {
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $memory['reply'],
            ]);

            return [
                'conversation_id' => $conversation->uuid,
                'reply' => $memory['reply'],
                'source' => 'memory',
                'traces' => [],
                'provider' => 'ark',
                'model' => null,
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'latency_ms' => 0,
            ];
        }

        $fast = $hadTurns ? null : $this->fastFact($message);
        if ($fast !== null) {
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $fast,
            ]);
            $conversation->usages()->create([
                'provider' => 'fast_fact',
                'model' => null,
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'tool_calls' => 0,
                'latency_ms' => 0,
                'estimated_cost_cents' => 0,
            ]);

            return [
                'conversation_id' => $conversation->uuid,
                'reply' => $fast,
                'source' => 'fast_fact',
                'traces' => [],
                'provider' => 'fast_fact',
                'model' => null,
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'latency_ms' => 0,
            ];
        }

        $history = $conversation->messages()
            ->orderBy('id')
            ->get()
            ->slice(0, -1)
            ->take(-24)
            ->map(fn (DragonAgentMessage $row): array => [
                'role' => $row->role,
                'content' => $row->content,
            ])
            ->values()
            ->all();

        $result = $this->loop->run(
            $situation !== null && $situation !== '' ? $situation."\n\n".$message : $message,
            $history,
            $sharedGlass,
        );

        $conversation->forceFill([
            'provider' => $result['provider'],
            'model' => $result['model'],
        ])->save();

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $result['reply'],
        ]);

        foreach ($result['traces'] as $trace) {
            $conversation->traces()->create([
                'round' => $trace['round'],
                'tool' => $trace['tool'],
                'arguments' => $trace['arguments'],
                'observation_summary' => $trace['observation_summary'],
                'data_categories' => $trace['data_categories'] ?? [],
                'latency_ms' => $trace['latency_ms'],
            ]);
        }

        $conversation->usages()->create([
            'provider' => $result['provider'],
            'model' => $result['model'],
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
            'tool_calls' => $result['tool_calls'],
            'latency_ms' => $result['latency_ms'],
            'estimated_cost_cents' => $this->estimateCostCents(
                (string) $result['provider'],
                (int) $result['prompt_tokens'],
                (int) $result['completion_tokens'],
            ),
        ]);

        return [
            'conversation_id' => $conversation->uuid,
            'reply' => $result['reply'],
            'source' => 'agent',
            'traces' => $result['traces'],
            'provider' => $result['provider'],
            'model' => $result['model'],
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
            'latency_ms' => $result['latency_ms'],
        ];
    }

    private function fastFact(string $message): ?string
    {
        $text = mb_strtolower($message);
        if (preg_match('/\b(how many|count|number of).*(open|active).*(ro|r\.?o\.?|repair order)/i', $text)
            || str_contains($text, 'how many repair orders are open')) {
            $summary = $this->work->summaryOnly();

            return 'There are '.(int) ($summary['open_ro_count'] ?? 0).' open repair orders.';
        }

        if (preg_match('/\b(is )?(ark|the shop system) (connected|up|online)\b/i', $text)) {
            return 'Yes. You are talking to Dragon inside ARK.';
        }

        return null;
    }

    private function bindMemoryContext(DragonMemoryContext $context): void
    {
        app()->instance(DragonMemoryContext::class, $context);
    }

    private function conversation(User $user, ?string $uuid): DragonAgentConversation
    {
        if (is_string($uuid) && $uuid !== '') {
            $existing = DragonAgentConversation::query()
                ->where('uuid', $uuid)
                ->where('user_id', $user->id)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        return DragonAgentConversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'station_device_token_id' => null,
            'provider' => (string) config('dragon.provider'),
            'model' => null,
        ]);
    }

    private function stationConversation(StationDeviceToken $token, ?string $uuid): DragonAgentConversation
    {
        if (is_string($uuid) && $uuid !== '') {
            $existing = DragonAgentConversation::query()
                ->where('uuid', $uuid)
                ->where('station_device_token_id', $token->id)
                ->whereNull('user_id')
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        $latest = DragonAgentConversation::query()
            ->where('station_device_token_id', $token->id)
            ->whereNull('user_id')
            ->orderByDesc('id')
            ->first();
        if ($latest) {
            return $latest;
        }

        return DragonAgentConversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => null,
            'station_device_token_id' => $token->id,
            'provider' => (string) config('dragon.provider'),
            'model' => null,
        ]);
    }

    private function estimateCostCents(string $provider, int $prompt, int $completion): ?int
    {
        if ($provider !== 'openai') {
            return 0;
        }

        $usd = ($prompt / 1_000_000) * 2.50 + ($completion / 1_000_000) * 10.0;

        return (int) max(0, round($usd * 100));
    }
}
