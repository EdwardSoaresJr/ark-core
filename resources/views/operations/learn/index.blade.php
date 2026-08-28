@php
    use App\Ark\Operations\Learn\LearnArkCurriculum;
    use App\Ark\Operations\Learn\LearnArticleKey;
    use App\Ark\Runtime\Authorization\ArkCapability;
@endphp

<x-operations.app :title="\App\Support\Branding\Branding::learnName()">
    <section class="ops-learn">
        @if (session('learn_gate'))
            <div class="ops-learn-gate" role="status">
                <div class="ops-learn-gate__copy">
                    <p class="ops-learn-gate__title">Complete required guides before using the workboard</p>
                    <p class="ops-learn-gate__body">
                        @if (! empty(session('learn_gate.next_title')))
                            Next up: <strong>{{ session('learn_gate.next_title') }}</strong>.
                        @endif
                        {{ session('learn_gate.summary.completed') }} of {{ session('learn_gate.summary.required') }} required guides finished.
                    </p>
                </div>
                @if ($canSnoozeTraining ?? false)
                    <form method="POST" action="{{ route('operations.learn.progress.snooze') }}" class="ops-learn-snooze-form">
                        @csrf
                        <button type="submit" class="ops-learn-snooze-form__btn">
                            Snooze {{ $snoozeHours }}h — back to workboard
                        </button>
                    </form>
                @elseif (($trainingSummary['required'] ?? 0) > ($trainingSummary['completed'] ?? 0))
                    <p class="ops-learn-gate__snooze-hint">Read at least one section to snooze again.</p>
                @endif
            </div>
        @endif

        @if (session('learn_snooze_blocked'))
            <div class="ops-learn-gate" role="status">
                <p class="ops-learn-gate__body">{{ session('learn_snooze_blocked') }}</p>
            </div>
        @endif

        @if (session('learn_gate_control'))
            <div class="ops-learn-owner-gate" role="status">
                <p class="ops-learn-owner-gate__body">{{ session('learn_gate_control') }}</p>
            </div>
        @endif

        @if ($canManageTrainingGate ?? false)
            <div class="ops-learn-owner-gate">
                <div class="ops-learn-owner-gate__copy">
                    <p class="ops-learn-owner-gate__title">Owner training controls</p>
                    @if ($trainingGateEnabled ?? true)
                        <p class="ops-learn-owner-gate__body">
                            Gate is <strong>on</strong> — staff must finish required guides or snooze to reach the workboard.
                            @if ($ownerTrainingBypass ?? false)
                                You have owner bypass; your workboard is never gated.
                            @endif
                        </p>
                    @else
                        <p class="ops-learn-owner-gate__body">
                            Gate is <strong>off</strong> — all staff can use the workboard without finishing guides. Progress is still tracked.
                        </p>
                    @endif
                </div>
                <form method="POST" action="{{ route('operations.learn.training-gate') }}" class="ops-learn-owner-gate__form">
                    @csrf
                    <input type="hidden" name="enabled" value="{{ ($trainingGateEnabled ?? true) ? '0' : '1' }}">
                    <button type="submit" class="ops-learn-owner-gate__btn">
                        {{ ($trainingGateEnabled ?? true) ? 'Pause gate for all staff' : 'Turn gate back on' }}
                    </button>
                </form>
            </div>
        @elseif (! ($trainingGateEnabled ?? true))
            <div class="ops-learn-owner-gate ops-learn-owner-gate--info" role="status">
                <p class="ops-learn-owner-gate__body">Required training gate is paused shop-wide.</p>
            </div>
        @endif

        @if ($trainingSnooze)
            <div class="ops-learn-snooze-banner" role="status">
                Training snoozed until <strong>{{ $trainingSnooze['snoozed_until_label'] }}</strong>.
                <a href="{{ route('operations.learn.index') }}">Resume guides</a>
            </div>
        @endif

        <header class="ops-learn__header">
            <div>
                <p class="ops-learn__eyebrow">Staff training</p>
                <h1 class="ops-learn__title">{{ \App\Support\Branding\Branding::learnName() }}</h1>
                <p class="ops-learn__lede">Role-based training for how this shop uses ARK. You see your track and every role below it on the floor.</p>
                @if (($trainingSummary['required'] ?? 0) > 0)
                    <div class="ops-learn-training">
                        <div class="ops-learn-training__meta">
                            <span>Required training</span>
                            <span class="tabular-nums">{{ $trainingSummary['completed'] }}/{{ $trainingSummary['required'] }}</span>
                        </div>
                        <div class="ops-learn-training__bar" aria-hidden="true">
                            <span class="ops-learn-training__fill" style="width: {{ $trainingSummary['percent'] }}%"></span>
                        </div>
                        @if (($trainingSummary['stale'] ?? 0) > 0)
                            <p class="ops-learn-training__stale">{{ $trainingSummary['stale'] }} guide(s) updated — re-read required.</p>
                        @endif
                    </div>
                @endif
            </div>
            <div class="ops-learn-print-select__actions">
                @if ($visibleSections !== [])
                    <div class="ops-learn__role-chips">
                        @foreach ($visibleSections as $visibleSection)
                            <span class="ops-role-chip {{ $visibleSection->chipClass }}">{{ $visibleSection->label }}</span>
                        @endforeach
                    </div>
                @endif
                @can(ArkCapability::StaffManage->value)
                    <a href="{{ route('operations.learn.team-progress') }}" class="ops-learn-print-select__btn">Team progress</a>
                @endcan
                <a href="{{ route('operations.learn.print') }}" class="ops-learn-print-select__btn">Print guides</a>
            </div>
        </header>

        @can(ArkCapability::StaffManage->value)
            @if (($nextWaveArticles ?? collect())->isNotEmpty())
                <div class="ops-learn-next-wave">
                    <p class="ops-learn-next-wave__title">Next required wave (planned)</p>
                    <p class="ops-learn-next-wave__lede">Promote these optional guides when the floor is ready for the next training gate.</p>
                    <ul class="ops-learn-next-wave__list">
                        @foreach ($nextWaveArticles as $nextArticle)
                            <li>
                                <a href="{{ route('operations.learn.show', ['role' => $nextArticle['role'], 'article' => $nextArticle['slug']]) }}">
                                    <span class="ops-learn-next-wave__guide-title">{{ $nextArticle['title'] }}</span>
                                    <span class="ops-learn-next-wave__guide-role">{{ $nextArticle['section_label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endcan

        <div class="ops-learn__layout">
            <nav aria-label="{{ \App\Support\Branding\Branding::learnName() }} guides" class="ops-learn__nav">
                @forelse ($visibleSections as $visibleSection)
                    @php
                        $roleArticles = $articlesByRole[$visibleSection->key] ?? [];
                    @endphp
                    @if ($roleArticles !== [])
                        <div class="ops-learn__nav-group">
                            <p class="ops-learn__nav-heading">{{ $visibleSection->label }}</p>
                            <ul class="ops-learn__nav-list">
                                @foreach ($roleArticles as $navArticle)
                                    @php
                                        $navArticleKey = LearnArticleKey::make($visibleSection->key, $navArticle['slug']);
                                        $navRequired = LearnArkCurriculum::isRequired($navArticleKey);
                                        $navCompleted = in_array($navArticleKey, $completedArticleKeys, true);
                                        $navStale = in_array($navArticleKey, $staleArticleKeys ?? [], true);
                                    @endphp
                                    <li>
                                        <a
                                            href="{{ route('operations.learn.show', ['role' => $visibleSection->key, 'article' => $navArticle['slug']]) }}"
                                            @class([
                                                'ops-learn__nav-link',
                                                'ops-learn__nav-link--active' => $article !== null
                                                    && $article['section']->key === $visibleSection->key
                                                    && $article['slug'] === $navArticle['slug'],
                                            ])
                                        >
                                            <span class="ops-learn__nav-link-row">
                                                <span class="ops-learn__nav-link-title">{{ $navArticle['title'] }}</span>
                                                @if ($navRequired)
                                                    <span @class([
                                                        'ops-learn__nav-badge',
                                                        'ops-learn__nav-badge--done' => $navCompleted,
                                                        'ops-learn__nav-badge--updated' => $navStale,
                                                        'ops-learn__nav-badge--required' => ! $navCompleted && ! $navStale,
                                                    ])>{{ $navStale ? 'Updated' : ($navCompleted ? 'Done' : 'Required') }}</span>
                                                @endif
                                            </span>
                                            <span class="ops-learn__nav-link-summary">{{ $navArticle['summary'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @empty
                    <p class="ops-learn__empty">No training guides are assigned to your account yet. Ask a manager to confirm your staff role.</p>
                @endforelse
            </nav>

            <article
                class="ops-learn__article"
                @if ($article !== null && ($articleProgress['required'] ?? false) && ! ($articleProgress['completed'] ?? false))
                    x-data="arkLearnProgress({
                        articleKey: @js($articleProgress['article_key']),
                        minActiveSeconds: @js($articleProgress['min_active_seconds']),
                        checkpointMinActiveSeconds: @js($articleProgress['checkpoint_min_active_seconds']),
                        idleSeconds: @js($articleProgress['idle_seconds']),
                        completed: @js($articleProgress['completed']),
                        activeSeconds: @js($articleProgress['active_seconds']),
                        reachedCheckpointKeys: @js(collect($articleProgress['checkpoints'] ?? [])->pluck('key')->values()->all()),
                        heartbeatUrl: @js(route('operations.learn.progress.heartbeat')),
                        checkpointUrl: @js(route('operations.learn.progress.checkpoint')),
                        completeUrl: @js(route('operations.learn.progress.complete')),
                        resumeUrl: @js(route('operations.index')),
                    })"
                    x-init="init()"
                @endif
            >
                @if ($article === null)
                    <div class="ops-learn__welcome">
                        <h2>Pick a guide</h2>
                        <p>Select a topic from the left to get started. Owners, advisors, technicians, and admins each have their own section.</p>
                    </div>
                @else
                    <header class="ops-learn__article-header">
                        <p class="ops-learn__article-role">{{ $article['section']->label }}</p>
                        <h2 class="ops-learn__article-title">{{ $article['title'] }}</h2>
                        <p class="ops-learn__article-summary">{{ $article['summary'] }}</p>
                    </header>

                    @if ($canManageLearnMedia ?? false)
                        @include('operations.learn.partials.media-manager', [
                            'role' => $article['section']->key,
                            'article' => $article,
                            'articleMedia' => $articleMedia ?? collect(),
                        ])
                    @endif

                    <div class="ops-learn__article-body">
                        @include($article['view'])
                    </div>

                    @if ($articleProgress !== null && ($articleProgress['required'] ?? false))
                        <footer class="ops-learn-progress">
                            @if ($articleProgress['completed'] ?? false)
                                <p class="ops-learn-progress__done">Guide complete. You can return to the workboard anytime.</p>
                            @else
                                <div class="ops-learn-progress__panel">
                                    @if ($articleProgress['content_stale'] ?? false)
                                        <p class="ops-learn-progress__stale">This guide was updated. Re-read each section to complete again.</p>
                                    @endif
                                    <div class="ops-learn-progress__head">
                                        <p class="ops-learn-progress__title">Required guide — read each section</p>
                                        <p class="ops-learn-progress__hint">Active time only. Idle tabs stop the clock. Sections unlock in order.</p>
                                    </div>

                                    <ul class="ops-learn-progress__checkpoints">
                                        <template x-for="heading in headings" :key="heading.key">
                                            <li
                                                class="ops-learn-progress__checkpoint"
                                                :class="heading.reached ? 'ops-learn-progress__checkpoint--done' : 'ops-learn-progress__checkpoint--pending'"
                                            >
                                                <span class="ops-learn-progress__checkpoint-mark" x-text="heading.reached ? '✓' : '○'"></span>
                                                <span x-text="heading.label"></span>
                                            </li>
                                        </template>
                                    </ul>

                                    <div class="ops-learn-progress__meta">
                                        <span>Active reading: <strong class="tabular-nums" x-text="activeSeconds"></strong>s</span>
                                        <span x-show="remainingActiveSeconds() > 0">
                                            Need <strong class="tabular-nums" x-text="remainingActiveSeconds()"></strong>s more active time
                                        </span>
                                    </div>

                                    <p class="ops-learn-progress__status" x-show="statusMessage" x-text="statusMessage"></p>
                                    <p class="ops-learn-progress__error" x-show="errorMessage" x-text="errorMessage"></p>

                                    <div class="ops-learn-progress__actions">
                                        <button
                                            type="button"
                                            class="ops-learn-progress__complete"
                                            :disabled="! canComplete()"
                                            @click="completeArticle()"
                                        >
                                            <span x-show="! completing">Mark guide complete</span>
                                            <span x-show="completing">Saving…</span>
                                        </button>

                                        @if ($canSnoozeTraining ?? false)
                                            <form method="POST" action="{{ route('operations.learn.progress.snooze') }}">
                                                @csrf
                                                <button type="submit" class="ops-learn-snooze-form__btn ops-learn-snooze-form__btn--inline">
                                                    Snooze {{ $snoozeHours }}h — workboard
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </footer>
                    @endif
                @endif
            </article>
        </div>
    </section>
</x-operations.app>
