<x-portal.app>
    <div class="container py-4">
        <h1 class="h3 mb-3">Account statement</h1>
        <p class="text-secondary">Balances and invoices from your shop account.</p>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="mb-3 d-flex flex-wrap gap-2">
            <a href="{{ route('portal.account.pay') }}" class="btn btn-primary">Pay invoices</a>
            <a href="{{ route('portal.home') }}" class="btn btn-outline-secondary">Home</a>
        </div>

        @include('operations.financials.accounts-receivable.partials.statement-body', [
            'statement' => $statement,
            'combined' => false,
            'money' => $money,
        ])
    </div>
</x-portal.app>
