@php
    /** @var array<string, mixed> $nudge */
    /** @var string $section */
    use Illuminate\Support\Str;

    $action = $nudge['primary_action'] ?? null;
    $draftReply = trim((string) ($nudge['draft_reply'] ?? ''));
@endphp

<section class="ops-comms-nudge" aria-label="Suggested next step">
    <div class="ops-comms-nudge__header">
        <p class="ops-comms-nudge__eyebrow">Suggested next step</p>
        <h4 class="ops-comms-nudge__headline">{{ $nudge['headline'] ?? 'Follow up' }}</h4>
    </div>

    <p class="ops-comms-nudge__message">{{ $nudge['message'] ?? '' }}</p>

    @if (filled($nudge['rationale'] ?? null))
        <p class="ops-comms-nudge__rationale">{{ $nudge['rationale'] }}</p>
    @endif

    @if ($draftReply !== '')
        <p class="ops-comms-nudge__draft">{{ Str::limit($draftReply, 180) }}</p>
    @endif

    <div class="ops-comms-nudge__actions">
        @if (is_array($action))
            @if (($action['type'] ?? '') === 'link')
                <a href="{{ $action['url'] }}" class="ops-comms-nudge__primary">{{ $action['label'] ?? 'Open' }}</a>
            @elseif (($action['type'] ?? '') === 'anchor')
                <a
                    href="{{ $action['url'] ?? '#' }}"
                    class="ops-comms-nudge__primary"
                    @if ($draftReply !== '' && str_contains((string) ($action['url'] ?? ''), 'comms-call-note-composer'))
                        x-data
                        @click.prevent="
                            const form = document.getElementById('comms-call-note-composer');
                            const input = form?.querySelector('textarea[name=body]');
                            if (input) { input.value = @js($draftReply); input.focus(); }
                            const nudgeKey = form?.querySelector('input[name=nudge_key]');
                            if (nudgeKey) { nudgeKey.value = @js($nudge['key'] ?? 'call.log_note'); }
                            form?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        "
                    @endif
                >{{ $action['label'] ?? 'Go' }}</a>
            @elseif (($action['type'] ?? '') === 'composer')
                <a
                    href="{{ $action['url'] ?? '#comms-thread-composer' }}"
                    class="ops-comms-nudge__primary"
                    @if ($draftReply !== '')
                        x-data
                        @click.prevent="
                            window.dispatchEvent(new CustomEvent('ark:prefill-comms-composer', { detail: { body: @js($draftReply), nudgeKey: @js($nudge['key'] ?? ''), entityKey: @js($nudge['entity_key'] ?? '') } }));
                            document.getElementById('comms-thread-composer')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        "
                    @endif
                >{{ $action['label'] ?? 'Reply' }}</a>
            @elseif (in_array($action['type'] ?? '', ['form', 'redirect_form'], true))
                <form
                    method="{{ $action['method'] ?? 'POST' }}"
                    action="{{ $action['url'] }}"
                    class="ops-comms-workspace__inline-form"
                >
                    @csrf
                    @foreach ($action['fields'] ?? [] as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    <input type="hidden" name="section" value="{{ $section }}">
                    <button type="submit" class="ops-comms-nudge__primary">{{ $action['label'] ?? 'Continue' }}</button>
                </form>
            @endif
        @endif

        <form
            method="POST"
            action="{{ route('operations.communications.nudge.dismiss') }}"
            class="ops-comms-workspace__inline-form"
        >
            @csrf
            <input type="hidden" name="entity_key" value="{{ $nudge['entity_key'] ?? '' }}">
            <input type="hidden" name="nudge_key" value="{{ $nudge['key'] ?? '' }}">
            <input type="hidden" name="section" value="{{ $section }}">
            <button type="submit" class="ops-comms-nudge__dismiss">Not now</button>
        </form>
    </div>
</section>
