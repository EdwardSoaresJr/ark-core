<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Dragon\Agent\DragonMemoryPrivacy;
use App\Ark\Dragon\Agent\DragonAgentMemory;
use App\Ark\Dragon\Agent\ForgetDragonMemory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DragonMemorySettingsController
{
    public function update(Request $request, DragonAgentMemory $memory): RedirectResponse
    {
        $data = $request->validate([
            'fact_value' => ['required', 'string', 'max:500'],
        ]);
        $fact = trim($data['fact_value']);
        $rejected = DragonMemoryPrivacy::rejectReason($fact);
        if ($rejected !== null) {
            return back()->withErrors(['fact_value' => $rejected]);
        }

        $memory->forceFill([
            'fact_value' => $fact,
            'provenance' => trim((string) $memory->provenance.'|settings:correct'),
        ])->save();

        return back()->with('status', 'Dragon memory updated.');
    }

    public function forget(DragonAgentMemory $memory, ForgetDragonMemory $forget): RedirectResponse
    {
        $forget->forget($memory);

        return back()->with('status', 'Dragon memory forgotten.');
    }
}
