<?php

namespace App\Ark\Operations\Learn\BookStack;

final class ArkademyImportReport
{
    /** @var list<string> */
    public array $articles = [];

    public int $importedPages = 0;

    public int $stalePagesRemoved = 0;

    public int $bookCount = 0;

    public int $articleCount = 0;

    public string $shelfName = '';
}
