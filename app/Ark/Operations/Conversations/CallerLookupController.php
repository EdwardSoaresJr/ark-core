<?php

namespace App\Ark\Operations\Conversations;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CallerLookupController
{
    public function __invoke(Request $request, CustomerCallContextResolver $resolver): View
    {
        $phone = trim((string) $request->query('phone', ''));

        $context = $phone !== ''
            ? $resolver->resolve($phone)
            : null;

        return view('operations.caller-lookup.index', [
            'phone' => $phone,
            'context' => $context,
        ]);
    }
}
