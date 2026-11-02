<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

@php
    // Cloudflare fronts this domain and caches the icons for four hours, so a
    // replaced favicon keeps serving the old bytes long after it ships. Keying
    // the URL to the file's mtime makes each new icon a new URL, for the edge
    // and for anyone who already has one cached.
    $iconVersion = @filemtime(public_path('favicon.ico')) ?: 1;
@endphp
<link rel="icon" href="/favicon.ico?v={{ $iconVersion }}" sizes="any">
<link rel="icon" href="/favicon.svg?v={{ $iconVersion }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png?v={{ $iconVersion }}">

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
