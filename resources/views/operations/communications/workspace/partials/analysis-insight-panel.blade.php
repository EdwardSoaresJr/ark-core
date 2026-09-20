@php
    /** @var array<string, mixed> $insight */
    $suggestedReply = trim((string) ($insight['suggested_reply'] ?? ''));
    $followUpNotes = trim((string) ($insight['follow_up_notes'] ?? ''));
    $summary = trim((string) ($insight['summary'] ?? ''));
    $entityKey = (string) ($entityKey ?? $insight['entity_key'] ?? '');
    $nudgeKey = (string) ($insight['nudge_key'] ?? 'conversation.sms_analysis_follow_up');
    $composerTarget = (string) ($composerTarget ?? 'sms');
    $isCallNote = $composerTarget === 'call-note';
@endphp

<section class="ops-comms-analysis-insight" aria-label="Analysis insight">
    <div class="ops-comms-analysis-insight__header">
        <p class="ops-comms-analysis-insight__eyebrow">{{ $insight['channel'] ?? 'Analysis' }} insight</p>
        @if (($insight['follow_up_needed'] ?? false) === true)
            <p class="ops-comms-analysis-insight__badge">Follow-up suggested</p>
        @endif
    </div>

    @if ($summary !== '')
        <p class="ops-comms-analysis-insight__summary">{{ $summary }}</p>
    @endif

    @if ($followUpNotes !== '' && $followUpNotes !== $summary)
        <p class="ops-comms-analysis-insight__notes">{{ $followUpNotes }}</p>
    @endif

    @if ($suggestedReply !== '')
        <div class="ops-comms-analysis-insight__draft">
            <p class="ops-comms-analysis-insight__draft-label">{{ $isCallNote ? 'Suggested note' : 'Suggested reply' }}</p>
            <p class="ops-comms-analysis-insight__draft-body">{{ $suggestedReply }}</p>
            <a
                href="{{ $isCallNote ? '#comms-call-note-composer' : '#comms-thread-composer' }}"
                class="ops-comms-analysis-insight__draft-action"
                x-data
                @if ($isCallNote)
                    @click.prevent="
                        const form = document.getElementById('comms-call-note-composer');
                        const input = form?.querySelector('textarea[name=body]');
                        if (input) { input.value = @js($suggestedReply); input.focus(); }
                        const nudgeKey = form?.querySelector('input[name=nudge_key]');
                        if (nudgeKey) { nudgeKey.value = @js($nudgeKey); }
                        form?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    "
                @else
                    @click.prevent="
                        window.dispatchEvent(new CustomEvent('ark:prefill-comms-composer', { detail: { body: @js($suggestedReply), nudgeKey: @js($nudgeKey), entityKey: @js($entityKey) } }));
                        document.getElementById('comms-thread-composer')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    "
                @endif
            >{{ $isCallNote ? 'Use in call note' : 'Use in composer' }}</a>
        </div>
    @endif
</section>
