<x-operations.app :title="'Statement · '.$customer->name" ui="tabler">
    <x-tabler.page
        layout="fluid"
        title="Account Statement"
        pretitle="{{ $customer->name }}"
        :breadcrumbs="[
            ['label' => 'Accounts Receivable', 'url' => route('operations.financials.accounts-receivable.index')],
            ['label' => $customer->name, 'url' => route('operations.financials.accounts-receivable.customer', $customer)],
            ['label' => 'Statement'],
        ]"
    >
        @if (session('status'))
            <div class="alert alert-info">{{ session('status') }}</div>
        @endif

        <div class="d-flex flex-wrap gap-2 mb-3 no-print">
            <button type="button" class="btn btn-primary" onclick="window.print()">Print Statement</button>
            <a href="{{ route('operations.financials.accounts-receivable.statement.pdf', $customer) }}" class="btn btn-ghost-secondary">Download PDF</a>
            <a href="{{ route('operations.financials.accounts-receivable.statement.label', $customer) }}" class="btn btn-ghost-secondary" target="_blank">Print Address Label</a>
            @if ($customer->hasEmail())
                <form method="POST" action="{{ route('operations.financials.accounts-receivable.statement.email', $customer) }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost-secondary">Email Statement</button>
                </form>
            @endif
            <a href="{{ route('operations.financials.accounts-receivable.customer', $customer) }}" class="btn btn-ghost-secondary">Back to account</a>
        </div>

        <div class="card">
            <div class="card-body">
                @include('operations.financials.accounts-receivable.partials.statement-body', [
                    'statement' => $statement,
                    'combined' => false,
                    'money' => $money,
                ])
            </div>
        </div>
    </x-tabler.page>
</x-operations.app>
