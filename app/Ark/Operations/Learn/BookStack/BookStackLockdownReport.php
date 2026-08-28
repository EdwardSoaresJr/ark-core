<?php

namespace App\Ark\Operations\Learn\BookStack;

final class BookStackLockdownReport
{
    public int $orphansRemoved = 0;

    public int $orphansSkipped = 0;

    public int $pagesReattributed = 0;

    public int $revisionsReattributed = 0;

    public bool $importTokenReassigned = false;

    public ?int $authorBookStackUserId = null;
}
