@if (! empty($intakeWorkspaceParams['ws'] ?? null))
    <input type="hidden" name="ws" value="{{ $intakeWorkspaceParams['ws'] }}">
@endif
@if (! empty($intakeWorkspaceParams['lead_id'] ?? null))
    <input type="hidden" name="lead_id" value="{{ $intakeWorkspaceParams['lead_id'] }}">
@endif
