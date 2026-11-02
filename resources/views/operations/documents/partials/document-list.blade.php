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
    <ul class="list-group list-group-flush" data-document-list>
        @foreach ($documents as $doc)
            <li
                class="list-group-item"
                x-data="{ emailOpen: false }"
                data-document-row="{{ $doc['id'] }}"
            >
                <div class="row g-2 align-items-start">
                    <div class="col-12 col-lg">
                        <div class="subheader">{{ $doc['type_label'] }}</div>
                        <a
                            href="{{ $doc['viewer_url'] }}"
                            target="_blank"
                            rel="noopener"
                            class="fw-bold text-break"
                        >
                            {{ $doc['title'] }}
                        </a>
                        <div class="text-secondary small">
                            Added {{ $doc['created_label'] }}
                            @if ($doc['repair_order_number'] ?? null)
                                · RO #{{ $doc['repair_order_number'] }}
                            @endif
                            @if ($doc['customer_visible'] ?? false)
                                · <span class="badge bg-azure-lt">Customer visible</span>
                            @else
                                · Shop only
                            @endif
                            @if (($doc['email_send_count'] ?? 0) > 0 && ($doc['email_last_label'] ?? null))
                                · {{ $doc['email_last_label'] }}
                            @endif
                        </div>
                    </div>
                    <div class="col-12 col-lg-auto">
                        <div class="btn-list">
                            <a href="{{ $doc['viewer_url'] }}" target="_blank" rel="noopener" class="btn btn-sm">View</a>
                            <a href="{{ $doc['download_url'] }}" target="_blank" rel="noopener" class="btn btn-sm">Download</a>
                            <a href="{{ $doc['viewer_url'] }}" target="_blank" rel="noopener" class="btn btn-sm">Print</a>
                            @if ($canEmail)
                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary"
                                    @click="emailOpen = ! emailOpen"
                                    :aria-expanded="emailOpen ? 'true' : 'false'"
                                >Email</button>
                            @endif
                            @if ($canManage)
                                <form method="POST" action="{{ route('operations.customers.documents.visibility', [$customer, $doc['id']]) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="customer_visible" value="{{ ($doc['customer_visible'] ?? false) ? 0 : 1 }}">
                                    <button type="submit" class="btn btn-sm">
                                        {{ ($doc['customer_visible'] ?? false) ? 'Shop only' : 'Show to Customer' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('operations.customers.documents.retire', [$customer, $doc['id']]) }}" class="d-inline" onsubmit="return confirm('Retire this document? It will leave active lists.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Retire</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
                @if ($canEmail)
                    <div class="mt-2" x-show="emailOpen" x-cloak>
                        @include('operations.documents.partials.document-email-form', [
                            'customer' => $customer,
                            'doc' => $doc,
                            'compact' => true,
                            'ui' => 'tabler',
                        ])
                    </div>
                @endif
                @if ($showAttachRo && $activeRepairOrders->isNotEmpty() && empty($doc['repair_order_id']))
                    <form method="POST" action="{{ route('operations.customers.documents.attach', [$customer, $doc['id']]) }}" class="row g-2 align-items-end mt-1">
                        @csrf
                        <div class="col-12 col-sm-auto">
                            <label class="form-label small mb-1" for="document-attach-ro-{{ $doc['id'] }}">Attach to current RO</label>
                            <select id="document-attach-ro-{{ $doc['id'] }}" name="repair_order_id" required class="form-select form-select-sm">
                                @foreach ($activeRepairOrders as $ro)
                                    <option value="{{ $ro->id }}">RO #{{ $ro->repair_order_id }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-sm-auto">
                            <button type="submit" class="btn btn-sm">Attach</button>
                        </div>
                    </form>
                @endif
            </li>
        @endforeach
    </ul>
@endif
