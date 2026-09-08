@props(['title' => config('app.name', 'NaaraSim')])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>

    <script>
        (function () {
            try {
                var stored = localStorage.getItem('theme');
                var wantsDark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.classList.toggle('dark', wantsDark);
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-[#F8F9FA] text-[#0F172A] antialiased dark:bg-navy dark:text-slate-100">
    @include('partials.icon-sprite')

    {{ $slot }}

    @livewireScripts
</body>
</html>
