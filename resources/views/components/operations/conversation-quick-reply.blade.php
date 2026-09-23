@props([
    'customer',
    'repairOrder' => null,
    'repairOrderId' => null,
    'conversation' => null,
    'isTerminal' => false,
    'openRepairOrders' => [],
    'messagesListIds' => [],
    'sendEstimateUrl' => null,
    'sendPaymentUrl' => null,
    'sendDepositUrl' => null,
    'sendInspectionUrl' => null,
    'hasConversationHistory' => true,
    'alwaysOpen' => false,
    'messengerAlwaysOpen' => false,
    'keepOpenAfterSend' => false,
    'showQuickReplies' => false,
    'nudgeKey' => null,
    'entityKey' => null,
    'initialBody' => null,
    'actionsMenu' => false,
    'sendUrl' => null,
    'platformBacked' => false,
])

@php
    use App\Ark\Operations\Appointments\ScheduleUrl;
    use App\Ark\Operations\Customers\CustomerSmsSendEligibility;
    use App\Ark\Operations\PhoneNumber;
    use App\Ark\Operations\Messaging\MessageActionKey;
    use App\Ark\Operations\Messaging\MessageActionsSettings;
    use App\Ark\Operations\Messaging\Messenger\MetaMessengerConfiguration;
    use App\Ark\Operations\Messaging\Messenger\MetaMessengerMessageTag;
    use App\Ark\Operations\Messaging\RepairOrderConversationSendProjection;
    use App\Ark\Operations\Communications\ScheduledOutboundSmsProjection;
    use App\Ark\Operations\OperationsFeatures;
    use App\Ark\Operations\RepairOrders\RepairOrder;

    $square = app(App\Ark\Operations\Payments\CardPresentCaptureProjection::class);
    $integrations = app(App\Ark\Operations\Settings\ShopIntegrationCredentials::class);
    $sendProjectionService = app(RepairOrderConversationSendProjection::class);
    $smsScheduleProjection = app(ScheduledOutboundSmsProjection::class);
    $messengerConfig = MetaMessengerConfiguration::current();
    $smsEligibility = CustomerSmsSendEligibility::for($customer, $integrations);

    $canSendSms = $smsEligibility->canSend();
    $smsDeliveryWarning = $smsEligibility->deliveryWarning();
    $smsBlockedReason = $smsEligibility->blockReason();
    $canSendMessenger = filled($customer->messenger_psid)
        && $messengerConfig->isConfigured();
    $composeMessenger = request()->query('compose') === 'messenger';
    $messengerMessageTags = collect(MetaMessengerMessageTag::cases())
        ->map(fn (MetaMessengerMessageTag $tag): array => [
            'value' => $tag->value,
            'label' => $tag->label(),
        ])
        ->values()
        ->all();

    $contextProjection = $repairOrder instanceof RepairOrder
        ? $sendProjectionService->forRepairOrder($repairOrder, auth()->user())
        : null;

    $repairOrderOptions = collect($openRepairOrders)
        ->map(function ($openRepairOrder) use ($sendProjectionService): array {
            $projection = $sendProjectionService->forRepairOrder(
                $openRepairOrder->repairOrder,
                auth()->user(),
            );

            return [
                'repair_order_id' => $openRepairOrder->repairOrder->repair_order_id,
                'label' => 'RO #'.$openRepairOrder->repairOrder->repair_order_id.' · '.$openRepairOrder->vehicle->display_name,
                'send_estimate_url' => route('operations.repair-orders.conversation-actions.send-estimate', $openRepairOrder->repairOrder),
                'cancel_scheduled_estimate_url' => route('operations.repair-orders.conversation-actions.cancel-scheduled-estimate', $openRepairOrder->repairOrder),
                'send_payment_url' => route('operations.repair-orders.conversation-actions.send-payment', $openRepairOrder->repairOrder),
                'send_deposit_url' => route('operations.repair-orders.conversation-actions.send-deposit', $openRepairOrder->repairOrder),
                'send_inspection_url' => route('operations.repair-orders.conversation-actions.send-inspection', $openRepairOrder->repairOrder),
                'add_vin_url' => route('operations.repair-orders.show', $openRepairOrder->repairOrder).'#ro-identity-band',
                'estimate' => $projection['estimate'],
                'payment' => $projection['payment'],
                'deposit' => $projection['deposit'],
                'inspection' => $projection['inspection'],
                'schedule' => $projection['schedule'],
            ];
        })
        ->values()
        ->all();

    $estimateSchedule = is_array($contextProjection)
        ? ($contextProjection['schedule'] ?? ['shop_is_open' => true, 'pending' => null])
        : ['shop_is_open' => true, 'pending' => null];
    $smsSchedule = $smsScheduleProjection->forCustomer($customer->id);
    // Prefer live shop hours for after-hours SMS prompts even when estimate context is missing.
    if (! is_array($contextProjection)) {
        $estimateSchedule['shop_is_open'] = $smsSchedule['shop_is_open'];
    }
    $cancelScheduledEstimateUrl = $repairOrder instanceof RepairOrder
        ? route('operations.repair-orders.conversation-actions.cancel-scheduled-estimate', $repairOrder)
        : null;
    $cancelScheduledSmsUrl = route('operations.customers.conversation-actions.cancel-scheduled-sms', $customer);

    $callback = app(App\Ark\Operations\Telephony\TelephonyCallbackPresenter::class)
        ->forCustomer($customer->id, $customer->phone);

    $customerEmail = trim((string) $customer->email);
    $hasEstimateContext = $sendEstimateUrl !== null || $repairOrderOptions !== [];
    $hasPaymentContext = $sendPaymentUrl !== null || $repairOrderOptions !== [];
    $hasDepositContext = $sendDepositUrl !== null || $repairOrderOptions !== [];
    $hasInspectionContext = $sendInspectionUrl !== null || $repairOrderOptions !== [];

    if ($contextProjection !== null) {
        $estimateProjection = $contextProjection['estimate'];
        $paymentProjection = $contextProjection['payment'];
        $depositProjection = $contextProjection['deposit'];
        $inspectionProjection = $contextProjection['inspection'];
        $canSmsEstimate = $estimateProjection['can_sms'];
        $canEmailEstimate = $estimateProjection['can_email'];
        $canSmsPayment = $paymentProjection['can_sms'];
        $canEmailPayment = $paymentProjection['can_email'];
        $canSmsDeposit = $depositProjection['can_sms'];
        $canEmailDeposit = $depositProjection['can_email'];
        $canSmsInspection = $inspectionProjection['can_sms'];
        $depositSuggestedAmount = $depositProjection['suggested_amount_decimal'] ?? '';
    } else {
        $canSmsEstimate = $canSendSms && $hasEstimateContext;
        $canEmailEstimate = $hasEstimateContext && collect($repairOrderOptions)->contains(
            fn (array $option): bool => ($option['estimate']['can_email'] ?? false),
        );
        $canSmsPayment = $square->portalPayEnabled() && $hasPaymentContext && collect($repairOrderOptions)->contains(
            fn (array $option): bool => ($option['payment']['can_sms'] ?? false),
        );
        $canEmailPayment = $square->portalPayEnabled() && $hasPaymentContext && collect($repairOrderOptions)->contains(
            fn (array $option): bool => ($option['payment']['can_email'] ?? false),
        );
        $canSmsDeposit = $square->portalPayEnabled() && $hasDepositContext && collect($repairOrderOptions)->contains(
            fn (array $option): bool => ($option['deposit']['can_sms'] ?? false),
        );
        $canEmailDeposit = $square->portalPayEnabled() && $hasDepositContext && collect($repairOrderOptions)->contains(
            fn (array $option): bool => ($option['deposit']['can_email'] ?? false),
        );
        $canSmsInspection = $hasInspectionContext && collect($repairOrderOptions)->contains(
            fn (array $option): bool => ($option['inspection']['can_sms'] ?? false),
        );
        $depositSuggestedAmount = collect($repairOrderOptions)
            ->map(fn (array $option): ?string => $option['deposit']['suggested_amount_decimal'] ?? null)
            ->first(fn (?string $amount): bool => filled($amount)) ?? '';
    }

    $showEstimateActions = $hasEstimateContext;
    $showPaymentActions = $square->portalPayEnabled() && $hasPaymentContext;
    $showDepositActions = $square->portalPayEnabled() && $hasDepositContext;
    $showInspectionActions = $hasInspectionContext;
    $canSendEstimate = $showEstimateActions;
    $canSendPayment = $showPaymentActions;
    $canSendDeposit = $showDepositActions;
    $canSendInspection = $showInspectionActions;
    $messageActions = [];

    if ($canSendSms) {
        foreach (MessageActionKey::advisorOneTap() as $action) {
            if (! MessageActionsSettings::canSend($action)) {
                continue;
            }

            $messageActions[] = [
                'key' => $action->value,
                'label' => $action->label(),
                'color' => MessageActionsSettings::color($action),
                'url' => route('operations.customers.conversation-actions.send', [
                    'customer' => $customer,
                    'messageAction' => $action->value,
                ]),
            ];
        }
    }

    $canSchedule = OperationsFeatures::appointmentsEnabled();
    $scheduleHref = null;
    if ($canSchedule) {
        if ($repairOrder instanceof RepairOrder) {
            $scheduleHref = ScheduleUrl::to(array_filter([
                'repair_order' => $repairOrder->id,
                'conversation' => $conversation?->id,
            ]));
        } elseif ($conversation !== null) {
            $scheduleHref = ScheduleUrl::to(['conversation' => $conversation->id]);
        } else {
            $scheduleHref = ScheduleUrl::to(['customer' => $customer->id]);
        }
    }
    $callHref = PhoneNumber::telUri($customer->phone);
    $showSmsComposer = $canSendSms;
    $showSmsUnavailableNotice = filled($customer->phone)
        && $integrations->twilioConfigured()
        && ! $canSendSms
        && filled($smsBlockedReason);
    $showCommsToolbar = $showSmsComposer
        || $showEstimateActions
        || $showPaymentActions
        || $showDepositActions
        || $showInspectionActions
        || $messageActions !== []
        || $canSchedule
        || filled($callHref)
        || ($callback['show_callback_action'] ?? false)
        || $showSmsUnavailableNotice
        || filled($smsDeliveryWarning);
    $defaultEstimateDelivery = $canSmsEstimate ? 'sms' : 'email';
    $defaultPaymentDelivery = $canSmsPayment ? 'sms' : 'email';
    $defaultDepositDelivery = $canSmsDeposit ? 'sms' : 'email';
@endphp

@can(App\Ark\Runtime\Authorization\ArkCapability::OperationsAccess->value)
    @if ($showCommsToolbar)
        <div
            @if ($attributes->has('id'))
                id="{{ $attributes->get('id') }}"
            @endif
            data-ark-workspace-dirty="off"
            data-ark-conversation-composer
            @if ($platformBacked)
                data-platform-backed="1"
            @endif
            x-data="arkConversationQuickReply(@js([
                'sendUrl' => filled($sendUrl)
                    ? $sendUrl
                    : route('operations.customers.conversation-messages.store', $customer),
                'repairOrderId' => $repairOrderId,
                'sendEstimateUrl' => $sendEstimateUrl,
                'cancelScheduledEstimateUrl' => $cancelScheduledEstimateUrl,
                'cancelScheduledSmsUrl' => $cancelScheduledSmsUrl,
                'sendPaymentUrl' => $sendPaymentUrl,
                'sendDepositUrl' => $sendDepositUrl,
                'sendInspectionUrl' => $sendInspectionUrl,
                'messageActions' => $messageActions,
                'contextEstimateSend' => $contextProjection ? $contextProjection['estimate'] : null,
                'contextPaymentSend' => $contextProjection ? $contextProjection['payment'] : null,
                'contextDepositSend' => $contextProjection ? $contextProjection['deposit'] : null,
                'contextInspectionSend' => $contextProjection ? $contextProjection['inspection'] : null,
                'estimateSchedule' => $estimateSchedule,
                'smsSchedule' => $smsSchedule,
                'openRepairOrders' => $repairOrderOptions,
                'customerId' => $customer->id,
                'messagesListIds' => $messagesListIds,
                'hasConversationHistory' => $hasConversationHistory,
                'autoOpenComposer' => request()->query('compose') === 'text' || $alwaysOpen,
                'alwaysOpen' => $alwaysOpen,
                'keepOpenAfterSend' => $keepOpenAfterSend,
                'customerPhoneDisplay' => $customer->display_phone,
                'customerEmail' => $customerEmail,
                'estimateDelivery' => $defaultEstimateDelivery,
                'paymentDelivery' => $defaultPaymentDelivery,
                'depositDelivery' => $defaultDepositDelivery,
                'depositAmount' => $depositSuggestedAmount,
                'canSmsEstimate' => $canSmsEstimate,
                'canEmailEstimate' => $canEmailEstimate,
                'canSmsPayment' => $canSmsPayment,
                'canEmailPayment' => $canEmailPayment,
                'canSmsDeposit' => $canSmsDeposit,
                'canEmailDeposit' => $canEmailDeposit,
                'canSmsInspection' => $canSmsInspection,
                'showSmsComposer' => $showSmsComposer,
                'sendEstimateAddVinUrl' => $repairOrder instanceof RepairOrder
                    ? route('operations.repair-orders.show', $repairOrder).'#ro-identity-band'
                    : null,
                'nudgeKey' => $nudgeKey,
                'entityKey' => $entityKey,
                'initialBody' => $initialBody,
                'platformBacked' => (bool) $platformBacked,
            ]))"
            {{ $attributes->class(['border-t border-slate-200 bg-slate-50/40'])->except('id') }}
        >
            @if ($contextProjection !== null && filled($contextProjection['estimate']['send_block_reason'] ?? null))
                <div class="border-b border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                    {{ $contextProjection['estimate']['send_block_reason'] }}
                </div>
            @elseif ($contextProjection !== null && filled($contextProjection['payment']['send_block_reason'] ?? null))
                <div class="border-b border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                    {{ $contextProjection['payment']['send_block_reason'] }}
                </div>
            @elseif ($showSmsUnavailableNotice)
                <div class="border-b border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                    {{ $smsBlockedReason }}
                </div>
            @elseif (filled($smsDeliveryWarning))
                <div class="border-b border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                    {{ $smsDeliveryWarning }}
                </div>
            @endif
            <div
                x-show="estimateSchedule?.pending"
                x-cloak
                class="flex flex-wrap items-center justify-between gap-2 border-b border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-950"
            >
                <p>
                    Scheduled estimate · <span x-text="estimateSchedule?.pending?.scheduled_for_label ?? estimateSchedule?.next_open_morning_label ?? smsSchedule?.next_open_morning_label ?? 'Next open morning'"></span>
                </p>
                <button
                    type="button"
                    class="font-semibold text-sky-900 underline decoration-sky-400 underline-offset-2 hover:text-sky-950"
                    :disabled="sending"
                    @click="cancelScheduledEstimate()"
                >Cancel</button>
            </div>
            <div
                x-show="smsSchedule?.pending"
                x-cloak
                class="flex flex-wrap items-center justify-between gap-2 border-b border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-950"
            >
                <p>
                    Scheduled reply · <span x-text="smsSchedule?.pending?.scheduled_for_label ?? smsSchedule?.next_open_morning_label ?? 'Next open morning'"></span>
                    <span
                        x-show="smsSchedule?.pending?.preview"
                        class="mt-0.5 block font-normal text-sky-900/80"
                        x-text="smsSchedule?.pending?.preview"
                    ></span>
                </p>
                <button
                    type="button"
                    class="font-semibold text-sky-900 underline decoration-sky-400 underline-offset-2 hover:text-sky-950"
                    :disabled="sending"
                    @click="cancelScheduledSms()"
                >Cancel</button>
            </div>
            <div
                x-show="afterHoursPromptOpen"
                x-cloak
                class="border-b border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950"
            >
                <p class="font-semibold">It's after business hours.</p>
                <p class="mt-1">
                    Send now, or schedule for
                    <span x-text="smsSchedule?.next_open_morning_label ?? 'next open morning'"></span>?
                </p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="h-7 rounded-sm border border-amber-300 bg-white px-2.5 text-xs font-semibold text-amber-950 hover:border-amber-400"
                        :disabled="sending"
                        @click="confirmAfterHoursTiming('now')"
                    >Send Now</button>
                    <button
                        type="button"
                        class="h-7 rounded-sm border border-amber-400 bg-amber-100 px-2.5 text-xs font-semibold text-amber-950 hover:bg-amber-200"
                        :disabled="sending"
                        @click="confirmAfterHoursNextOpenMorning()"
                    >Schedule</button>
                    <button
                        type="button"
                        class="h-7 px-2 text-xs font-semibold text-amber-800/80 hover:text-amber-950"
                        :disabled="sending"
                        @click="dismissAfterHoursPrompt()"
                    >Cancel</button>
                </div>
            </div>
            <div @class(['ops-comms-composer-stack', 'ops-comms-composer-stack--compact' => $actionsMenu])>
            <div @class(['flex flex-wrap items-center gap-2 px-3 py-2', 'ops-comms-composer-stack__tools' => $actionsMenu, 'justify-between' => $actionsMenu]) aria-label="Conversation commands">
                @if ($actionsMenu)
                    <details
                        class="ops-comms-actions"
                        x-ref="actionsMenu"
                        @toggle="onActionsMenuToggle($event)"
                        @click.outside="closeActionsMenu()"
                    >
                        <summary class="ops-comms-actions__summary">Actions</summary>
                        <div class="ops-comms-actions__panel">
                            @if ($callHref)
                                <a href="{{ $callHref }}" class="ops-comms-actions__item">Call</a>
                            @endif
                            @if ($canSendEstimate)
                                <button type="button" class="ops-comms-actions__item" :disabled="sending" @click.stop="sendEstimate('sms')">Send Estimate</button>
                            @endif
                            @if ($canSendInspection)
                                <button type="button" class="ops-comms-actions__item" :disabled="sending" @click.stop="sendInspectionLink()">Send Inspection</button>
                            @endif
                            @if ($scheduleHref)
                                <a href="{{ $scheduleHref }}" class="ops-comms-actions__item">Schedule</a>
                            @endif
                            @if ($canSendPayment)
                                <button type="button" class="ops-comms-actions__item" :disabled="sending" @click.stop="sendPaymentLink('sms')">Send Pay Link</button>
                            @endif
                            @if ($showSmsComposer)
                                <label class="ops-comms-actions__item">
                                    Attach file
                                    <input
                                        x-ref="attachmentInput"
                                        type="file"
                                        class="hidden"
                                        accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,application/pdf"
                                        @change="pickAttachment($event)"
                                    >
                                </label>
                            @endif
                            @foreach ($messageActions as $messageAction)
                                <button
                                    type="button"
                                    class="ops-comms-actions__item"
                                    @if (($messageAction['color'] ?? 'neutral') !== 'neutral') style="box-shadow: inset 3px 0 0 {{ \App\Ark\Operations\Communications\CommunicationsAccentColor::swatch($messageAction['color']) }}" @endif
                                    :disabled="sending"
                                    @click.stop="sendMessageAction(@js($messageAction['key']))"
                                >{{ $messageAction['label'] }}</button>
                            @endforeach
                        </div>
                    </details>
                @endif
                @if (! $actionsMenu && $callHref)
                    <a
                        href="{{ $callHref }}"
                        class="inline-flex h-8 items-center rounded-sm border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950"
                    >Call</a>
                @endif
                @if ($showSmsComposer)
                    @unless ($alwaysOpen)
                        <button
                            type="button"
                            @click="toggleReply()"
                            class="h-8 rounded-sm border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950"
                            data-composer-mode="{{ $hasConversationHistory ? 'reply' : 'text-customer' }}"
                            :data-composer-mode="hasConversationHistory ? 'reply' : 'text-customer'"
                        >
                            <span x-show="! open" x-text="composerActionLabel()">{{ $hasConversationHistory ? 'Reply' : 'Text Customer' }}</span>
                            <span x-show="open" x-cloak>Cancel</span>
                        </button>
                    @endunless
                    <div class="ops-comms-menu" x-ref="smsSendMenu" x-show="channel === 'sms'" x-cloak>
                        <button
                            type="button"
                            x-ref="smsSendMenuTrigger"
                            @click.stop="toggleSmsSendMenu()"
                            :disabled="sending"
                            class="ops-comms-menu__trigger"
                            :aria-expanded="smsSendMenuOpen"
                            aria-haspopup="menu"
                        >
                            <span x-text="sending ? 'Sending…' : 'Send'">Send</span>
                            <span class="ops-comms-menu__caret" aria-hidden="true">▾</span>
                        </button>
                        <template x-teleport="body">
                            <div
                                x-show="smsSendMenuOpen"
                                x-ref="smsSendMenuPanel"
                                :style="smsSendMenuStyle"
                                class="ops-comms-menu__panel ops-comms-menu__panel--floating"
                                role="menu"
                                @click.stop
                            >
                                <button
                                    type="button"
                                    role="menuitem"
                                    class="ops-comms-menu__item"
                                    :disabled="sending"
                                    @click.stop="chooseSmsSendTiming('now')"
                                >Now</button>
                                <div class="ops-comms-menu__separator" role="separator"></div>
                                <template x-for="slot in (smsSchedule?.upcoming ?? [])" :key="slot.day_key">
                                    <button
                                        type="button"
                                        role="menuitem"
                                        class="ops-comms-menu__item"
                                        :class="{ 'opacity-50': !! attachment && ! sending }"
                                        :disabled="sending"
                                        @click.stop="chooseSmsSendMorning(slot.scheduled_for)"
                                        x-text="slot.label"
                                    ></button>
                                </template>
                            </div>
                        </template>
                    </div>
                @endif
                @if (! $actionsMenu)
                @if (($canSendEstimate || $canSendPayment || $canSendDeposit) && $sendEstimateUrl === null && $sendPaymentUrl === null && $sendDepositUrl === null && count($repairOrderOptions) > 1)
                    <select
                        x-model="selectedRepairOrderId"
                        class="ops-comms-ro-select h-8 rounded-sm border border-slate-300 bg-white py-1 pl-2 pr-7 text-xs font-semibold text-slate-700"
                    >
                        @foreach ($repairOrderOptions as $option)
                            <option value="{{ $option['repair_order_id'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                @endif
                @if ($canSendEstimate)
                    <div class="ops-comms-menu" x-ref="estimateMenu">
                        <button
                            type="button"
                            x-ref="estimateMenuTrigger"
                            @click.stop="toggleEstimateMenu()"
                            :disabled="sending"
                            class="ops-comms-menu__trigger"
                            :aria-expanded="estimateMenuOpen"
                            aria-haspopup="menu"
                        >
                            <span>Send Estimate</span>
                            <span class="ops-comms-menu__caret" aria-hidden="true">▾</span>
                        </button>
                        <template x-teleport="body">
                            <div
                                x-show="estimateMenuOpen"
                                x-ref="estimateMenuPanel"
                                :style="estimateMenuStyle"
                                class="ops-comms-menu__panel ops-comms-menu__panel--floating"
                                role="menu"
                                @click.stop
                            >
                                <button
                                    type="button"
                                    role="menuitem"
                                    class="ops-comms-menu__item"
                                    :class="{ 'opacity-50': ! canSmsEstimate && ! sending }"
                                    :disabled="sending"
                                    @click.stop="sendEstimate('sms')"
                                >SMS</button>
                                <button
                                    type="button"
                                    role="menuitem"
                                    class="ops-comms-menu__item"
                                    :class="{ 'opacity-50': ! canEmailEstimate && ! sending }"
                                    :disabled="sending"
                                    @click.stop="sendEstimate('email')"
                                >Email</button>
                                <button
                                    type="button"
                                    role="menuitem"
                                    class="ops-comms-menu__item"
                                    :class="{ 'opacity-50': (! canSmsEstimate || ! canEmailEstimate) && ! sending }"
                                    :disabled="sending"
                                    @click.stop="sendEstimate('both')"
                                >Both</button>
                                <div class="ops-comms-menu__separator" role="separator"></div>
                                <template x-for="slot in (smsSchedule?.upcoming ?? [])" :key="'est-' + slot.day_key">
                                    <button
                                        type="button"
                                        role="menuitem"
                                        class="ops-comms-menu__item"
                                        :disabled="sending"
                                        @click.stop="scheduleEstimateForMorning(slot.scheduled_for)"
                                        x-text="slot.label"
                                    ></button>
                                </template>
                            </div>
                        </template>
                    </div>
                @endif
                @if ($canSendPayment)
                    <div class="ops-comms-menu" x-ref="paymentMenu">
                        <button
                            type="button"
                            x-ref="paymentMenuTrigger"
                            @click.stop="togglePaymentMenu()"
                            :disabled="sending"
                            class="ops-comms-menu__trigger"
                            :aria-expanded="paymentMenuOpen"
                            aria-haspopup="menu"
                        >
                            <span>Send Pay Link</span>
                            <span class="ops-comms-menu__caret" aria-hidden="true">▾</span>
                        </button>
                        <template x-teleport="body">
                            <div
                                x-show="paymentMenuOpen"
                                x-ref="paymentMenuPanel"
                                :style="paymentMenuStyle"
                                class="ops-comms-menu__panel ops-comms-menu__panel--floating"
                                role="menu"
                                @click.stop
                            >
                                <button
                                    type="button"
                                    role="menuitem"
                                    class="ops-comms-menu__item"
                                    :class="{ 'opacity-50': ! canSmsPayment && ! sending }"
                                    :disabled="sending"
                                    @click.stop="sendPaymentLink('sms')"
                                >SMS</button>
                                <button
                                    type="button"
                                    role="menuitem"
                                    class="ops-comms-menu__item"
                                    :class="{ 'opacity-50': ! canEmailPayment && ! sending }"
                                    :disabled="sending"
                                    @click.stop="sendPaymentLink('email')"
                                >Email</button>
                                <button
                                    type="button"
                                    role="menuitem"
                                    class="ops-comms-menu__item"
                                    :class="{ 'opacity-50': (! canSmsPayment || ! canEmailPayment) && ! sending }"
                                    :disabled="sending"
                                    @click.stop="sendPaymentLink('both')"
                                >Both</button>
                            </div>
                        </template>
                    </div>
                @endif
                @if ($canSendDeposit)
                    <div class="ops-comms-menu" x-ref="depositMenu">
                        <div class="flex items-center gap-1.5">
                            <label class="sr-only" for="ark-deposit-amount-{{ $customer->id }}">Deposit amount</label>
                            <span class="text-xs font-semibold text-slate-500" aria-hidden="true">$</span>
                            <input
                                id="ark-deposit-amount-{{ $customer->id }}"
                                type="number"
                                min="0.01"
                                step="0.01"
                                inputmode="decimal"
                                x-model="depositAmount"
                                @click.stop
                                class="h-8 w-20 rounded-sm border border-slate-300 bg-white px-2 text-xs font-semibold text-slate-900 tabular-nums"
                                placeholder="0.00"
                            />
                            <button
                                type="button"
                                x-ref="depositMenuTrigger"
                                @click.stop="toggleDepositMenu()"
                                :disabled="sending"
                                class="ops-comms-menu__trigger"
                                :aria-expanded="depositMenuOpen"
                                aria-haspopup="menu"
                            >
                                <span>Send Deposit</span>
                                <span class="ops-comms-menu__caret" aria-hidden="true">▾</span>
                            </button>
                        </div>
                        <template x-teleport="body">
                            <div
                                x-show="depositMenuOpen"
                                x-ref="depositMenuPanel"
                                :style="depositMenuStyle"
                                class="ops-comms-menu__panel ops-comms-menu__panel--floating"
                                role="menu"
                                @click.stop
                            >
                                <button
                                    type="button"
                                    role="menuitem"
                                    class="ops-comms-menu__item"
                                    :class="{ 'opacity-50': ! canSmsDeposit && ! sending }"
                                    :disabled="sending"
                                    @click.stop="sendDepositRequest('sms')"
                                >SMS</button>
                                <button
                                    type="button"
                                    role="menuitem"
                                    class="ops-comms-menu__item"
                                    :class="{ 'opacity-50': ! canEmailDeposit && ! sending }"
                                    :disabled="sending"
                                    @click.stop="sendDepositRequest('email')"
                                >Email</button>
                                <button
                                    type="button"
                                    role="menuitem"
                                    class="ops-comms-menu__item"
                                    :class="{ 'opacity-50': (! canSmsDeposit || ! canEmailDeposit) && ! sending }"
                                    :disabled="sending"
                                    @click.stop="sendDepositRequest('both')"
                                >Both</button>
                            </div>
                        </template>
                    </div>
                @endif
                @if ($canSendInspection)
                    <button
                        type="button"
                        class="h-8 rounded-sm border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950"
                        :class="{ 'opacity-50': ! canSmsInspection && ! sending }"
                        :disabled="sending"
                        @click.stop="sendInspectionLink()"
                    >Send Inspection</button>
                @endif
                @if ($scheduleHref)
                    <a
                        href="{{ $scheduleHref }}"
                        class="inline-flex h-8 items-center rounded-sm border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950"
                    >Schedule</a>
                @endif
                @if ($messageActions !== [] || $callback['show_callback_action'])
                    <div class="ops-comms-menu" x-ref="moreMenu">
                        <button
                            type="button"
                            x-ref="moreMenuTrigger"
                            @click.stop="toggleMoreMenu()"
                            :disabled="sending"
                            class="ops-comms-menu__trigger"
                            :aria-expanded="moreMenuOpen"
                            aria-haspopup="menu"
                        >
                            <span>More</span>
                            <span class="ops-comms-menu__caret" aria-hidden="true">▾</span>
                        </button>
                        <template x-teleport="body">
                            <div
                                x-show="moreMenuOpen"
                                x-ref="moreMenuPanel"
                                :style="moreMenuStyle"
                                class="ops-comms-menu__panel ops-comms-menu__panel--floating"
                                role="menu"
                                @click.stop
                            >
                                @foreach ($messageActions as $messageAction)
                                    <button
                                        type="button"
                                        role="menuitem"
                                        class="ops-comms-menu__item"
                                        @if (($messageAction['color'] ?? 'neutral') !== 'neutral') style="box-shadow: inset 3px 0 0 {{ \App\Ark\Operations\Communications\CommunicationsAccentColor::swatch($messageAction['color']) }}" @endif
                                        :disabled="sending"
                                        @click.stop="moreMenuOpen = false; sendMessageAction(@js($messageAction['key']))"
                                    >{{ $messageAction['label'] }}</button>
                                @endforeach
                                @if ($callback['show_callback_action'])
                                    <button
                                        type="button"
                                        role="menuitem"
                                        class="ops-comms-menu__item"
                                        @click.stop="moreMenuOpen = false"
                                        onclick="window.arkInitiateTelephonyCallback?.({
                                            customerId: {{ $customer->id }},
                                            repairOrderId: {{ filled($repairOrderId) ? (int) $repairOrderId : 'null' }},
                                            button: this,
                                        })"
                                    >Callback</button>
                                @endif
                            </div>
                        </template>
                    </div>
                @endif
                @if ($showSmsComposer)
                    <label class="h-8 cursor-pointer rounded-sm border border-slate-300 bg-white px-2.5 text-xs font-semibold leading-8 text-slate-700 hover:border-slate-400 hover:text-slate-950">
                        Attach Picture?
                        <input
                            x-ref="attachmentInput"
                            type="file"
                            class="hidden"
                            accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,application/pdf"
                            @change="pickAttachment($event)"
                        >
                    </label>
                    <span x-show="attachmentLabel" x-cloak class="truncate text-[11px] font-medium text-slate-500" x-text="attachmentLabel"></span>
                    <button
                        type="button"
                        x-show="attachmentLabel"
                        x-cloak
                        @click="clearAttachment()"
                        class="text-[11px] font-semibold text-slate-500 hover:text-slate-800"
                    >
                        Remove
                    </button>
                @endif
                @endif
            </div>

            <div x-show="vinWarningOpen" x-cloak class="ops-estimate-vin-warning mx-3 mb-2">
                <p class="ops-estimate-vin-warning-title">VIN missing</p>
                <p class="ops-estimate-vin-warning-copy">
                    Parts lookup, labor guides, service history accuracy, and OEM information may be affected.
                </p>
                <div class="ops-estimate-vin-warning-actions">
                    <a :href="addVinUrl()" class="ops-estimate-email-form-btn ops-estimate-email-form-btn--secondary" @click="cancelVinWarning()">Add VIN</a>
                    <button type="button" class="ops-estimate-email-form-btn ops-estimate-email-form-btn--primary" @click="continueWithoutVin()">Continue anyway</button>
                </div>
            </div>

            <div
                x-show="showSmsComposer && open"
                x-cloak
                @class([
                    'grid gap-1.5 border-t border-slate-200 px-3 py-2',
                    'ops-comms-composer-stack__input' => $actionsMenu,
                ])
            >
                <textarea
                    x-ref="replyBody"
                    x-model="body"
                    rows="2"
                    @if (request()->query('compose') === 'text') autofocus @endif
                    placeholder="{{ $hasConversationHistory ? 'Message or MMS to '.$customer->display_phone : 'First text or MMS to '.$customer->display_phone }}"
                    :placeholder="composerPlaceholder()"
                    class="w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950 placeholder:text-slate-400"
                    @keydown.meta.enter.prevent="send()"
                    @keydown.ctrl.enter.prevent="send()"
                ></textarea>
                <div class="flex items-center justify-between gap-2">
                    @if ($showQuickReplies)
                        @include('operations.communications.workspace.partials.quick-replies')
                    @else
                        <span></span>
                    @endif
                </div>
                <p x-show="error" x-cloak class="text-xs font-semibold text-rose-700" x-text="error"></p>
            </div>
            </div>
            <div x-show="error && ! open" x-cloak class="mx-3 mb-2 rounded-sm border border-rose-200 bg-rose-50 px-2.5 py-2 text-xs text-rose-950" role="alert">
                <p class="font-semibold">Could not send</p>
                <p class="mt-0.5 leading-4" x-text="error"></p>
            </div>
        </div>
    @elseif (! filled($customer->phone) && ! filled($customer->email))
        <div {{ $attributes->class(['border-t border-slate-200 px-3 py-2 text-xs text-slate-500']) }}>
            Add a phone number or email to reach this customer.
        </div>
    @endif

    @if ($canSendMessenger)
        <div
            data-ark-workspace-dirty="off"
            data-ark-conversation-composer
            x-data="arkConversationQuickReply(@js([
                'sendUrl' => route('operations.customers.conversation-messages.store', $customer),
                'channel' => 'messenger',
                'repairOrderId' => $repairOrderId,
                'customerId' => $customer->id,
                'messagesListIds' => $messagesListIds,
                'hasConversationHistory' => $hasConversationHistory,
                'autoOpenComposer' => $composeMessenger || $alwaysOpen || $messengerAlwaysOpen,
                'alwaysOpen' => $alwaysOpen || $messengerAlwaysOpen,
                'keepOpenAfterSend' => $keepOpenAfterSend,
                'messengerDisplay' => $customer->name,
                'messengerMessageTags' => $messengerMessageTags,
                'defaultMessengerMessageTag' => $messengerConfig->outsideWindowTag()?->value,
            ]))"
            {{ $attributes->class(['border-t border-slate-200 bg-slate-50/40']) }}
        >
            <div class="flex flex-wrap items-center gap-2 px-3 py-2">
                @unless ($alwaysOpen || $messengerAlwaysOpen)
                    <button
                        type="button"
                        @click="toggleReply()"
                        class="h-8 rounded-sm border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950"
                    >
                        <span x-show="! open" x-cloak>Reply on Messenger</span>
                        <span x-show="open" x-cloak>Cancel</span>
                    </button>
                @endunless
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
                <span x-show="attachmentLabel" x-cloak class="truncate text-[11px] font-medium text-slate-500" x-text="attachmentLabel"></span>
                <button
                    type="button"
                    x-show="attachmentLabel"
                    x-cloak
                    @click="clearAttachment()"
                    class="text-[11px] font-semibold text-slate-500 hover:text-slate-800"
                >
                    Remove
                </button>
            </div>

            <div x-show="open" x-cloak class="grid gap-2 border-t border-slate-200 px-3 py-2">
                <label class="block text-[11px] font-semibold text-slate-600">
                    Outside 24h tag
                    <select
                        x-model="messengerMessageTag"
                        class="mt-1 h-8 w-full rounded-sm border border-slate-300 bg-white px-2 text-xs font-semibold text-slate-700"
                    >
                        <option value="">Use shop default / standard reply</option>
                        <template x-for="tag in messengerMessageTags" :key="tag.value">
                            <option :value="tag.value" x-text="tag.label"></option>
                        </template>
                    </select>
                </label>
                <textarea
                    x-ref="replyBody"
                    x-model="body"
                    rows="{{ ($alwaysOpen || $messengerAlwaysOpen) ? 3 : 2 }}"
                    placeholder="Reply on Messenger to {{ $customer->name }}"
                    class="w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950 placeholder:text-slate-400"
                    @keydown.meta.enter.prevent="send()"
                    @keydown.ctrl.enter.prevent="send()"
                ></textarea>
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[11px] leading-4 text-slate-500" x-text="composerHelper()"></p>
                    <button
                        type="button"
                        @click="send()"
                        :disabled="sending"
                        class="h-8 shrink-0 rounded-sm border border-slate-800 bg-slate-900 px-3 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-60"
                    >
                        <span x-show="! sending" x-text="sendButtonLabel()">Send Messenger</span>
                        <span x-show="sending" x-cloak>Sending…</span>
                    </button>
                </div>
                <p x-show="error" x-cloak class="text-xs font-semibold text-rose-700" x-text="error"></p>
            </div>
            <p x-show="error && ! open" x-cloak class="px-3 pb-2 text-xs font-semibold text-rose-700" x-text="error"></p>
        </div>
    @endif
@endcan
