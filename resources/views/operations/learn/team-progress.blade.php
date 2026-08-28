<x-operations.app :title="\App\Support\Branding\Branding::learnName().' · Team progress'">
    <section class="ops-learn-team">
        <header class="ops-learn__header">
            <div>
                <p class="ops-learn__eyebrow">Staff training</p>
                <h1 class="ops-learn__title">Team training progress</h1>
                <p class="ops-learn__lede">Who has finished required {{ \App\Support\Branding\Branding::learnName() }} guides. Optional guides are not tracked here.</p>
            </div>
            <div class="ops-learn-print-select__actions">
                <a href="{{ route('operations.learn.index') }}" class="ops-learn-print-select__btn">Back to guides</a>
            </div>
        </header>

        @if (! ($trainingGateEnabled ?? true))
            <div class="ops-learn-owner-gate ops-learn-owner-gate--info" role="status">
                <p class="ops-learn-owner-gate__body">Required training gate is paused — staff are not blocked from the workboard.</p>
            </div>
        @endif

        <div class="ops-learn-team__table-wrap">
            <table class="ops-learn-table ops-learn-team__table">
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Role</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th>Outstanding</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $outstanding = collect($row['progress'])->where('completed', false)->values();
                        @endphp
                        <tr>
                            <td>
                                <span class="ops-learn-team__name">{{ $row['user']->name }}</span>
                            </td>
                            <td>{{ $row['role_label'] }}</td>
                            <td class="tabular-nums">
                                {{ $row['summary']['completed'] }}/{{ $row['summary']['required'] }}
                                @if ($row['summary']['required'] > 0)
                                    <span class="ops-learn-team__percent">({{ $row['summary']['percent'] }}%)</span>
                                @endif
                            </td>
                            <td>
                                @if ($row['current'])
                                    <span class="ops-learn-team__status ops-learn-team__status--current">Current</span>
                                @elseif ($row['snooze'])
                                    <span class="ops-learn-team__status ops-learn-team__status--snoozed">
                                        Snoozed until {{ $row['snooze']['snoozed_until_label'] }}
                                    </span>
                                @elseif ($row['summary']['required'] === 0)
                                    <span class="ops-learn-team__status">N/A</span>
                                @else
                                    <span class="ops-learn-team__status ops-learn-team__status--behind">Required</span>
                                @endif
                            </td>
                            <td>
                                @if ($outstanding->isEmpty())
                                    <span class="ops-learn-team__none">—</span>
                                @else
                                    <ul class="ops-learn-team__outstanding">
                                        @foreach ($outstanding as $article)
                                            <li>{{ $article['title'] }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No active staff accounts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-operations.app>
