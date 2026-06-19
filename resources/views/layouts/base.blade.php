<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page['title'] ?? config('app.name') }} | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ $bodyClass ?? '' }}">
    <div class="flash-stack">
        @php($status = session(config('platform.flash_session_key')))
        @if (is_array($status))
            <article class="flash" data-flash data-tone="{{ $status['tone'] ?? 'neutral' }}">
                <strong class="flash-title">{{ $status['title'] ?? 'Status' }}</strong>
                <p class="flash-message">{{ $status['message'] ?? '' }}</p>
            </article>
        @endif
    </div>

    <div class="page-shell">
        @yield('body')
    </div>
</body>
</html>
