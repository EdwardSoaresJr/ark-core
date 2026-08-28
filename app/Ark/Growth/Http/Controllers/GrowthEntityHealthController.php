<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\EntityHealth\EntityHealthEngine;
use Illuminate\Contracts\View\View;

final class GrowthEntityHealthController
{
    public function __invoke(EntityHealthEngine $entityHealth): View
    {
        return view('growth.entity-health', [
            'entityHealth' => $entityHealth->summarize(),
        ]);
    }
}
