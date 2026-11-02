@if (\App\Support\DemoData::present())
    <div class="border-b border-status-warning/40 bg-status-warning/10">
        <div class="mx-auto flex max-w-6xl items-start gap-2 px-6 py-2.5">
            <svg class="mt-0.5 size-4 shrink-0 text-status-warning" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
            </svg>
            <p class="text-sm">
                <span class="font-medium">This database contains seeded demo data.</span>
                Figures on these pages, and in the generated memo, are fabricated for
                demonstration and were not measured. Run
                <code class="rounded bg-viz-grid px-1 py-0.5 text-xs">php artisan migrate:fresh</code>
                then <code class="rounded bg-viz-grid px-1 py-0.5 text-xs">quotes:poll</code>
                for real ones.
            </p>
        </div>
    </div>
@endif
