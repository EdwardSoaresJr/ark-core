<?php

namespace App\Ark\Operations\Work;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdvisorTaskCompleteController
{
    use RedirectsAdvisorWorkActions;

    public function __invoke(Request $request, AdvisorTask $task): RedirectResponse
    {
        if ($task->completed_at === null) {
            $task->forceFill(['completed_at' => now()])->save();
        }

        return $this->redirectAfterWorkAction($request, 'Task marked complete.');
    }
}
