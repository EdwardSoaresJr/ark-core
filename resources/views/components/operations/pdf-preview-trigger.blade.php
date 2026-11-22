@props(['preview'])

{{-- Opens the shared inline PDF viewer (operations.documents.partials.inline-pdf-viewer). Without JS or the viewer it is a plain new-tab link. --}}
<a
    href="{{ $preview['new_tab_url'] }}"
    target="_blank"
    rel="noopener"
    data-ark-pdf-preview
    @if ($preview['document_id'] ?? null) data-document-id="{{ $preview['document_id'] }}" @endif
    data-pdf-title="{{ $preview['title'] }}"
    data-pdf-view-url="{{ $preview['view_url'] }}"
    data-pdf-download-url="{{ $preview['download_url'] }}"
    data-pdf-new-tab-url="{{ $preview['new_tab_url'] }}"
    @if ($preview['cache_bust'] ?? false) data-pdf-cache-bust @endif
    {{ $attributes }}
>{{ $slot }}</a>
