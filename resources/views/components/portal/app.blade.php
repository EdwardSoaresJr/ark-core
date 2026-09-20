{{-- Internal alias — customer application uses x-customer.shell only. --}}
@props([
    'layout' => 'stack',
])

<x-customer.shell :layout="$layout" :show-nav="true" :show-footer="true">
    {{ $slot }}
</x-customer.shell>
