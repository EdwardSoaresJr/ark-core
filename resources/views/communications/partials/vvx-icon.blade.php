@php
    $icon = (string) ($icon ?? 'ic-flow');
@endphp
@if ($icon === 'ic-person')
<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4" fill="#fff"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6" fill="#fff"/></svg>
@elseif ($icon === 'ic-walk')
<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="5" r="2.5" fill="#fff"/><path d="M10 22l2-8 2 3 2 5" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
@elseif ($icon === 'ic-missed')
<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2l4 6H7l5 14 2-14H14z" fill="#fff"/></svg>
@elseif ($icon === 'ic-eye')
<svg viewBox="0 0 24 24" aria-hidden="true"><ellipse cx="12" cy="12" rx="9" ry="5" fill="none" stroke="#fff" stroke-width="2"/><circle cx="12" cy="12" r="2" fill="#fff"/></svg>
@elseif ($icon === 'ic-parts')
<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.7 6.3a4 4 0 00-5.4 5.4l-6.3 6.3a1.5 1.5 0 002.1 2.1l6.3-6.3a4 4 0 005.4-5.4l-2.1 2.1-2.1-2.1 2.1-2.1z" fill="#fff"/></svg>
@elseif ($icon === 'ic-approve')
<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2" fill="none" stroke="#fff" stroke-width="2"/><path d="M8 12l3 3 5-6" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
@elseif ($icon === 'ic-pay')
<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="none" stroke="#fff" stroke-width="2"/><path d="M7 12l3 3 6-7" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
@elseif ($icon === 'ic-car')
<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 11l2-4h10l2 4v7H5z" fill="#fff"/><circle cx="8" cy="16" r="2" fill="#000"/><circle cx="16" cy="16" r="2" fill="#000"/></svg>
@elseif ($icon === 'ic-phone')
<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h4l2 5-3 2a15 15 0 007 7l2-3 5 2v4c-8 1-16-7-17-17z" fill="#fff"/></svg>
@elseif ($icon === 'ic-lock')
<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="10" width="12" height="10" rx="2" fill="#fff"/><path d="M8 10V8a4 4 0 118 0v2" stroke="#fff" stroke-width="2" fill="none"/></svg>
@else
<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="#fff" stroke-width="2"/></svg>
@endif
