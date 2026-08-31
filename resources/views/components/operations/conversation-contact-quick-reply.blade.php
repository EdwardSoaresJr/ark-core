@props([
    'conversation',
    'displayPhone',
    'messagesListId' => 'conversation-messages-contact',
    'hasConversationHistory' => true,
    'nudgeKey' => null,
    'entityKey' => null,
    'initialBody' => null,
])

@php
    use App\Ark\Operations\Appointments\ScheduleUrl;
    use App\Ark\Operations\OperationsFeatures;
    use App\Ark\Operations\PhoneNumber;
    use App\Ark\Operations\Settings\ShopIntegrationCredentials;

    $integrations = app(ShopIntegrationCredentials::class);
    $canSend = $integrations->messagingConfigured();
    $autoOpen = request()->query('compose') === 'text';
    $canSchedule = OperationsFeatures::appointmentsEnabled();
    $scheduleHref = $canSchedule
        ? ScheduleUrl::to(['conversation' => $conversation->id])
        : null;
    $callHref = PhoneNumber::telUri($displayPhone)
        ?? PhoneNumber::telUri((string) ($conversation->contact_address ?? ''));
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
            {{ $attributes->class(['border-t border-slate-200 bg-slate-50/40'])->except('id') }}
        >
            @if ($scheduleHref || filled($callHref))
                <div class="flex flex-wrap items-center gap-2 px-3 py-2" aria-label="Conversation commands">
                    @if (filled($callHref))
                        <a
                            href="{{ $callHref }}"
                            class="inline-flex h-8 items-center rounded-sm border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950"
                        >Call</a>
                    @endif
                    @if ($scheduleHref)
                        <a
                            href="{{ $scheduleHref }}"
                            class="inline-flex h-8 items-center rounded-sm border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950"
                        >Schedule</a>
                        <span class="text-[11px] text-slate-500">Identify the customer if this thread isn’t linked yet.</span>
                    @endif
                </div>
            @endif
            @if ($canSend)
                <div x-show="open" class="grid gap-1.5 border-t border-slate-200 px-3 py-2">
                    <textarea
                        x-ref="replyBody"
                        x-model="body"
                        rows="2"
                        @if ($autoOpen) autofocus @endif
                        :placeholder="composerPlaceholder()"
                        class="w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950 placeholder:text-slate-400"
                        @keydown.meta.enter.prevent="send()"
                        @keydown.ctrl.enter.prevent="send()"
                    ></textarea>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label class="h-8 cursor-pointer rounded-sm border border-slate-300 bg-white px-2.5 text-xs font-semibold leading-8 text-slate-700 hover:border-slate-400 hover:text-slate-950">
                            Attach file
                            <input
                                x-ref="attachmentInput"
                                type="file"
                                class="hidden"
                                accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,application/pdf"
                                @change="pickAttachment($event)"
                            >
                        </label>
                        <button
                            type="button"
                            @click="send()"
                            :disabled="sending"
                            class="h-8 shrink-0 rounded-sm border border-slate-800 bg-slate-900 px-3 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-60"
                        >
                            <span x-show="! sending">Send</span>
                            <span x-show="sending" x-cloak>Sending…</span>
                        </button>
                    </div>
                    <p x-show="attachmentLabel" x-cloak class="truncate text-[11px] font-medium text-slate-500" x-text="attachmentLabel"></p>
                    <p x-show="error" x-cloak class="text-xs font-semibold text-rose-700" x-text="error"></p>
                    @include('operations.communications.workspace.partials.quick-replies')
                </div>
            @endif
        </div>
    @else
        <div {{ $attributes->class(['border-t border-slate-200 px-3 py-2 text-xs text-slate-500']) }}>
            Shop messaging is disabled.
        </div>
    @endif
@endcan
