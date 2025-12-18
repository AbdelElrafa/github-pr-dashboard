<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'PR Assistant') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.30.1/moment.min.js"></script>
    @livewireStyles
</head>
<body class="antialiased">
    {{ $slot }}
    @livewireScripts
</body>
</html>
