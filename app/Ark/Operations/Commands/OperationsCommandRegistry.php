<?php

namespace App\Ark\Operations\Commands;

use App\Models\User;

final class OperationsCommandRegistry
{
    /** @var list<OperationsCommand> */
    private array $commands = [];

    public function register(OperationsCommand $command): void
    {
        $this->commands[] = $command;
    }

    /**
     * @return list<OperationsCommand>
     */
    public function all(): array
    {
        return $this->commands;
    }

    /**
     * @return list<OperationsCommand>
     */
    public function forUser(?User $user): array
    {
        return array_values(array_filter(
            $this->commands,
            fn (OperationsCommand $command): bool => $command->permission === null
                || ($user !== null && $user->can($command->permission)),
        ));
    }

    /**
     * Simple substring match across title, group, and keywords.
     *
     * @return list<OperationsCommand>
     */
    public function filter(string $query, ?User $user): array
    {
        $available = $this->forUser($user);
        $needle = mb_strtolower(trim($query));

        if ($needle === '') {
            return $available;
        }

        return array_values(array_filter(
            $available,
            function (OperationsCommand $command) use ($needle): bool {
                $haystack = mb_strtolower(implode(' ', [
                    $command->title,
                    $command->group,
                    ...$command->keywords,
                ]));

                return str_contains($haystack, $needle);
            },
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forUserAsArrays(?User $user): array
    {
        return array_map(
            fn (OperationsCommand $command): array => $command->toArray(),
            $this->forUser($user),
        );
    }
}
