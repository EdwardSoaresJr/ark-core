<?php

namespace App\Ark\Operations\Leads\Public;

use Illuminate\Http\Request;

final class CommonProblemLeadForm
{
    /**
     * Index conversion is Book CTA only — same family as show pages.
     *
     * @return array<string, mixed>
     */
    public function forIndex(Request $request, string $shopName): array
    {
        return [
            'formHeading' => 'Book an Appointment',
            'prefilledConcern' => null,
            'problemTitle' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $problem
     * @return array<string, mixed>
     */
    public function forShow(Request $request, array $problem, string $shopName): array
    {
        return [
            'prefilledConcern' => $problem['concern_prefill'],
            'problemTitle' => $problem['title'],
            'formHeading' => 'Book for '.$problem['title'],
            'surfaceContext' => PublicSurfaceContext::commonProblemShow($problem['slug']),
        ];
    }
}
