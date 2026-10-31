@php
    $meaning = new App\Ark\Operations\RepairOrders\RepairOrderConcernMeaningPresentation($concern);
@endphp

@if ($meaning->showsMeaningStrip())
    <div class="text-secondary small mt-1">
        @if ($meaning->showsCustomerWording())
            <span>{{ $meaning->customerWording() }}</span>
        @endif
    </div>
@endif
