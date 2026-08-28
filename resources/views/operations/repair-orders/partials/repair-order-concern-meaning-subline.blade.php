@php
    $meaning = new App\Ark\Operations\RepairOrders\RepairOrderConcernMeaningPresentation($concern);
@endphp

@if ($meaning->showsMeaningStrip())
    <div class="ops-scope-meaning-subline">
        @if ($meaning->showsCustomerWording())
            <span class="ops-scope-meaning-subline__customer">{{ $meaning->customerWording() }}</span>
        @endif
    </div>
@endif
