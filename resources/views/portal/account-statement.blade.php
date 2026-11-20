<x-portal.app>
    <div class="container py-4">
        <h1 class="h3 mb-3">Account statement</h1>
        <p class="text-secondary">Balances and invoices from your shop account.</p>

        @include('operations.financials.accounts-receivable.partials.statement-body', [
            'statement' => $statement,
            'combined' => false,
            'money' => $money,
        ])
    </div>
</x-portal.app>
