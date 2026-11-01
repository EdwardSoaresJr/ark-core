@can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
    @unless ($isTerminal ?? false)
        <details id="comms-workflow-log" class="card card-sm">
            <summary class="card-body">
                <span class="d-block subheader">Workflow note</span>
                <span class="d-block small text-secondary">Staff-only posture - does not appear on the timeline below.</span>
            </summary>
            <form
                method="POST"
                action="{{ route('operations.repair-orders.communications.store', $repairOrder) }}"
                data-refresh-scope="comms"
                data-saving-label="Logging workflow note…"
                data-continuity-focus="#comms-workflow-log textarea[name='summary']"
                @submit.prevent="window.arkWorksheetFormSubmit($event)"
                class="card-body border-top"
            >
                @csrf
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <div class="row g-2 mb-2">
                    <div class="col-12 col-sm">
                        <select name="communication_type" required class="form-select form-select-sm" aria-label="Communication type">
                            @foreach (App\Ark\Operations\Communications\OperationalCommunicationType::cases() as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-sm">
                        <select name="channel" required class="form-select form-select-sm" aria-label="Channel">
                            @foreach (App\Ark\Operations\Communications\OperationalCommunicationChannel::cases() as $channel)
                                <option value="{{ $channel->value }}">{{ $channel->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-sm">
                        <select name="direction" required class="form-select form-select-sm" aria-label="Direction">
                            @foreach (App\Ark\Operations\Communications\OperationalCommunicationDirection::cases() as $direction)
                                <option value="{{ $direction->value }}">{{ $direction->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-sm-auto">
                        <button type="submit" class="btn btn-sm w-100">
                            Log
                        </button>
                    </div>
                </div>
                <textarea name="summary" rows="2" required placeholder="Estimate viewed, unreachable, left voicemail, etc." aria-label="Workflow note" class="form-control"></textarea>
            </form>
        </details>
    @endunless
@endcan
