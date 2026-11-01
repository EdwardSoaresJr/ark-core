@php
    /** @var list<string> $missing */
@endphp

<x-operations.app title="Stations &amp; Phones" ui="tabler">
    <x-tabler.page layout="focused" title="Stations & Phones" subtitle="Database setup required">
        <div class="alert alert-warning" role="alert">
            <div class="fw-bold d-md-none">Database setup required</div>
            <div>
                This deployment is missing voice endpoint schema from the latest release. Voice setup cannot load until migrations run on production.
            </div>

            @if ($missing !== [])
                <div class="mt-3">
                    <div class="subheader">Missing</div>
                    <ul class="mt-1 mb-0 ps-4" style="list-style: disc">
                        @foreach ($missing as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">On the production app container</h2>
            </div>
            <div class="card-body p-2 p-md-3">
                <pre class="mb-0 overflow-auto">php artisan migrate --force --no-interaction
php artisan db:seed --class=CommunicationDeviceModelSeeder --force --no-interaction</pre>
            </div>
            <div class="card-footer small text-secondary">
                After migrations complete, reload this page. If errors persist, check <code>storage/logs/laravel.log</code> on the server.
            </div>
        </div>
    </x-tabler.page>
</x-operations.app>
