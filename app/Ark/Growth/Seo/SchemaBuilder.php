<?php

namespace App\Ark\Growth\Seo;

interface SchemaBuilder
{
    public function type(): string;

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function build(array $context): array;
}
