{{--
  Compact document list. Empty → render nothing (caller shows + Add Document only).
  Props: $documents, $customer, $showAttachRo, $activeRepairOrders
--}}
@php
    $documents = $documents ?? collect();
    $showAttachRo = (bool) ($showAttachRo ?? false);
    $activeRepairOrders = $activeRepairOrders ?? collect();
    $canManage = auth()->user()?->can(\App\Ark\Runtime\Authorization\ArkCapability::CustomersManage->value);
    $canEmail = $canManage
        || auth()->user()?->can(\App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value);
@endphp

@if ($documents->isNotEmpty())
    <ul class="divide-y divide-slate-100">
        @foreach ($documents as $doc)
            <li
                class="px-3 py-3"
                x-data="{ emailOpen: false }"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $doc['type_label'] }}</p>
                        <a
                            href="{{ $doc['viewer_url'] }}"
                            target="_blank"
                            rel="noopener"
                            class="mt-0.5 block text-sm font-semibold text-slate-950 underline-offset-2 hover:underline"
                        >
                            {{ $doc['title'] }}
                        </a>
                        <p class="mt-0.5 text-xs text-slate-600">
                            Added {{ $doc['created_label'] }}
                            @if ($doc['repair_order_number'] ?? null)
                                <span class="mx-1 text-slate-300">·</span>
                                RO #{{ $doc['repair_order_number'] }}
                            @endif
                            @if ($doc['customer_visible'] ?? false)
                                <span class="mx-1 text-slate-300">·</span>
                                <span class="text-sky-800">Customer visible</span>
                            @else
                                <span class="mx-1 text-slate-300">·</span>
                                Shop only
                            @endif
                            @if (($doc['email_send_count'] ?? 0) > 0 && ($doc['email_last_label'] ?? null))
                                <span class="mx-1 text-slate-300">·</span>
                                <span class="text-slate-700">{{ $doc['email_last_label'] }}</span>
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <a href="{{ $doc['viewer_url'] }}" target="_blank" rel="noopener" class="inline-flex min-h-8 items-center rounded-sm border border-slate-300 px-2 text-[11px] font-semibold text-slate-700 hover:border-slate-500">View</a>
                        <a href="{{ $doc['download_url'] }}" target="_blank" rel="noopener" class="inline-flex min-h-8 items-center rounded-sm border border-slate-300 px-2 text-[11px] font-semibold text-slate-700 hover:border-slate-500">Download</a>
                        <a href="{{ $doc['viewer_url'] }}" target="_blank" rel="noopener" class="inline-flex min-h-8 items-center rounded-sm border border-slate-300 px-2 text-[11px] font-semibold text-slate-700 hover:border-slate-500">Print</a>
                        @if ($canEmail)
                            <button
                                type="button"
                                class="inline-flex min-h-8 items-center rounded-sm border border-slate-800 bg-slate-900 px-2 text-[11px] font-semibold text-white hover:bg-slate-800"
                                @click="emailOpen = ! emailOpen"
                            >Email</button>
                        @endif
                        @if ($canManage)
                            <form method="POST" action="{{ route('operations.customers.documents.visibility', [$customer, $doc['id']]) }}" class="inline">
                                @csrf
                                <input type="hidden" name="customer_visible" value="{{ ($doc['customer_visible'] ?? false) ? 0 : 1 }}">
                                <button type="submit" class="inline-flex min-h-8 items-center rounded-sm border border-slate-300 px-2 text-[11px] font-semibold text-slate-700 hover:border-slate-500">
                                    {{ ($doc['customer_visible'] ?? false) ? 'Shop only' : 'Show to Customer' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('operations.customers.documents.retire', [$customer, $doc['id']]) }}" class="inline" onsubmit="return confirm('Retire this document? It will leave active lists.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex min-h-8 items-center rounded-sm border border-rose-200 px-2 text-[11px] font-semibold text-rose-800 hover:border-rose-400">Retire</button>
                            </form>
                        @endif
                    </div>
                </div>
                @if ($canEmail)
                    <div class="mt-2" x-show="emailOpen" x-cloak>
                        @include('operations.documents.partials.document-email-form', [
                            'customer' => $customer,
                            'doc' => $doc,
                            'compact' => true,
                        ])
                    </div>
                @endif
                @if ($showAttachRo && $activeRepairOrders->isNotEmpty() && empty($doc['repair_order_id']))
                    <form method="POST" action="{{ route('operations.customers.documents.attach', [$customer, $doc['id']]) }}" class="mt-2 flex flex-wrap items-end gap-2">
                        @csrf
                        <label class="block text-[11px] font-medium text-slate-600">
                            Attach to current RO
                            <select name="repair_order_id" required class="mt-0.5 block min-w-[12rem] rounded-md border border-slate-300 px-2 py-1 text-sm">
                                @foreach ($activeRepairOrders as $ro)
                                    <option value="{{ $ro->id }}">RO #{{ $ro->repair_order_id }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="inline-flex min-h-8 items-center rounded-sm border border-slate-800 bg-slate-900 px-2 text-[11px] font-semibold text-white hover:bg-slate-800">Attach</button>
                    </form>
                @endif
            </li>
        @endforeach
    </ul>
@endif
