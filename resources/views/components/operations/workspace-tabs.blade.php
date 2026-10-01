@if (config('ark_workspace_tabs.enabled', true))
    <div id="ops-workspace-tabs" class="ops-workspace-tabs" aria-label="Workspaces" aria-hidden="true"></div>

    <script>
        window.__ARK_WORKSPACE__ = @json(array_merge(
            \App\Ark\Operations\Workspace\WorkspaceTabSupport::clientConfig(),
            ['boot' => $arkWorkspaceEntity ?? null]
        ));
    </script>
@endif
