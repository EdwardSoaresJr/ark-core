<?php

namespace App\Ark\Operations\Realtime;

/**
 * Ordered canonical events — reusable golden-stream artifact for provider parity tests.
 */
final readonly class CanonicalSessionStream
{
    /**
     * @param  list<CanonicalSessionEvent>  $events
     */
    public function __construct(
        public array $events,
    ) {}

    /**
     * @return list<array{type: string, payload: array<string, mixed>}>
     */
    public function signatures(): array
    {
        return array_map(
            fn (CanonicalSessionEvent $event): array => $event->signature(),
            $this->events,
        );
    }

    public function equals(CanonicalSessionStream $other): bool
    {
        return $this->signatures() === $other->signatures();
    }

    /**
     * @param  list<array{type: string, payload?: array<string, mixed>}>  $signatures
     */
    public static function fromSignatures(array $signatures): self
    {
        $events = [];

        foreach ($signatures as $signature) {
            $type = SessionEventType::from((string) $signature['type']);
            $events[] = new CanonicalSessionEvent(
                type: $type,
                payload: (array) ($signature['payload'] ?? []),
            );
        }

        return new self($events);
    }

    public static function fromFixture(string $path): self
    {
        /** @var list<array{type: string, payload?: array<string, mixed>}> $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return self::fromSignatures($decoded);
    }
}
