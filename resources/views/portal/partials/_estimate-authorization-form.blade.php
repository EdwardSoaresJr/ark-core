@php
    $alpineScope = $alpineScope ?? 'form';
    $authorizationConcerns = $authorizationForm->authorizationConcerns;
    $initialDispositions = $authorizationForm->initialDispositions;
@endphp

<form
    method="POST"
    action="{{ route('portal.estimates.authorize', ['token' => $token]) }}"
    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
    @if ($alpineScope === 'form')
        x-data="arkPortalEstimateForm({
            authorization: {
                concerns: @js($authorizationConcerns),
                initialDispositions: @js($initialDispositions),
            },
            signatureRequired: @js($signatureRequired),
            depositEnabled: @js($depositEnabled ?? false),
        })"
        @if ($signatureRequired)
            x-init="init()"
        @endif
    @endif
    @submit="validateBeforeSubmit($event)"
>
    @csrf

    <div class="border-b border-slate-100 bg-gradient-to-r from-[#0099cc]/8 to-white px-4 py-4 sm:px-5">
        <h2 class="text-base font-semibold text-slate-950">Choose what to approve</h2>
        <p class="mt-1 text-sm leading-6 text-slate-600">
            Approve what you want done now. Defer means not now — we keep it for a later visit. Decline means you do not want that repair.
        </p>
    </div>

    <div class="space-y-4 px-4 py-4 sm:px-5">
        @if (($errors ?? null)?->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($authorizationMode === \App\Ark\Operations\Portal\PortalEstimateAuthorizationMode::PerConcern)
            <div class="space-y-3">
                @foreach ($authorizationConcerns as $concern)
                    <fieldset class="rounded-lg border border-slate-200 bg-white p-4">
                        <div class="flex items-start justify-between gap-3">
                            <legend class="text-sm font-semibold text-slate-950">{{ $concern['summary'] }}</legend>
                            <span class="shrink-0 text-sm font-bold tabular-nums text-slate-950">{{ $concern['subtotal'] }}</span>
                        </div>

                        <input
                            type="hidden"
                            name="concern_dispositions[{{ $concern['id'] }}]"
                            :value="dispositions[{{ $concern['id'] }}]"
                        >

                        <div class="mt-3 grid grid-cols-3 gap-2">
                            <label
                                class="flex min-h-11 cursor-pointer items-center justify-center rounded-md border px-2 text-sm font-semibold transition"
                                :class="dispositions[{{ $concern['id'] }}] === 'approved'
                                    ? 'border-emerald-600 bg-emerald-50 text-emerald-900 ring-2 ring-emerald-600/20'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                            >
                                <input
                                    type="radio"
                                    value="approved"
                                    class="sr-only"
                                    x-model="dispositions[{{ $concern['id'] }}]"
                                >
                                Approve
                            </label>
                            <label
                                class="flex min-h-11 cursor-pointer items-center justify-center rounded-md border px-2 text-sm font-semibold transition"
                                :class="dispositions[{{ $concern['id'] }}] === 'deferred'
                                    ? 'border-amber-600 bg-amber-50 text-amber-950 ring-2 ring-amber-600/15'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                            >
                                <input
                                    type="radio"
                                    value="deferred"
                                    class="sr-only"
                                    x-model="dispositions[{{ $concern['id'] }}]"
                                >
                                Defer
                            </label>
                            <label
                                class="flex min-h-11 cursor-pointer items-center justify-center rounded-md border px-2 text-sm font-semibold transition"
                                :class="dispositions[{{ $concern['id'] }}] === 'declined'
                                    ? 'border-rose-600 bg-rose-50 text-rose-950 ring-2 ring-rose-600/15'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                            >
                                <input
                                    type="radio"
                                    value="declined"
                                    class="sr-only"
                                    x-model="dispositions[{{ $concern['id'] }}]"
                                >
                                Decline
                            </label>
                        </div>
                    </fieldset>
                @endforeach
            </div>

            <div
                class="hidden rounded-lg border border-[#0099cc]/30 bg-[#0099cc]/5 px-4 py-3 md:block"
                aria-live="polite"
            >
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Approved work total</p>
                        <p class="mt-0.5 text-xs text-slate-600" x-show="approvedCount() > 0" x-cloak>
                            <span x-text="approvedCount()"></span>
                            <span x-text="approvedCount() === 1 ? ' service approved' : ' services approved'"></span>
                        </p>
                        <p class="mt-0.5 text-xs text-slate-600" x-show="approvedCount() === 0 && declinedCount() > 0 && deferredCount() === 0" x-cloak>
                            All services declined — your response is recorded.
                        </p>
                        <p class="mt-0.5 text-xs text-slate-600" x-show="approvedCount() === 0 && deferredCount() > 0 && declinedCount() === 0" x-cloak>
                            All services deferred — you can still submit to record your choices.
                        </p>
                        <p class="mt-0.5 text-xs text-slate-600" x-show="approvedCount() === 0 && deferredCount() === 0 && declinedCount() === 0" x-cloak>
                            Choose Approve, Defer, or Decline for each repair.
                        </p>
                    </div>
                    <p class="text-xl font-black tabular-nums text-slate-950" x-text="approvedTotalLabel()"></p>
                </div>
                @if (filled($discountNote ?? null))
                    <p class="mt-2 text-xs leading-5 text-slate-600">{{ $discountNote }}</p>
                @endif
            </div>
        @endif

        <div>
            <label for="confirmed_name" class="block text-sm font-semibold text-slate-800">Your name</label>
            <input
                id="confirmed_name"
                name="confirmed_name"
                type="text"
                value="{{ old('confirmed_name', $customerName) }}"
                required
                maxlength="255"
                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2.5 text-base text-slate-950 shadow-sm sm:text-sm"
            >
        </div>

        <div>
            <label for="notes" class="block text-sm font-semibold text-slate-800">Notes <span class="font-normal text-slate-500">(optional)</span></label>
            <textarea
                id="notes"
                name="notes"
                rows="2"
                maxlength="2000"
                placeholder="Questions or instructions for the shop"
                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2.5 text-base text-slate-950 shadow-sm sm:text-sm"
            >{{ old('notes') }}</textarea>
        </div>

        @if ($signatureRequired)
            @if (filled($authorizationLanguage ?? null))
                <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-3 text-sm leading-6 text-slate-700">
                    <p class="font-semibold text-slate-900">Approval terms</p>
                    <p class="mt-2 whitespace-pre-line">{{ $authorizationLanguage }}</p>
                </div>

                <label class="inline-flex items-start gap-2 text-sm text-slate-800">
                    <input
                        type="checkbox"
                        name="authorization_acknowledged"
                        value="1"
                        required
                        @checked(old('authorization_acknowledged'))
                        class="mt-1 rounded border-slate-300 text-slate-950"
                    >
                    <span>I have read and agree to the terms above.</span>
                </label>
            @endif

            <div>
                <div class="flex items-center justify-between gap-3">
                    <label class="block text-sm font-semibold text-slate-800">Signature</label>
                    <button type="button" class="text-xs font-semibold text-slate-600 underline" @click="clear()">Clear</button>
                </div>
                <canvas
                    x-ref="signatureCanvas"
                    class="mt-2 h-36 w-full rounded-md border border-slate-300 bg-white touch-none lg:h-44"
                    @pointerdown.prevent="startDrawing($event)"
                    @pointermove.prevent="draw($event)"
                    @pointerup.prevent="stopDrawing()"
                    @pointerleave.prevent="stopDrawing()"
                ></canvas>
                <input type="hidden" name="signature_data" x-ref="signatureInput" value="">
                <p class="mt-1 text-xs text-slate-500">Sign with your finger or mouse to confirm your choices.</p>
            </div>
        @endif
    </div>

    <div class="border-t border-slate-100 bg-slate-50 px-4 py-4 sm:px-5">
        <button
            type="submit"
            class="inline-flex min-h-12 w-full items-center justify-center rounded-md bg-[#0099cc] px-4 text-base font-semibold text-white shadow-sm hover:bg-[#007aa3] sm:text-sm lg:max-w-none"
            x-text="submitButtonLabel()"
        >
            Approve all services
        </button>
        @if ($depositEnabled ?? false)
            <p class="mt-2 text-center text-xs leading-5 text-slate-600">
                After you send your choices, you’ll pay the deposit on the next step if one is required.
            </p>
        @endif
    </div>
</form>
