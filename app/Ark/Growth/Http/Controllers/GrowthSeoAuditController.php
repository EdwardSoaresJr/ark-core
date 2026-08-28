<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Seo\Audit\SeoAuditEngine;
use Illuminate\View\View;

final class GrowthSeoAuditController
{
    public function __invoke(SeoAuditEngine $auditEngine): View
    {
        return view('growth.audit', [
            'audit' => $auditEngine->summarize(),
        ]);
    }
}
