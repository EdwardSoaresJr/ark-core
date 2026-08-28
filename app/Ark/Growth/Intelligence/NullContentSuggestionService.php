<?php

namespace App\Ark\Growth\Intelligence;

use App\Ark\Growth\Intelligence\Contracts\ContentSuggestionService;

final class NullContentSuggestionService implements ContentSuggestionService
{
    public function suggest(): array
    {
        return [];
    }
}
