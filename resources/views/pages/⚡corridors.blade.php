<?php

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('Corridors')] class extends Component
{
    #[Url]
    public string $size = 'medium';

    public const PROVIDER_SLOTS = ['one-click' => 1];

    public const PROVIDER_LABELS = ['one-click' => '1-Click (NEAR Intents)'];

    #[Computed]
    public function corridorLabels(): array
    {
        return collect(config('corridors.pairs'))->map(fn ($c, $k) => $c['label'] ?? $k)->all();
    }

    /**
     * Best route per corridor at the selected size. DISTINCT ON takes the first
     * row of each corridor group, so the ORDER BY decides which one wins.
     */
    #[Computed]
    public function cards(): array
    {
        $rows = DB::table('v_leaderboard')
            ->selectRaw('DISTINCT ON (corridor) corridor, provider, median_spread_bps,
                         win_rate, median_latency_ms, sample_count, last_seen_at')
            ->where('trade_size', $this->size)
            ->orderByRaw('corridor, median_spread_bps ASC')
            ->get();

        $counts = DB::table('v_leaderboard')
            ->selectRaw('corridor, COUNT(DISTINCT provider) AS providers')
            ->where('trade_size', $this->size)
            ->groupBy('corridor')
            ->pluck('providers', 'corridor');

        return $rows->map(fn ($r) => (object) [
            ...(array) $r,
            'providers' => $counts[$r->corridor] ?? 1,
        ])->all();
    }

    /** Corridors configured but with nothing in the view yet — coverage is a finding. */
    #[Computed]
    public function missing(): array
    {
        $covered = collect($this->cards)->pluck('corridor')->all();

        return collect(config('corridors.pairs'))->keys()
            ->reject(fn ($k) => in_array($k, $covered, true))->values()->all();
    }
}; ?>

<div class="min-h-screen bg-viz-plane text-viz-ink">
    <div class="mx-auto max-w-5xl px-6 py-10">

        <header class="mb-8">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Corridors</h1>
                <p class="mt-1 text-sm text-viz-ink-soft">
                    Best route on each pair, at the selected size. Coverage matters as much as
                    price — a corridor only one route serves is a position, not a rounding error.
                </p>
            </div>
        </header>

        <div class="mb-4 flex flex-wrap items-end gap-3">
            <label class="flex flex-col gap-1">
                <span class="text-xs font-medium text-viz-ink-muted">Trade size</span>
                <select wire:model.live="size"
                        class="rounded-md border border-viz-axis bg-viz-surface px-3 py-1.5 text-sm">
                    @foreach (config('corridors.trade_sizes') as $key => $usd)
                        <option value="{{ $key }}">${{ number_format($usd) }} ({{ ucfirst($key) }})</option>
                    @endforeach
                </select>
            </label>
            <div wire:loading class="pb-2 text-xs text-viz-ink-muted">Updating…</div>
        </div>

        @if (count($this->cards) === 0)
            <div class="rounded-xl border border-viz-grid bg-viz-surface py-16 text-center text-sm text-viz-ink-muted">
                Nothing in <code class="rounded bg-viz-grid px-1.5 py-0.5">v_leaderboard</code> yet for this size.
                Poll, then refresh the view.
            </div>
        @else
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->cards as $card)
                    <div wire:key="card-{{ $card->corridor }}"
                         class="rounded-xl border border-viz-grid bg-viz-surface p-4">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="text-sm font-semibold">{{ $this->corridorLabels[$card->corridor] ?? $card->corridor }}</span>
                            <span class="text-xs text-viz-ink-muted">{{ $card->providers }} {{ Str::plural('route', $card->providers) }}</span>
                        </div>

                        <div class="mt-3 flex items-baseline gap-1.5">
                            <span class="text-2xl font-semibold tracking-tight">{{ number_format($card->median_spread_bps, 1) }}</span>
                            <span class="text-xs text-viz-ink-muted">bps median</span>
                        </div>

                        <div class="mt-2.5 flex items-center gap-2 text-sm">
                            <span class="size-2.5 shrink-0 rounded-full"
                                  style="background-color: var(--color-series-{{ self::PROVIDER_SLOTS[$card->provider] ?? 1 }})"></span>
                            <span class="truncate">{{ self::PROVIDER_LABELS[$card->provider] ?? $card->provider }}</span>
                        </div>

                        <dl class="mt-3 grid grid-cols-3 gap-2 border-t border-viz-grid/60 pt-3 text-xs">
                            <div>
                                <dt class="text-viz-ink-muted">Win rate</dt>
                                <dd class="tabular mt-0.5">{{ number_format($card->win_rate * 100, 0) }}%</dd>
                            </div>
                            <div>
                                <dt class="text-viz-ink-muted">Latency</dt>
                                <dd class="tabular mt-0.5">{{ $card->median_latency_ms ? number_format($card->median_latency_ms).' ms' : '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-viz-ink-muted">Samples</dt>
                                <dd class="tabular mt-0.5">{{ number_format($card->sample_count) }}</dd>
                            </div>
                        </dl>
                    </div>
                @endforeach
            </div>
        @endif

        @if (count($this->missing) > 0)
            <div class="mt-4 rounded-xl border border-viz-grid bg-viz-surface px-4 py-3">
                <p class="text-xs text-viz-ink-muted">
                    <span class="font-medium text-viz-ink-soft">No quotes yet:</span>
                    {{ collect($this->missing)->map(fn ($k) => $this->corridorLabels[$k] ?? $k)->join(', ') }}
                </p>
            </div>
        @endif
    </div>
</div>
