@props(['name' => '', 'initials' => '', 'size' => null])

@php
    $name = trim((string) $name);
    $initials = trim((string) $initials);
    if ($initials === '' && $name !== '') {
        $words = preg_split('/\s+/', preg_replace('/[^\pL\pN\s]/u', '', $name) ?: '') ?: [];
        $initials = mb_strtoupper(implode('', array_map(fn (string $w): string => mb_substr($w, 0, 1), array_slice(array_filter($words), 0, 2))));
    }
    // The same name always gets the same colour; a phone number or an unknown contact stays neutral.
    $tones = ['blue', 'azure', 'indigo', 'purple', 'pink', 'red', 'orange', 'yellow', 'lime', 'green', 'teal', 'cyan'];
    $tone = $initials !== '' && preg_match('/\pL/u', $initials) ? $tones[crc32(mb_strtolower($name)) % count($tones)] : 'secondary';
@endphp

<span {{ $attributes->class(['avatar', 'avatar-'.$size => filled($size), 'bg-'.$tone.'-lt']) }} aria-hidden="true">{{ $initials !== '' ? $initials : '•' }}</span>
