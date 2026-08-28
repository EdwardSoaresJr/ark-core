@php
    $integrations = $shopIntegrations ?? \App\Ark\Operations\Settings\ShopIntegrationCredentials::forCurrentShop();
    $openaiReady = $integrations->openaiConfigured();
@endphp

<div class="space-y-3 rounded-sm border border-slate-200 bg-white p-3">
    <div class="border-b border-slate-200 pb-2">
        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Call intelligence (AI)</p>
        <p class="mt-1 text-xs leading-5 text-slate-500">
            Transcribes recorded calls and generates owner summaries on
            <a href="{{ route('operations.owner.call-intelligence') }}" class="font-semibold text-slate-800 underline">Call intelligence</a>.
            {{ $integrations->credentialSourceLabel($integrations->openaiCredentialSource()) }}
        </p>
    </div>

    <div class="rounded-sm border px-3 py-2 text-xs font-semibold {{ $openaiReady ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900' }}">
        @if ($openaiReady)
            Call analysis is active. New recordings queue automatically when the worker is running.
        @else
            Add an OpenAI API key to enable transcription and AI summaries.
        @endif
    </div>

    <div class="grid gap-3 lg:grid-cols-2">
        <label class="block">
            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">OpenAI API key</span>
            <input
                type="password"
                name="openai_api_key"
                autocomplete="off"
                class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-1.5 font-mono text-[11px] text-slate-800"
                placeholder="{{ $integrations->hasStoredOpenaiApiKey() ? 'Saved — leave blank to keep' : 'sk-…' }}"
            >
        </label>
        <label class="block">
            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Transcription model</span>
            <input
                type="text"
                name="openai_transcription_model"
                value="{{ old('openai_transcription_model', $settings->openai_transcription_model ?: 'whisper-1') }}"
                class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-800"
            >
        </label>
        <label class="block lg:col-span-2">
            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Analysis model</span>
            <input
                type="text"
                name="openai_analysis_model"
                value="{{ old('openai_analysis_model', $settings->openai_analysis_model ?: 'gpt-4o-mini') }}"
                class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-800"
            >
            <span class="mt-1 block text-[11px] leading-4 text-slate-500">Used for summary, empathy scoring, missed-upsell flags, coaching priority, and advisor improvement notes after Whisper transcription.</span>
        </label>
    </div>
</div>
