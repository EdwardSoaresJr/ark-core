@php
    $exceptionLines = $concern->lines->filter(
        fn ($line) => $line->isPart() || $line->type->isLabor(),
    );
@endphp

@if (! ($isTerminal ?? false) && $exceptionLines->isNotEmpty() && auth()->user()?->hasAnyRole(['admin', 'advisor']))
    <details class="ops-scope-settings__exception">
        <summary>Record exception</summary>
        <form
            method="POST"
            action="{{ route('operations.repair-orders.concerns.authorization-exceptions.store', [$repairOrder, $concern]) }}"
        >
            @csrf
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
            <p>This covers only the lines you select. It does not approve the work or record customer consent.</p>
            <label for="authorization-exception-reason-{{ $concern->id }}">Basis</label>
            <select id="authorization-exception-reason-{{ $concern->id }}" name="reason" required>
                @foreach (App\Ark\Operations\RepairOrders\AuthorizationExceptionReason::cases() as $reason)
                    <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                @endforeach
            </select>
            @foreach ($exceptionLines as $line)
                <label>
                    <input type="checkbox" name="line_ids[]" value="{{ $line->id }}">
                    {{ $line->description }}
                </label>
            @endforeach
            <label for="authorization-exception-note-{{ $concern->id }}">What this covers and why</label>
            <textarea id="authorization-exception-note-{{ $concern->id }}" name="note" required minlength="20" maxlength="2000" rows="3"></textarea>
            <button type="submit">Record exception</button>
        </form>
    </details>
@endif
