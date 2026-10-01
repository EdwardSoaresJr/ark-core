@if ($full ?? false)
    @include('operations.communications.partials.channel-tabs')
@endif

@include('operations.work.partials.queue-comms-attention')

@if ($full ?? false)
    <div
        id="ops-work-recent-activity"
        class="ops-board-shell border-0"
        data-fragment-url="{{ route('operations.communications.recent-activity.fragment', request()->only('channel')) }}"
    >
        <div class="border-b border-slate-200 px-3 py-2">
            <h3 class="text-sm font-black text-slate-950">
                Recent Activity
                <span
                    data-recent-activity-count
                    class="font-normal text-slate-500"
                    @if (($recent_activity ?? []) === []) hidden @endif
                >
                    @if (($recent_activity ?? []) !== [])
                        ({{ count($recent_activity) }})
                    @endif
                </span>
            </h3>
            <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Calls and messages in the last {{ \App\Ark\Operations\Communications\CommunicationsQueueWindow::HOURS }} hours.</p>
        </div>
        <div class="border-t border-slate-100" data-recent-activity-list>
            @include('operations.communications.partials.recent-activity-list', [
                'rows' => $recent_activity ?? [],
            ])
        </div>
    </div>
@endif
