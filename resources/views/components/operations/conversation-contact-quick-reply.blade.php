@props([
    'conversation',
    'displayPhone',
    'messagesListId' => 'conversation-messages-contact',
    'hasConversationHistory' => true,
    'nudgeKey' => null,
    'entityKey' => null,
    'initialBody' => null,
    'actionsMenu' => false,
])

@php
    use App\Ark\Operations\Appointments\ScheduleUrl;
    use App\Ark\Operations\OperationsFeatures;
    use App\Ark\Operations\PhoneNumber;
    use App\Ark\Operations\Settings\ShopIntegrationCredentials;

    $integrations = app(ShopIntegrationCredentials::class);
    $canSend = \App\Ark\Platform\Communications\ManagedCommunicationsGate::platformSend()
        || $integrations->twilioConfigured();
    $autoOpen = request()->query('compose') === 'text';
    $canSchedule = OperationsFeatures::appointmentsEnabled();
    $scheduleHref = $canSchedule
        ? ScheduleUrl::to(['conversation' => $conversation->id])
        : null;
    $callHref = PhoneNumber::telUri($displayPhone)
        ?? PhoneNumber::telUri((string) ($conversation->contact_address ?? ''));
    $bodyId = $attributes->get('id', 'ark-contact-composer-'.$conversation->id).'-body';
@endphp

@can(App\Ark\Runtime\Authorization\ArkCapability::OperationsAccess->value)
    @if ($canSchedule || $canSend || filled($callHref))
        <div
            @if ($attributes->has('id'))
                id="{{ $attributes->get('id') }}"
            @endif
            data-ark-workspace-dirty="off"
            data-ark-conversation-composer
            x-data="arkConversationQuickReply(@js([
                'sendUrl' => route('operations.conversations.messages.store', $conversation),
                'messagesListIds' => [$messagesListId],
                'hasConversationHistory' => $hasConversationHistory,
                'autoOpenComposer' => $autoOpen,
                'alwaysOpen' => true,
                'keepOpenAfterSend' => true,
                'customerPhoneDisplay' => $displayPhone,
                'showSmsComposer' => $canSend,
                'nudgeKey' => $nudgeKey,
                'entityKey' => $entityKey,
                'initialBody' => $initialBody,
            ]))"
            {{ $attributes->except('id') }}
        >
            @if ($canSend)
                <div x-show="open">
                    <div class="px-3 py-2">
                        <label class="visually-hidden" for="{{ $bodyId }}">Message</label>
                        <textarea
                            id="{{ $bodyId }}"
                            x-ref="replyBody"
                            x-model="body"
                            rows="2"
                            @if ($autoOpen) autofocus @endif
                            :placeholder="composerPlaceholder()"
                            class="form-control"
                            @keydown.meta.enter.prevent="send()"
                            @keydown.ctrl.enter.prevent="send()"
                        ></textarea>
                        <div class="mt-2">
                            @include('operations.communications.workspace.partials.quick-replies')
                        </div>
                        <div x-show="attachmentLabel" x-cloak class="mt-1 small text-secondary text-truncate" x-text="attachmentLabel"></div>
                        <div x-show="error" x-cloak class="mt-1 small text-danger fw-medium" x-text="error"></div>
                    </div>
                </div>
            @endif
            @if ($actionsMenu || $scheduleHref || filled($callHref) || $canSend)
                <div class="d-flex flex-wrap align-items-center gap-2 px-3 py-2 border-top" role="group" aria-label="Conversation commands">
                    @if ($actionsMenu)
                        <details
                            class="position-relative"
                            x-ref="actionsMenu"
                            @toggle="onActionsMenuToggle($event)"
                            @click.outside="closeActionsMenu()"
                        >
                            <summary class="btn btn-sm">Actions</summary>
                            <div class="dropdown-menu show position-absolute bottom-100 mb-1" data-comms-actions-panel>
                                @if (filled($callHref))
                                    <a href="{{ $callHref }}" class="dropdown-item">
                                        <x-tabler.icon name="phone" class="dropdown-item-icon" />
                                        Call
                                    </a>
                                @endif
                                @if ($scheduleHref)
                                    <a href="{{ $scheduleHref }}" class="dropdown-item">
                                        <x-tabler.icon name="calendar" class="dropdown-item-icon" />
                                        Schedule
                                    </a>
                                @endif
                                @if ($canSend)
                                    <label class="dropdown-item">
                                        <x-tabler.icon name="paperclip" class="dropdown-item-icon" />
                                        Attach file
                                        <input
                                            x-ref="attachmentInput"
                                            type="file"
                                            multiple
                                            class="d-none"
                                            accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,application/pdf"
                                            @change="pickAttachment($event)"
                                        >
                                    </label>
                                @endif
                            </div>
                        </details>
                    @else
                        @if (filled($callHref))
                            <a href="{{ $callHref }}" class="btn btn-sm">
                                <x-tabler.icon name="phone" />
                                Call
                            </a>
                        @endif
                        @if ($scheduleHref)
                            <a href="{{ $scheduleHref }}" class="btn btn-sm">
                                <x-tabler.icon name="calendar" />
                                Schedule
                            </a>
                            <span class="text-secondary small">Book the visit - customer can be linked at arrival.</span>
                        @endif
                        @if ($canSend)
                            <label class="btn btn-sm">
                                <x-tabler.icon name="paperclip" />
                                Attach file
                                <input
                                    x-ref="attachmentInput"
                                    type="file"
                                    multiple
                                    class="d-none"
                                    accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,application/pdf"
                                    @change="pickAttachment($event)"
                                >
                            </label>
                        @endif
                    @endif
                    @if ($canSend)
                        <button
                            type="button"
                            @click="send()"
                            :disabled="sending"
                            class="btn btn-primary btn-sm ms-auto"
                        >
                            <span x-show="! sending">Send</span>
                            <span x-show="sending" x-cloak>Sending…</span>
                        </button>
                    @endif
                </div>
            @endif
        </div>
    @else
        <div {{ $attributes }}>
            <div class="px-3 py-2 text-secondary small">Shop messaging is disabled.</div>
        </div>
    @endif
@endcan
