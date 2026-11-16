@php
    $channel = in_array($channel ?? 'sms', ['sms', 'email'], true) ? $channel : 'sms';
@endphp

<x-operations.app title="New Message" ui="tabler">
    <x-tabler.page
        layout="focused"
        title="New Message"
        title-id="ops-comms-compose"
        subtitle="Start a conversation with a phone number or email. A customer record is optional."
        actions-label="Compose commands"
    >
        <x-slot:actions>
            <x-tabler.page-action :href="\App\Ark\Operations\Communications\CommunicationsNeedsYou::url()" icon="message" label="Inbox" />
        </x-slot:actions>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('operations.communications.compose') }}" data-comms-compose-start>
                    @csrf

                    <div class="mb-3" role="tablist" aria-label="Message channel">
                        <div class="btn-group" role="group">
                            <button
                                type="button"
                                class="btn {{ $channel === 'sms' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                data-comms-compose-channel="sms"
                                aria-pressed="{{ $channel === 'sms' ? 'true' : 'false' }}"
                            >
                                SMS
                            </button>
                            <button
                                type="button"
                                class="btn {{ $channel === 'email' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                data-comms-compose-channel="email"
                                aria-pressed="{{ $channel === 'email' ? 'true' : 'false' }}"
                            >
                                Email
                            </button>
                        </div>
                    </div>

                    <div data-comms-compose-panel="sms" @if ($channel !== 'sms') hidden @endif>
                        <label class="form-label" for="comms-compose-phone">Phone number</label>
                        <input
                            id="comms-compose-phone"
                            type="tel"
                            name="phone"
                            value="{{ old('phone', $phone) }}"
                            class="form-control @error('phone') is-invalid @enderror"
                            autocomplete="tel"
                            placeholder="(719) 555-0100"
                            @if ($channel === 'sms') required @endif
                            @if ($channel !== 'sms') disabled @endif
                        >
                        @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div data-comms-compose-panel="email" @if ($channel !== 'email') hidden @endif>
                        <label class="form-label" for="comms-compose-email">Email address</label>
                        <input
                            id="comms-compose-email"
                            type="email"
                            name="email"
                            value="{{ old('email', $email) }}"
                            class="form-control @error('email') is-invalid @enderror"
                            autocomplete="email"
                            placeholder="parts@example.com"
                            @if ($channel === 'email') required @endif
                            @if ($channel !== 'email') disabled @endif
                        >
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <a href="{{ \App\Ark\Operations\Communications\CommunicationsNeedsYou::url() }}" class="btn btn-ghost-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Open conversation</button>
                    </div>
                </form>
            </div>
        </div>
    </x-tabler.page>

    <script>
        (() => {
            const form = document.querySelector('[data-comms-compose-start]');
            if (!form) return;

            const panels = {
                sms: form.querySelector('[data-comms-compose-panel="sms"]'),
                email: form.querySelector('[data-comms-compose-panel="email"]'),
            };
            const inputs = {
                sms: form.querySelector('#comms-compose-phone'),
                email: form.querySelector('#comms-compose-email'),
            };
            const buttons = [...form.querySelectorAll('[data-comms-compose-channel]')];

            const show = (channel) => {
                for (const key of ['sms', 'email']) {
                    const active = key === channel;
                    panels[key].hidden = !active;
                    inputs[key].disabled = !active;
                    inputs[key].required = active;
                }
                buttons.forEach((button) => {
                    const active = button.dataset.commsComposeChannel === channel;
                    button.classList.toggle('btn-primary', active);
                    button.classList.toggle('btn-outline-secondary', !active);
                    button.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
                inputs[channel]?.focus();
            };

            buttons.forEach((button) => {
                button.addEventListener('click', () => show(button.dataset.commsComposeChannel));
            });
        })();
    </script>
</x-operations.app>
