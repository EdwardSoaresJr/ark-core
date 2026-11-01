@props([
    'initialTask' => null,
    'initialContext' => [],
])

@php
    $initialContext = is_array($initialContext) ? $initialContext : [];
@endphp

{{--
    Tabler markup for arkWorkspaceModal (resources/js/ark-workspace-modal.js), with the same id, refs, and bindings as
    x-operations.workspace-modal, which the repair order workspace keeps. Panels carry data-workspace-modal-panel.
--}}
<div
    {{ $attributes }}
    id="workspace-modal-host"
    x-data="arkWorkspaceModal({
        initialTask: @js($initialTask),
        initialContext: @js($initialContext),
    })"
    @keydown.escape.window="onEscape()"
    @keydown.tab="trapFocus($event)"
>
    <div x-show="open" x-cloak data-workspace-modal-overlay>
        <div class="modal-backdrop show"></div>
        <div
            class="modal d-block"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="open ? 'ops-workspace-modal-title' : null"
            @keydown.escape.stop="onEscape()"
            @click.self="if (! saving && ! saved) onBackdrop()"
        >
            <div
                x-ref="dialog"
                class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down"
                @click.self="if (! saving && ! saved) onBackdrop()"
            >
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="overflow-hidden">
                            <h2 id="ops-workspace-modal-title" class="modal-title" x-text="title()"></h2>
                            <div class="small text-secondary" x-show="helper()" x-text="helper()"></div>
                        </div>
                        <button type="button" class="btn-close" aria-label="Close" :disabled="saving || saved" @click="requestClose()"></button>
                    </div>

                    <div id="workspace-modal-body" class="modal-body">
                        {{ $slot }}
                    </div>

                    <div class="modal-footer flex-wrap">
                        <div class="alert alert-danger w-100 mb-2" x-show="validationMessage" x-cloak x-text="validationMessage" role="alert"></div>
                        <div class="me-auto">
                            <template x-if="showDelete()">
                                <button
                                    type="button"
                                    class="btn"
                                    :class="deleteConfirm ? 'btn-danger' : 'btn-outline-danger'"
                                    :disabled="saving || saved"
                                    :aria-label="deleteConfirm ? `Confirm delete within ${deleteConfirmSeconds} seconds` : 'Delete line'"
                                    @click="armOrSubmitDelete()"
                                    x-text="deleteLabel()"
                                ></button>
                            </template>
                        </div>
                        <button type="button" class="btn" :disabled="saving || saved" @click="requestClose()">Cancel</button>
                        <button type="button" class="btn" x-show="showSaveAndRewrite()" x-cloak :disabled="saving || saved" @click="submitPrimaryAndRewrite()">Save &amp; Generate</button>
                        <button
                            type="button"
                            class="btn"
                            x-ref="primaryBtn"
                            x-show="showPrimary()"
                            :disabled="saving || saved"
                            :class="saved ? 'btn-success' : 'btn-primary'"
                            @click="submitPrimary()"
                        >
                            <span x-show="! saving && ! saved" x-text="primaryLabel()"></span>
                            <span x-show="saving" x-cloak>Saving…</span>
                            <span x-show="saved" x-cloak>✓ Saved</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
