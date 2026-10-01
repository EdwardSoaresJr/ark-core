<?php

namespace App\Ark\Tech;

final class TechBrakeSpeechParser
{
    public function __construct(
        private readonly TechSchemaSpeechParser $parser = new TechSchemaSpeechParser,
    ) {}

    /**
     * @param  list<array{key: string, name?: string, unit?: ?string, aliases?: list<string>}>  $slots
     * @return array{measurements: list<array{name: string, value: string, unit: string}>, rotor_condition: ?string, finding: ?string, condition: ?string}
     */
    public function parse(string $transcript, array $slots = []): array
    {
        return $this->parser->parse($transcript, $slots === [] ? null : $slots);
    }
}
