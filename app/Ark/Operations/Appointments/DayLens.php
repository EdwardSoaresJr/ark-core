<?php

namespace App\Ark\Operations\Appointments;

/**
 * Perspective on one Appointment day board — filters visibility; never reinterprets truth.
 *
 * @see docs/runtime/scheduling-runtime-authority.md
 */
final class DayLens
{
    public const KIND_AGENDA = 'agenda';

    public const KIND_UNASSIGNED = 'unassigned';

    public const KIND_TECHNICIAN = 'technician';

    public const KIND_WORKSTATION = 'workstation';

    private function __construct(
        public readonly string $kind,
        public readonly ?int $resourceId = null,
    ) {}

    public static function agenda(): self
    {
        return new self(self::KIND_AGENDA);
    }

    public static function unassigned(): self
    {
        return new self(self::KIND_UNASSIGNED);
    }

    public static function technician(int $id): self
    {
        return new self(self::KIND_TECHNICIAN, max(1, $id));
    }

    public static function workstation(int $id): self
    {
        return new self(self::KIND_WORKSTATION, max(1, $id));
    }

    /**
     * Parse ?lens= keys. Invalid or empty → Agenda.
     */
    public static function parse(?string $key): self
    {
        $key = trim((string) $key);
        if ($key === '' || $key === self::KIND_AGENDA) {
            return self::agenda();
        }

        if ($key === self::KIND_UNASSIGNED) {
            return self::unassigned();
        }

        if (preg_match('/^technician:(\d+)$/', $key, $matches) === 1) {
            return self::technician((int) $matches[1]);
        }

        if (preg_match('/^workstation:(\d+)$/', $key, $matches) === 1) {
            return self::workstation((int) $matches[1]);
        }

        return self::agenda();
    }

    public function key(): string
    {
        return match ($this->kind) {
            self::KIND_AGENDA => self::KIND_AGENDA,
            self::KIND_UNASSIGNED => self::KIND_UNASSIGNED,
            self::KIND_TECHNICIAN => self::KIND_TECHNICIAN.':'.(int) $this->resourceId,
            self::KIND_WORKSTATION => self::KIND_WORKSTATION.':'.(int) $this->resourceId,
            default => self::KIND_AGENDA,
        };
    }

    public function isAgenda(): bool
    {
        return $this->kind === self::KIND_AGENDA;
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @return list<array<string, mixed>>
     */
    public function filterCards(array $cards): array
    {
        return array_values(array_filter(
            $cards,
            function (array $card): bool {
                return match ($this->kind) {
                    self::KIND_AGENDA => true,
                    self::KIND_UNASSIGNED => empty($card['technician_user_id']) && empty($card['workstation_id']),
                    self::KIND_TECHNICIAN => (int) ($card['technician_user_id'] ?? 0) === (int) $this->resourceId,
                    self::KIND_WORKSTATION => (int) ($card['workstation_id'] ?? 0) === (int) $this->resourceId,
                    default => true,
                };
            },
        ));
    }
}
