@props([
    'initialTask' => null,
    'initialContext' => [],
])

@php
    $initialContext = is_array($initialContext) ? $initialContext : [];
@endphp

<div
    {{ $attributes->class('ops-workspace-modal-host') }}
    id="workspace-modal-host"
    x-data="arkWorkspaceModal({
        initialTask: @js($initialTask),
        initialContext: @js($initialContext),
    })"
    @keydown.escape.window="onEscape()"
    @keydown.tab="trapFocus($event)"
>
    <div
        x-show="open"
        x-cloak
        class="ops-workspace-modal"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="open ? 'ops-workspace-modal-title' : null"
        @keydown.escape.stop="onEscape()"
    >
        <button
            type="button"
            class="ops-workspace-modal__backdrop"
            aria-label="Close"
            :disabled="saving || saved"
            @click="onBackdrop()"
        ></button>

        <div
            x-ref="dialog"
            class="ops-workspace-modal__dialog"
            x-show="open"
            x-transition:enter="ops-workspace-modal--enter"
            x-transition:enter-start="ops-workspace-modal--enter-start"
            x-transition:enter-end="ops-workspace-modal--enter-end"
            x-transition:leave="ops-workspace-modal--leave"
            x-transition:leave-start="ops-workspace-modal--leave-start"
            x-transition:leave-end="ops-workspace-modal--leave-end"
            @click.stop
        >
            <header class="ops-workspace-modal__header">
                <div class="ops-workspace-modal__heading min-w-0">
                    <h2 id="ops-workspace-modal-title" class="ops-workspace-modal__title" x-text="title()"></h2>
                    <p class="ops-workspace-modal__helper" x-show="helper()" x-text="helper()"></p>
                </div>
                <button
                    type="button"
                    class="ops-workspace-modal__close"
                    aria-label="Close"
                    :disabled="saving || saved"
                    @click="requestClose()"
                >
                    Close
                </button>
            </header>

            <div class="ops-workspace-modal__body">
                {{ $slot }}
            </div>

            <p
                class="ops-workspace-modal__validation"
                x-show="validationMessage"
                x-cloak
                x-text="validationMessage"
                role="alert"
            ></p>

            <footer class="ops-workspace-modal__footer">
                <div class="ops-workspace-modal__footer-start">
                    <template x-if="showDelete()">
                        <button
                            type="button"
                            class="ops-workspace-modal__danger"
                            :class="{ 'ops-workspace-modal__danger--confirm': deleteConfirm }"
                            :disabled="saving || saved"
                            :aria-label="deleteConfirm ? `Confirm delete within ${deleteConfirmSeconds} seconds` : 'Delete line'"
                            @click="armOrSubmitDelete()"
                            x-text="deleteLabel()"
                        ></button>
                    </template>
                </div>

                <div class="ops-workspace-modal__footer-end">
                    <button
                        type="button"
                        class="ops-workspace-modal__cancel"
                        :disabled="saving || saved"
                        @click="requestClose()"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="ops-workspace-modal__secondary"
                        x-show="showSaveAndRewrite()"
                        x-cloak
                        :disabled="saving || saved"
                        @click="submitPrimaryAndRewrite()"
                    >
                        Save &amp; Generate
                    </button>

                    <button
                        type="button"
                        class="ops-workspace-modal__primary"
                        x-ref="primaryBtn"
                        x-show="showPrimary()"
                        :disabled="saving || saved"
                        :class="{
                            'ops-workspace-modal__primary--saved': saved,
                            'ops-workspace-modal__primary--note': task === 'note' && ! saved,
                        }"
                        @click="submitPrimary()"
                    >
                        <span x-show="! saving && ! saved" x-text="primaryLabel()"></span>
                        <span x-show="saving" x-cloak>Saving…</span>
                        <span x-show="saved" x-cloak>✓ Saved</span>
                    </button>
                </div>
            </footer>
        </div>
    </div>
</div>
