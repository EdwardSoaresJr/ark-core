<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ \App\Support\Branding\Branding::learnName() }} · Print</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="ops-learn-print-body">
        @yield('content')
    </body>
</html>
