@props(['title' => 'Vehicle Inspection'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" data-accent="ark2">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.branding._favicons')
</head>
<body class="ops-inspection-tablet-shell min-h-full bg-slate-100 text-slate-950 antialiased">
    {{ $slot }}
    <x-operations.image-lightbox />
</body>
</html>
