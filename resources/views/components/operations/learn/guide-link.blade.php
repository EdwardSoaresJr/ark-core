@props([
    'role',
    'article',
    'label' => null,
])

@php
    use App\Ark\Operations\Learn\LearnArkCatalog;

    $user = auth()->user();
    $resolved = $user !== null ? LearnArkCatalog::articleFor($user, $role, $article) : null;
    $text = $label ?? ($resolved['title'] ?? 'Guide');
@endphp

@if ($resolved !== null)
    <button
        {{ $attributes->merge(['type' => 'button', 'class' => 'ops-learn-guide-link']) }}
        data-arkademy-guide="{{ $role }}:{{ $article }}"
    >
        {{ $text }}
    </button>
@endif
