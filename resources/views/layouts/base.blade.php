<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php
        $seo = $page['seo'] ?? [];
        $seoTitle = $seo['title'] ?? ($page['html_title'] ?? $page['title'] ?? config('app.name'));
        $seoDescription = $seo['description'] ?? ($page['description'] ?? config('app.name'));
        $seoCanonical = $seo['canonical'] ?? url()->current();
        $seoImage = $seo['image'] ?? asset('images/macau-bot.png');
        $seoRobots = $seo['robots'] ?? 'index,follow';
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seoTitle }} | {{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/macau-bot.svg') }}">
    <link rel="icon" type="image/png" href="{{ asset('images/macau-bot.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/macau-bot.png') }}">
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="robots" content="{{ $seoRobots }}">
    <link rel="canonical" href="{{ $seoCanonical }}">
    <meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
    <meta property="og:type" content="{{ $seo['type'] ?? 'website' }}">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $seoCanonical }}">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:image" content="{{ $seoImage }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $seoImage }}">
    @if (!empty($seo['structured_data']))
        @foreach ($seo['structured_data'] as $schema)
            <script type="application/ld+json">@json($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
        @endforeach
    @endif
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
