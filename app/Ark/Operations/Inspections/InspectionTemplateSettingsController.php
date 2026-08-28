<?php

namespace App\Ark\Operations\Inspections;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class InspectionTemplateSettingsController
{
    public function __invoke(Request $request, InspectionTemplateSettingsUpdater $updater): RedirectResponse
    {
        $data = $request->validate([
            'templates' => ['nullable', 'array'],
            'templates.*.enabled' => ['nullable'],
            'templates.*.categories' => ['nullable', 'array'],
            'templates.*.categories.*.name' => ['nullable', 'string', 'max:120'],
            'templates.*.categories.*.items' => ['nullable', 'array'],
            'templates.*.categories.*.items.*.label' => ['nullable', 'string', 'max:191'],
            'templates.*.categories.*.items.*.enabled' => ['nullable'],
            'templates.*.categories.*.items.*.requires_photo' => ['nullable'],
            'templates.*.categories.*.items.*.measurement_name' => ['nullable', 'string', 'max:120'],
            'templates.*.categories.*.items.*.measurement_unit' => ['nullable', 'string', 'max:32'],
        ]);

        $updater->apply($data);

        return redirect()
            ->route('operations.settings.shop.edit', [
                'section' => 'workflow',
                'workflow-tab' => 'inspections',
            ])
            ->with('status', 'Inspection checklist templates updated.');
    }
}
