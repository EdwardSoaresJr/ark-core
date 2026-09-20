<x-mail::message>
# ARK exception report

**Report ID:** `{{ $context['report_id'] ?? 'unknown' }}`

Reference this ID on the VPS or in your editor when investigating.

@if (! empty($context['report_copy_url']))
<x-mail::button :url="$context['report_copy_url']">
Open copy-friendly report
</x-mail::button>

The copy page includes one-click **Copy markdown** for pasting into an investigation note.
@endif

An `ark-error-{{ $context['report_id'] ?? 'unknown' }}.md` attachment is included for paste-friendly context.

## VPS lookup

```
{{ $context['report_vps_command'] ?? 'php artisan errors:recent --id='.($context['report_id'] ?? '') }}
@if (! empty($context['report_filename']))
php artisan errors:recent --show={{ $context['report_filename'] }}
@endif
```

---

An error occurred in **{{ strtoupper($context['environment'] ?? config('app.env')) }}**.

**Exception:** `{{ $context['exception_class'] }}`

**Message:** {{ $context['exception_message'] ?: 'No message provided.' }}

@if (! empty($context['status_code']))
**Status:** {{ $context['status_code'] }}
@endif

@if (! empty($context['url']))
**URL:** {{ $context['method'] ?? 'GET' }} {{ $context['url'] }}
@endif

@if (! empty($context['route']))
**Route:** {{ $context['route'] }}
@endif

@if (! empty($context['user_email']))
**Staff user:** {{ $context['user_email'] }} (#{{ $context['user_id'] }})
@elseif (! empty($context['user_id']))
**Staff user id:** {{ $context['user_id'] }}
@else
**Staff user:** guest
@endif

@if (! empty($context['ip']))
**IP:** {{ $context['ip'] }}
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
