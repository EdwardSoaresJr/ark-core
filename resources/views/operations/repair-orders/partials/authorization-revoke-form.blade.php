@php
    use App\Ark\Operations\Approvals\ApprovalSource;
    use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;

    $approvedConcerns = $repairOrder->concerns
        ->filter(fn ($concern) => $concern->disposition === RepairOrderConcernDisposition::Approved)
        ->values();
@endphp

@can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
    @unless ($isTerminal ?? false)
        @if ($approvedConcerns->isNotEmpty())
            <details class="mt-2 rounded-sm border border-amber-200 bg-amber-50/60">
                <summary class="cursor-pointer select-none px-2 py-1.5 text-[11px] font-semibold text-amber-950 hover:bg-amber-50">
                    Revoke authorization
                </summary>
                <form
                    method="POST"
                    action="{{ route('operations.repair-orders.authorization.revoke', [$repairOrder, $approvalEvent]) }}"
                    data-refresh-scope="auth"
                    data-saving-label="Revoking authorization…"
                    @submit.prevent="window.arkWorksheetFormSubmit($event)"
                    class="space-y-2 border-t border-amber-200 px-2 py-2"
                >
                    @csrf
                    <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                    <p class="text-[11px] leading-4 text-amber-900">Customer changed their mind or the authorization was recorded incorrectly. Reverts selected approved scopes and records the revocation.</p>

                    <div class="space-y-1">
                        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-amber-800">Scopes to revoke</p>
                        @foreach ($approvedConcerns as $concern)
                            <label class="flex items-start gap-2 text-xs text-amber-950">
                                <input
                                    type="checkbox"
                                    name="concern_ids[]"
                                    value="{{ $concern->id }}"
                                    checked
                                    class="mt-0.5 rounded border-amber-300 text-amber-950 focus:ring-amber-500"
                                >
                                <span>{{ $concern->summary }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div class="grid gap-2">
                        <label class="block text-[11px] font-medium text-amber-900">
                            Return scopes to
                            <select name="revert_disposition" required class="mt-1 w-full rounded-sm border border-amber-300 bg-white px-2 py-1.5 text-sm text-slate-950">
                                <option value="{{ RepairOrderConcernDisposition::Recommended->value }}" @selected(old('revert_disposition', RepairOrderConcernDisposition::Recommended->value) === RepairOrderConcernDisposition::Recommended->value)>Recommended (pending again)</option>
                                <option value="{{ RepairOrderConcernDisposition::Deferred->value }}" @selected(old('revert_disposition') === RepairOrderConcernDisposition::Deferred->value)>Deferred</option>
                            </select>
                        </label>

                        <label class="block text-[11px] font-medium text-amber-900">
                            Method
                            <select name="source" required class="mt-1 w-full rounded-sm border border-amber-300 bg-white px-2 py-1.5 text-sm text-slate-950">
                                @foreach (ApprovalSource::cases() as $source)
                                    <option value="{{ $source->value }}" @selected(old('source', ApprovalSource::Phone->value) === $source->value)>{{ $source->label() }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block text-[11px] font-medium text-amber-900">
                            Revoked by
                            <input
                                type="text"
                                name="revoked_by"
                                value="{{ old('revoked_by', $repairOrder->customer->name) }}"
                                required
                                class="mt-1 w-full rounded-sm border border-amber-300 bg-white px-2 py-1.5 text-sm text-slate-950"
                            >
                        </label>

                        <label class="block text-[11px] font-medium text-amber-900">
                            Notes
                            <textarea name="notes" rows="2" class="mt-1 w-full rounded-sm border border-amber-300 bg-white px-2 py-1.5 text-sm text-slate-950" placeholder="Optional revocation notes">{{ old('notes') }}</textarea>
                        </label>
                    </div>

                    @error('authorization')
                        <p class="text-xs text-red-700">{{ $message }}</p>
                    @enderror

                    <button type="submit" class="w-full rounded-sm border border-amber-400 bg-white px-3 py-2 text-sm font-semibold text-amber-950 hover:bg-amber-100">
                        Revoke this authorization
                    </button>
                </form>
            </details>
        @else
            <p class="mt-2 text-[11px] leading-4 text-slate-500">No approved scopes remain to revoke for this authorization.</p>
        @endif
    @endunless
@endcan
