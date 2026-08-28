<?php

namespace App\Ark\Operations\Briefing;

use App\Models\User;
use Illuminate\Support\Carbon;

final readonly class BriefingContext
{
    public function __construct(
        public User $user,
        public Carbon $briefingDate,
        public Carbon $yesterdayFrom,
        public Carbon $yesterdayTo,
        public Carbon $priorWeekFrom,
        public Carbon $priorWeekTo,
    ) {}
}
