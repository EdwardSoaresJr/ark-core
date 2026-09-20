@if ($operationalNotes !== [] && in_array($communicationsTab, ['hours', 'recording', 'ring'], true))
    <div class="rounded-sm border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-950">
        <ul class="list-disc space-y-1 pl-4">
            @foreach ($operationalNotes as $note)
                <li>{{ $note }}</li>
            @endforeach
        </ul>
        <p class="mt-2 text-[11px] text-amber-900">
            Full telephony status and webhook URLs are on the
            <a href="{{ route('operations.settings.shop.edit', ['section' => 'communications', 'communications-tab' => 'general']) }}" class="font-semibold underline decoration-amber-400 hover:text-amber-950">General</a>
            tab.
        </p>
    </div>
@endif
