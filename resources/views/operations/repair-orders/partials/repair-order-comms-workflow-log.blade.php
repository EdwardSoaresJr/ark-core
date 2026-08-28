@can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
    @unless ($isTerminal ?? false)
        <details id="comms-workflow-log" class="group border-b border-slate-100">
            <summary class="cursor-pointer list-none px-3 py-2 marker:content-none">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.08em] text-slate-500">Workflow note</p>
                        <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Staff-only posture — does not appear on the timeline below.</p>
                    </div>
                    <span class="shrink-0 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 group-open:hidden">Expand</span>
                    <span class="shrink-0 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 hidden group-open:inline">Collapse</span>
                </div>
            </summary>
            <form
                method="POST"
                action="{{ route('operations.repair-orders.communications.store', $repairOrder) }}"
                data-refresh-scope="comms"
                data-saving-label="Logging workflow note…"
                data-continuity-focus="#comms-workflow-log textarea[name='summary']"
                @submit.prevent="window.arkWorksheetFormSubmit($event)"
                class="grid gap-2 border-t border-slate-100 px-3 py-2"
            >
                @csrf
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto] items-center gap-2">
                    <select name="communication_type" required class="h-9 min-w-0 rounded-sm border-slate-300 bg-white py-1 pl-2 pr-8 text-xs font-semibold text-slate-700">
                        @foreach (App\Ark\Operations\Communications\OperationalCommunicationType::cases() as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    <select name="channel" required class="h-9 min-w-0 rounded-sm border-slate-300 bg-white py-1 pl-2 pr-8 text-xs font-semibold text-slate-700">
                        @foreach (App\Ark\Operations\Communications\OperationalCommunicationChannel::cases() as $channel)
                            <option value="{{ $channel->value }}">{{ $channel->label() }}</option>
                        @endforeach
                    </select>
                    <select name="direction" required class="h-9 min-w-0 rounded-sm border-slate-300 bg-white py-1 pl-2 pr-8 text-xs font-semibold text-slate-700">
                        @foreach (App\Ark\Operations\Communications\OperationalCommunicationDirection::cases() as $direction)
                            <option value="{{ $direction->value }}">{{ $direction->label() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="h-9 shrink-0 rounded-sm border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950">
                        Log
                    </button>
                </div>
                <textarea name="summary" rows="2" required placeholder="Estimate viewed, unreachable, left voicemail, etc." class="w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950 placeholder:text-slate-400"></textarea>
            </form>
        </details>
    @endunless
@endcan
