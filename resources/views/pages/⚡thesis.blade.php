<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('Investment thesis')] class extends Component
{
    #[Computed]
    public function tripwires(): array
    {
        return DB::table('tripwires')->orderBy('id')->get()
            ->map(function ($t) {
                $t->config = is_string($t->config) ? json_decode($t->config, true) : ($t->config ?? []);

                return $t;
            })->all();
    }

    #[Computed]
    public function counts(): array
    {
        return collect($this->tripwires)->countBy('status')->all();
    }

    #[Computed]
    public function memoHtml(): ?string
    {
        // GenerateMemo writes with storage_path('app/memo'), while the `local`
        // disk roots at storage/app/private — so read the path it actually uses.
        $path = storage_path('app/memo/investment-memo.md');

        if (! File::exists($path)) {
            return null;
        }

        // Drop the memo's own tripwire table — the cards above already carry it,
        // and the same numbers twice on one page is padding, not evidence.
        $markdown = preg_replace(
            '/^## Tripwire Panel.*?(?=^## )/ms',
            '',
            File::get($path)
        );

        // The memo puts Generated / Data window / Total quotes on their own lines;
        // CommonMark folds single newlines into one paragraph, so keep the breaks.
        $html = Str::markdown($markdown, ['renderer' => ['soft_break' => '<br>']]);

        // CommonMark emits bare <table> elements. Without a wrapper they are wider
        // than a phone viewport and drag the whole page sideways, so give each one
        // its own scroll container.
        return preg_replace(
            '/<table>(.*?)<\/table>/s',
            '<div class="memo-scroll"><table>$1</table></div>',
            $html
        );
    }

    #[Computed]
    public function generatedAt(): ?string
    {
        return collect($this->tripwires)->max('last_evaluated_at');
    }

    /**
     * Several tripwires compare NEAR against "the market". While one provider is
     * the only one quoting, that market is NEAR, so those readings are circular
     * and the page should say so rather than let them read as findings.
     */
    #[Computed]
    public function providerCount(): int
    {
        return DB::table('raw_quotes')->where('ok', true)->distinct()->count('provider');
    }
}; ?>

<div class="min-h-screen bg-viz-plane text-viz-ink">
    <div class="mx-auto max-w-3xl px-6 py-10">

        <header class="mb-8">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Investment thesis</h1>
                <p class="mt-1 text-sm text-viz-ink-soft">
                    Regenerated from live data. The numbers refresh; the argument does not.
                </p>
            </div>
        </header>

        @if (count($this->tripwires) === 0)
            <div class="rounded-xl border border-viz-grid bg-viz-surface py-16 text-center text-sm text-viz-ink-muted">
                No tripwires evaluated yet. Run
                <code class="rounded bg-viz-grid px-1.5 py-0.5">php artisan memo:generate</code>.
            </div>
        @else
            <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                <span>
                    <span class="font-semibold text-status-good">{{ $this->counts['HOLDING'] ?? 0 }}</span>
                    <span class="text-viz-ink-muted">holding</span>
                </span>
                <span>
                    <span class="font-semibold text-status-critical">{{ $this->counts['TRIPPED'] ?? 0 }}</span>
                    <span class="text-viz-ink-muted">tripped</span>
                </span>
                <span>
                    <span class="font-semibold text-viz-ink-soft">{{ $this->counts['PENDING'] ?? 0 }}</span>
                    <span class="text-viz-ink-muted">awaiting data</span>
                </span>
                @if ($this->generatedAt)
                    <span class="text-xs text-viz-ink-muted">
                        evaluated {{ \Illuminate\Support\Carbon::parse($this->generatedAt)->diffForHumans() }}
                    </span>
                @endif
            </div>

            @if ($this->providerCount < 2)
                <div class="mb-4 flex items-start gap-2 rounded-lg border border-status-warning/40 bg-status-warning/10 px-4 py-3">
                    <svg class="mt-0.5 size-4 shrink-0 text-status-warning" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                    </svg>
                    <p class="text-sm">
                        <span class="font-medium">Only one route is quoting.</span>
                        Tripwires that compare NEAR against the market — win rate and spread edge —
                        are measuring NEAR against itself until a second provider is polling. Read
                        those two as not yet tested rather than as results.
                    </p>
                </div>
            @endif

            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($this->tripwires as $t)
                    <x-tripwire-card :tripwire="$t" wire:key="tw-{{ $t->id }}" />
                @endforeach
            </div>
        @endif

        @if ($this->memoHtml)
            <article class="memo mt-10">{!! $this->memoHtml !!}</article>
        @else
            <p class="mt-10 text-sm text-viz-ink-muted">
                The memo has not been generated yet.
            </p>
        @endif
    </div>
</div>
