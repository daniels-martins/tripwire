@php
    // Named from config rather than hard-coded: the reference venue is a
    // REFERENCE_BASE_URL away from changing, and this line went stale once
    // already when the deployment moved off api.binance.com.
    $referenceHost = parse_url((string) config('reference.base_url'), PHP_URL_HOST) ?: 'the reference venue';
@endphp
<footer class="mt-14 border-t border-viz-grid">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-6 py-5
                text-xs text-viz-ink-muted">
        <p>
            Quotes polled every 15 minutes. Reference rates derived from spot USD pairs on
            <a href="https://{{ $referenceHost }}" rel="noopener noreferrer" target="_blank">{{ $referenceHost }}</a>.
            Settlements read from
            <code class="rounded bg-viz-grid px-1 py-0.5">intents.near</code>.
        </p>
        <p>Spread is quoted output versus reference price, in basis points. Lower is better.</p>
    </div>
</footer>
