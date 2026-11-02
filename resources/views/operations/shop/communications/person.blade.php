@php
    /** @var array{user: \App\Models\User, devices: \Illuminate\Support\Collection<int, \App\Ark\Operations\Communications\CommunicationDevice>} $context */
    $user = $context['user'];
    $devices = $context['devices'];
@endphp

<x-operations.app :title="$user->name.' · Shop'" ui="tabler">
    <x-tabler.page layout="focused" :title="$user->name" pretitle="Stations & Phones" actions-label="Person commands" stack-actions>
        <x-slot:actions>
            <x-tabler.page-action :href="route('operations.shop.communications')" icon="arrow-left" label="Stations & Phones" />
        </x-slot:actions>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        <div class="d-flex flex-column gap-3">
            <section class="card" aria-labelledby="person-devices-title">
                <div class="card-header">
                    <h2 class="card-title" id="person-devices-title">Assigned Devices</h2>
                </div>
                <div class="list-group list-group-flush">
                    @forelse ($devices as $device)
                        <a
                            href="{{ route('operations.shop.devices.show', $device) }}"
                            class="list-group-item list-group-item-action"
                        >
                            <div class="d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <div class="fw-bold">{{ $device->name }}</div>
                                    <div class="small text-secondary">{{ $device->summaryLabel() }}</div>
                                </div>
                                <span class="small text-secondary">Open</span>
                            </div>
                        </a>
                    @empty
                        <div class="list-group-item text-secondary">No devices assigned to this person yet.</div>
                    @endforelse
                </div>
            </section>

            <form method="POST" action="{{ route('operations.shop.devices.store') }}" class="card">
                @csrf
                <input type="hidden" name="assigned_user_id" value="{{ $user->id }}">
                <input type="hidden" name="provider" value="{{ App\Ark\Operations\Communications\CommunicationDeviceProvider::ShopPhone->value }}">
                <div class="card-header">
                    <h2 class="card-title">Add Device</h2>
                </div>
                <div class="card-body p-2 p-md-3">
                    <label class="form-label" for="person-device-name">Name</label>
                    <input
                        id="person-device-name"
                        type="text"
                        name="name"
                        required
                        placeholder="Front Desk VVX450"
                        class="form-control"
                    >
                </div>
                <div class="card-footer d-grid d-sm-flex justify-content-sm-end">
                    <button type="submit" class="btn btn-primary">
                        Add Device
                    </button>
                </div>
            </form>
        </div>
    </x-tabler.page>
</x-operations.app>
