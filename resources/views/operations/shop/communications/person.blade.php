@php
    /** @var array{user: \App\Models\User, devices: \Illuminate\Support\Collection<int, \App\Ark\Operations\Communications\CommunicationDevice>} $context */
    $user = $context['user'];
    $devices = $context['devices'];
@endphp

<x-operations.app :title="$user->name.' · Shop'">
    <div class="mx-auto max-w-3xl space-y-4 px-4 py-4">
        <header class="space-y-1 border-b border-slate-200 pb-3">
            <a href="{{ route('operations.shop.communications') }}" class="text-xs font-semibold text-sky-700">← Stations &amp; Phones</a>
            <h1 class="text-xl font-black text-slate-950">{{ $user->name }}</h1>
            @if (session('status'))
                <p class="text-sm font-semibold text-emerald-700">{{ session('status') }}</p>
            @endif
        </header>

        <section class="space-y-3">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Assigned Devices</p>

            <ul class="divide-y divide-slate-100 rounded-sm border border-slate-200">
                @forelse ($devices as $device)
                    <li>
                        <a
                            href="{{ route('operations.shop.devices.show', $device) }}"
                            class="flex items-center justify-between gap-3 px-3 py-2.5 hover:bg-slate-50"
                        >
                            <div>
                                <p class="text-sm font-black text-slate-950">{{ $device->name }}</p>
                                <p class="text-xs text-slate-600">{{ $device->summaryLabel() }}</p>
                            </div>
                            <span class="text-xs font-semibold text-slate-400">Open</span>
                        </a>
                    </li>
                @empty
                    <li class="px-3 py-3 text-sm text-slate-600">No devices assigned to this person yet.</li>
                @endforelse
            </ul>

            <form method="POST" action="{{ route('operations.shop.devices.store') }}" class="space-y-3 rounded-sm border border-slate-200 bg-slate-50/60 p-3">
                @csrf
                <input type="hidden" name="assigned_user_id" value="{{ $user->id }}">
                <input type="hidden" name="provider" value="{{ App\Ark\Operations\Communications\CommunicationDeviceProvider::ShopPhone->value }}">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Add Device</p>
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-slate-700">Name</span>
                    <input
                        type="text"
                        name="name"
                        required
                        placeholder="Front Desk VVX450"
                        class="w-full rounded-sm border-slate-300 text-sm"
                    >
                </label>
                <button type="submit" class="rounded-sm bg-slate-900 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-white">
                    Add Device
                </button>
            </form>
        </section>
    </div>
</x-operations.app>
