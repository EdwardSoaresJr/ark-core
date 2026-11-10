{{-- Legacy Labor Policies tab — consolidated into Labor Settings. --}}
<div class="alert alert-info" role="status">
    Labor Policies now live under <strong>Financial Rules → Labor</strong>
    (Standard rate, Labor categories, and Advanced billing rates).
    <button type="button" class="btn btn-link p-0 align-baseline" @click="setFinancialTab('labor')">
        Open Labor Settings
    </button>
</div>
@include('operations.settings.partials.labor-policies-matrix')
