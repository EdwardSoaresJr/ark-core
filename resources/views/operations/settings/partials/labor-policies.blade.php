{{-- Legacy Labor Policies tab — consolidated into Labor Settings. --}}
<div class="alert alert-info" role="status">
    Labor Policies now live under <strong>Financial Rules → Labor</strong>
    (explicit hourly rates, billing minimums, and Round To per work type × billing class).
    <button type="button" class="btn btn-link p-0 align-baseline" @click="setFinancialTab('labor')">
        Open Labor Settings
    </button>
</div>
@include('operations.settings.partials.labor-policies-matrix')
