@if (config('ark_workspace_tabs.enabled', true))
    @php($workspaceTabsUi = ($ui ?? null) === 'tabler' ? 'tabler' : 'legacy')
    @if ($workspaceTabsUi === 'tabler')
        {{-- The unsaved-changes trackers skip .ops-workspace-tabs, which this mount does not carry. --}}
        <div
            id="ops-workspace-tabs"
            class="d-none d-print-none border-bottom bg-surface"
            data-workspace-tabs
            data-workspace-tabs-ui="tabler"
            data-ark-workspace-dirty="off"
            aria-label="Workspaces"
            aria-hidden="true"
        ></div>
    @else
        <div id="ops-workspace-tabs" class="ops-workspace-tabs" data-workspace-tabs data-workspace-tabs-ui="legacy" aria-label="Workspaces" aria-hidden="true"></div>
    @endif

    <script>
        window.__ARK_WORKSPACE__ = @json(array_merge(
            \App\Ark\Operations\Workspace\WorkspaceTabSupport::clientConfig(),
            ['boot' => $arkWorkspaceEntity ?? null]
        ));
    </script>
@endif
