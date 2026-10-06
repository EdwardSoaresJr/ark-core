<?php

namespace App\Ark\Runtime;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DemoGithubClickController
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(DemoInstall::isDemo(), 404);

        $hosted = $request->query('to') === 'hosted';

        DemoEventLog::record(
            $hosted ? 'demo_hosted_click' : 'demo_github_click',
            DemoEventLog::sourcePath($request->query('source_path')),
        );

        return redirect()->away($hosted ? DemoInstall::HOSTED_URL : DemoInstall::REPOSITORY_URL);
    }
}
