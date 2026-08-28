@php
    $defaultLaborCategory = $settings->defaultLaborCategory();
    $defaultVisitMode = $settings->default_visit_mode ?? \App\Ark\Operations\RepairOrders\RepairOrderVisitMode::DropOff->value;
@endphp

<form method="POST" action="{{ route('operations.settings.shop.workflow.update') }}" class="mt-4">
    @csrf
    @method('PATCH')
    <div class="space-y-4">
        <div class="border border-slate-200">
            <p class="border-b border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-bold uppercase tracking-wide text-slate-500">Check In visit</p>
            <div class="grid gap-4 px-3 py-3 md:grid-cols-2">
                <label class="block text-xs font-medium text-slate-500">
                    Default visit posture
                    <select name="default_visit_mode" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                        @foreach (\App\Ark\Operations\RepairOrders\RepairOrderVisitMode::cases() as $mode)
                            <option value="{{ $mode->value }}" @selected(old('default_visit_mode', $defaultVisitMode) === $mode->value)>{{ $mode->label() }}</option>
                        @endforeach
                    </select>
                    <span class="mt-1 block font-normal leading-4 text-slate-500">Pre-selected when opening a repair order from Check In.</span>
                </label>
                <div class="text-xs leading-5 text-slate-500">
                    <p class="font-semibold text-slate-700">Account posture at Check In</p>
                    <p class="mt-1">Fleet and warranty billing follow the billing class. Advisors can override per visit at Check In.</p>
                    <p class="mt-2">Fees and parts matrices: <button type="button" @click="setActive('financial'); setFinancialTab('billing-classes')" class="font-semibold text-slate-800 underline decoration-slate-300 hover:text-slate-950">Financial Rules → Billing classes</button>.</p>
                </div>
            </div>
        </div>

        <div class="border border-slate-200">
            <p class="border-b border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-bold uppercase tracking-wide text-slate-500">Scopes &amp; concerns</p>
            <div class="grid gap-4 px-3 py-3 md:grid-cols-2">
                <label class="block text-xs font-medium text-slate-500">
                    Default recommendation intent
                    @include('operations.repair-orders.partials.recommendation-intent-select', [
                        'fieldName' => 'default_recommendation_intent',
                        'selected' => old('default_recommendation_intent', $settings->default_recommendation_intent),
                        'inputId' => 'default_recommendation_intent',
                        'selectClass' => 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950',
                    ])
                    <span class="mt-1 block font-normal leading-4 text-slate-500">Applied to new scopes at Check In and on the estimate worksheet.</span>
                </label>
                <div class="text-xs leading-5 text-slate-500">
                    <p class="font-semibold text-slate-700">Default labor category</p>
                    <p class="mt-1"><span class="font-semibold text-slate-800">{{ $defaultLaborCategory['name'] }}</span> at ${{ number_format($defaultLaborCategory['rate_cents'] / 100, 2) }}/hr — <button type="button" @click="setActive('financial'); setFinancialTab('labor')" class="font-semibold text-slate-800 underline decoration-slate-300 hover:text-slate-950">Financial Rules → Labor</button>.</p>
                    <p class="mt-2">RO lifecycle status is configured under <button type="button" @click="setWorkflowTab('statuses')" class="font-semibold text-slate-800 underline decoration-slate-300 hover:text-slate-950">RO statuses</button>.</p>
                </div>
            </div>
        </div>

        <div class="border border-slate-200">
            <p class="border-b border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-bold uppercase tracking-wide text-slate-500">Worksheet notes</p>
            <div class="px-3 py-3">
                <label class="flex items-start gap-2 text-xs font-medium text-slate-500">
                    <input type="hidden" name="default_notes_private" value="0">
                    <input
                        type="checkbox"
                        name="default_notes_private"
                        value="1"
                        @checked(old('default_notes_private', $settings->default_notes_private))
                        class="mt-0.5 rounded border-slate-300 text-slate-800"
                    >
                    <span>
                        New notes default to Advisor only
                        <span class="block font-normal text-slate-500">When checked, Technician and Customer stay off until an advisor opts them in. When unchecked, Customer is also selected by default.</span>
                    </span>
                </label>
            </div>
        </div>
    </div>
    <div class="mt-6 flex justify-end border-t border-slate-200 pt-4">
        <button type="submit" class="min-h-10 rounded-md bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
            Save Check In defaults
        </button>
    </div>
</form>
