@php
    $icon = (string) ($icon ?? 'car');
@endphp
@if ($icon === 'wrench')
<svg viewBox="0 0 24 16" aria-hidden="true"><path d="M14.7 2.3a4 4 0 00-5.4 5.4L2 15l1 1 7.3-7.3a4 4 0 005.4-5.4l-2.1 2.1-2.1-2.1 2.1-2.1z" fill="#cbd5e1"/></svg>
@elseif ($icon === 'parts')
<svg viewBox="0 0 24 16" aria-hidden="true"><rect x="4" y="3" width="16" height="10" rx="1" fill="none" stroke="#cbd5e1" stroke-width="2"/><path d="M8 8h8" stroke="#cbd5e1" stroke-width="2"/></svg>
@elseif ($icon === 'ready')
<svg viewBox="0 0 24 16" aria-hidden="true"><circle cx="12" cy="8" r="7" fill="none" stroke="#22c55e" stroke-width="2"/><path d="M7 8l3 3 6-6" stroke="#22c55e" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
@elseif ($icon === 'eye')
<svg viewBox="0 0 24 16" aria-hidden="true"><ellipse cx="12" cy="8" rx="9" ry="4" fill="none" stroke="#cbd5e1" stroke-width="2"/><circle cx="12" cy="8" r="2" fill="#cbd5e1"/></svg>
@else
<svg viewBox="0 0 24 16" aria-hidden="true"><path d="M4 10l2-5h12l2 5v4H4z" fill="#cbd5e1"/><circle cx="7" cy="12" r="1.5" fill="#000"/><circle cx="17" cy="12" r="1.5" fill="#000"/></svg>
@endif
