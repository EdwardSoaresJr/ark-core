<?php

namespace App\Ark\Dragon\Agent;

use InvalidArgumentException;

final class HandleDragonMemoryIntent
{
    public function __construct(
        private readonly StoreDragonMemory $store,
        private readonly ForgetDragonMemory $forget,
        private readonly RecallDragonMemory $recall,
    ) {}

    /**
     * @return array{reply: string, persisted: bool}|null
     */
    public function handle(string $message, DragonMemoryContext $context): ?array
    {
        $pending = $context->conversation?->pending_memory;
        if (is_array($pending) && $this->isConfirm($message)) {
            return $this->commitPending($context, $pending, $message);
        }

        if ($this->isForget($message)) {
            return $this->forgetIntent($message, $context);
        }

        if ($this->isCorrect($message)) {
            return $this->correctIntent($message, $context);
        }

        if ($this->isRemember($message)) {
            return $this->rememberIntent($message, $context);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $pending
     * @return array{reply: string, persisted: bool}
     */
    private function commitPending(DragonMemoryContext $context, array $pending, string $message): array
    {
        $scope = (string) ($pending['scope_type'] ?? 'company');
        if (preg_match('/\b(this station|this location|this workstation)\b/i', $message)) {
            $scope = 'workstation';
        } elseif (preg_match('/\b(company|whole company|all locations)\b/i', $message)) {
            $scope = 'company';
        }

        try {
            $memory = $this->store->store(
                $context,
                (string) ($pending['fact'] ?? ''),
                $scope,
                (string) ($pending['category'] ?? 'standard'),
                isset($pending['supersedes_id']) ? (int) $pending['supersedes_id'] : null,
            );
        } catch (InvalidArgumentException $e) {
            $this->clearPending($context);

            return ['reply' => $e->getMessage(), 'persisted' => false];
        }

        $this->clearPending($context);

        return [
            'reply' => $this->storedReply($memory),
            'persisted' => true,
        ];
    }

    /**
     * @return array{reply: string, persisted: bool}
     */
    private function rememberIntent(string $message, DragonMemoryContext $context): array
    {
        $fact = $this->extractRememberedFact($message);
        if ($fact === null || $fact === '') {
            return ['reply' => 'What should I remember? Say the durable standard in one sentence.', 'persisted' => false];
        }

        $scope = $this->detectScope($message, $context, $fact);
        if ($scope === 'ask') {
            $this->setPending($context, [
                'fact' => $fact,
                'scope_type' => 'company',
                'category' => 'standard',
                'needs_scope' => true,
            ]);

            return [
                'reply' => 'Should I remember that for the whole company, or just this station?',
                'persisted' => false,
            ];
        }

        if ($scope === 'ask_confirm') {
            $this->setPending($context, [
                'fact' => $fact,
                'scope_type' => 'company',
                'category' => 'standard',
            ]);

            return [
                'reply' => 'Should I remember that as a company-wide shop standard?',
                'persisted' => false,
            ];
        }

        try {
            $memory = $this->store->store($context, $fact, $scope, $scope === 'user' ? 'preference' : 'standard');
        } catch (InvalidArgumentException $e) {
            return ['reply' => $e->getMessage(), 'persisted' => false];
        }

        $this->clearPending($context);

        return ['reply' => $this->storedReply($memory), 'persisted' => true];
    }

    /**
     * @return array{reply: string, persisted: bool}
     */
    private function forgetIntent(string $message, DragonMemoryContext $context): array
    {
        $needle = trim((string) preg_replace('/^.*\bforget\b(?:\s+the\s+rule\s+about|\s+that|\s+the)?\s*/i', '', $message));
        $matches = $this->recall->facts($needle !== '' ? $needle : $message, $context);
        if (count($matches) === 0) {
            return ['reply' => 'I do not have an active memory matching that.', 'persisted' => false];
        }
        if (count($matches) > 1) {
            $list = collect($matches)->take(5)->map(fn (array $row): string => '• '.$row['value'])->implode("\n");

            return ['reply' => "Which one should I forget?\n".$list, 'persisted' => false];
        }

        $row = DragonAgentMemory::query()->find($matches[0]['id']);
        if ($row === null) {
            return ['reply' => 'I do not have an active memory matching that.', 'persisted' => false];
        }
        $this->forget->forget($row);

        return ['reply' => 'Forgotten. I will not use that as a durable standard anymore.', 'persisted' => false];
    }

    /**
     * @return array{reply: string, persisted: bool}
     */
    private function correctIntent(string $message, DragonMemoryContext $context): array
    {
        $newFact = $this->extractCorrection($message);
        if ($newFact === null) {
            return ['reply' => 'What should the updated standard be?', 'persisted' => false];
        }

        $needle = preg_replace('/\b(change that|from now on|correct that|update that)\b/i', '', $message) ?? $message;
        $matches = preg_match('/change that/i', $message)
            ? $this->recall->facts('', $context)
            : $this->recall->facts($needle, $context);
        if (count($matches) === 0) {
            try {
                $memory = $this->store->store($context, $newFact, 'company');
            } catch (InvalidArgumentException $e) {
                return ['reply' => $e->getMessage(), 'persisted' => false];
            }

            return ['reply' => $this->storedReply($memory), 'persisted' => true];
        }
        if (count($matches) > 1) {
            $list = collect($matches)->take(5)->map(fn (array $row): string => '• '.$row['value'])->implode("\n");

            return ['reply' => "Which standard should I replace?\n".$list, 'persisted' => false];
        }

        try {
            $memory = $this->store->store(
                $context,
                $newFact,
                (string) $matches[0]['scope'],
                (string) ($matches[0]['category'] ?? 'standard'),
                (int) $matches[0]['id'],
            );
        } catch (InvalidArgumentException $e) {
            return ['reply' => $e->getMessage(), 'persisted' => false];
        }

        return ['reply' => $this->storedReply($memory), 'persisted' => true];
    }

    private function detectScope(string $message, DragonMemoryContext $context, string $fact): string
    {
        $text = mb_strtolower($message.' '.$fact);
        if (preg_match('/\b(i prefer|prefer the|when i ask)\b/', $text)) {
            return 'user';
        }
        if (preg_match('/\b(all locations|whole company|company-wide|every station)\b/', $text)) {
            return 'company';
        }
        if (preg_match('/\b(this location|this station|this workstation|at this location)\b/', $text)) {
            return 'workstation';
        }
        if (preg_match('/\b(never|always|standard|require|evidence|do not|don\'t)\b/', $text) && $context->canWriteCompany()) {
            return 'company';
        }
        if ($context->workstation !== null) {
            return 'ask';
        }

        return $context->canWriteCompany() ? 'company' : 'ask_confirm';
    }

    private function extractRememberedFact(string $message): ?string
    {
        if (preg_match('/remember(?:\s+for\s+(?:this\s+)?(?:location|station|workstation|the\s+company|all\s+locations))?\s+(?:that\s+)?(.+)$/is', $message, $match)) {
            return trim($match[1], " \t\n\r\"'");
        }

        return null;
    }

    private function extractCorrection(string $message): ?string
    {
        if (preg_match('/from now on[,:]?\s*(?:phrase it as\s+)?(.+)$/is', $message, $match)) {
            return trim($match[1], " \t\n\r\"'");
        }
        if (preg_match('/change that[. ]+(.+)$/is', $message, $match)) {
            return trim($match[1], " \t\n\r\"'");
        }

        return null;
    }

    private function isRemember(string $message): bool
    {
        if (! preg_match('/\bremember\b/i', $message)) {
            return false;
        }

        return ! preg_match('/\b(do you remember|did you remember|what do you remember)\b/i', $message);
    }

    private function isForget(string $message): bool
    {
        return (bool) preg_match('/^\s*(please\s+)?forget\b|\bforget (the|that|this)\b/i', $message);
    }

    private function isCorrect(string $message): bool
    {
        return (bool) preg_match('/\b(change that|from now on|correct that|update that standard)\b/i', $message);
    }

    private function isConfirm(string $message): bool
    {
        return (bool) preg_match('/^\s*(yes|yeah|yep|y|ok|okay|please do|do it|go ahead|remember it|remember that|company|this station|this location)\b/i', $message);
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    private function setPending(DragonMemoryContext $context, array $pending): void
    {
        $context->conversation?->forceFill(['pending_memory' => $pending])->save();
    }

    private function clearPending(DragonMemoryContext $context): void
    {
        $context->conversation?->forceFill(['pending_memory' => null])->save();
    }

    private function storedReply(DragonAgentMemory $memory): string
    {
        return match ($memory->scope_type) {
            'workstation' => 'Got it. I will remember that for this station.',
            'user' => 'Got it. I will remember that as your preference.',
            default => 'Got it. I will remember that as a company-wide diagnostic standard.',
        };
    }
}
