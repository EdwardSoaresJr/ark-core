@extends('install.layout')

@section('title', 'Installing')

@section('content')
    <h1>Installing ARK</h1>
    <p class="lead">This can take a few minutes on a small server. Keep this page open — you do not need the terminal.</p>

    <div id="install-progress-panel" class="{{ $failed ? 'errors' : 'status' }}" role="status" aria-live="polite">
        <strong id="install-progress-label">{{ $label }}</strong>
        @if ($failed && $errorMessage)
            <p id="install-progress-message" style="margin:.5rem 0 0">{{ $errorMessage }}</p>
        @else
            <p id="install-progress-message" style="margin:.5rem 0 0;display:none"></p>
        @endif
    </div>

    <div class="actions" id="install-progress-actions" @if (! $failed) style="display:none" @endif>
        <a class="btn btn-primary" href="{{ route('install.admin') }}">Try again</a>
    </div>

    <script>
        (function () {
            const statusUrl = @json($statusUrl);
            const completeUrl = @json($completeUrl);
            const panel = document.getElementById('install-progress-panel');
            const labelEl = document.getElementById('install-progress-label');
            const messageEl = document.getElementById('install-progress-message');
            const actions = document.getElementById('install-progress-actions');
            let stopped = {{ $failed ? 'true' : 'false' }};

            async function poll() {
                if (stopped) {
                    return;
                }

                try {
                    const response = await fetch(statusUrl, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) {
                        throw new Error('status ' + response.status);
                    }
                    const data = await response.json();
                    if (labelEl && data.label) {
                        labelEl.textContent = data.label;
                    }
                    if (data.phase === 'complete') {
                        stopped = true;
                        window.location.href = data.complete_url || completeUrl;
                        return;
                    }
                    if (data.phase === 'failed') {
                        stopped = true;
                        panel.className = 'errors';
                        if (messageEl) {
                            messageEl.style.display = 'block';
                            messageEl.textContent = data.message || 'Installation failed. You can try again.';
                        }
                        if (actions) {
                            actions.style.display = 'flex';
                        }
                        return;
                    }
                } catch (e) {
                    // Keep polling — short outages during migrate are expected.
                }

                window.setTimeout(poll, 2000);
            }

            if (!stopped) {
                window.setTimeout(poll, 1500);
            }
        })();
    </script>
@endsection
