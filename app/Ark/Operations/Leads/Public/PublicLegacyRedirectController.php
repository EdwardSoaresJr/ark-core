<?php

namespace App\Ark\Operations\Leads\Public;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicLegacyRedirectController
{
    public function __invoke(Request $request): Response
    {
        $target = PublicLegacyRedirect::resolve($request->path());

        if ($target === null) {
            abort(404);
        }

        return redirect()->to($target, 301);
    }
}
