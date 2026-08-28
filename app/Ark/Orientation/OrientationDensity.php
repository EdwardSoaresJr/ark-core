<?php

namespace App\Ark\Orientation;

enum OrientationDensity: string
{
    /** Sticky RO workspace band — full briefing. */
    case Full = 'full';

    /** Comms rail and relationship context — story + follow-up, no confidence chips. */
    case Standard = 'standard';

    /** Queue rows — situation, story, owner, primary next action. */
    case Compact = 'compact';

    /** Phone pop and message interrupt — minimum viable orientation before acting. */
    case Interrupt = 'interrupt';
}
