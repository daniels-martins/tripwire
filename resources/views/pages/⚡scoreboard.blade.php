<?php

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('Scoreboard')] class extends Component {
    #[Url]
    public string $corridor = '';

    #[Url]
    public string $size = 'medium';

    public function updated(string $property): void
    {
        if (in_array($property, ['corridor', 'size'], true)) {
            unset($this->latency);
            $this->dispatch('latency-updated', latency: $this->latency);
        }
    }

    public function mount(): void
    {
        if ($this->corridor === '') {
            // Alphabetical order put BTC_ETH first, which only NEAR serves,
            // so the front page opened on a one-route table and read as if
            // the scanner only ever showed a winner. Open where there is a
            // contest; ties break alphabetically so the choice is stable.
            $this->corridor = DB::table('raw_quotes')
                ->where('ok', true)
                ->whereNotNull('spread_bps')
                ->groupBy('corridor')
                ->orderByRaw('COUNT(DISTINCT provider) DESC, corridor ASC')
                ->value('corridor')
                ?? ($this->corridors[0] ?? 'BTC_USDC');
        }
    }

    #[Computed]
    public function corridors(): array
    {
        return DB::table('raw_quotes')
            ->where('ok', true)
            ->distinct()
            ->orderBy('corridor')
            ->pluck('corridor')
            ->all();
    }

    #[Computed]
    public function corridorLabels(): array
    {
        $pairs = config('corridors.pairs');
        $labels = [];
        foreach ($pairs as $key => $config) {
            $labels[$key] = $config['label'] ?? $key;
        }

        return $labels;
    }

    #[Computed]
    public function sizeUsd(): int
    {
        return config("corridors.trade_sizes.{$this->size}", 1000);
    }

    /**
     * Every route, cheapest first, including the ones with no price.
     *
     * Listing only the routes that quoted made a corridor nobody else serves
     * look like a one-horse scoreboard, when the finding is the opposite:
     * three routes could not serve it at all. A blank row with a reason says
     * more than a missing row.
     */
    #[Computed]
    public function rows(): array
    {
        $priced = DB::table('raw_quotes')
            ->selectRaw("
                provider,
                COUNT(*) as sample_count,
                (percentile_cont(0.5) WITHIN GROUP (ORDER BY spread_bps))::numeric(12,4) as median_spread_bps,
                (percentile_cont(0.5) WITHIN GROUP (ORDER BY latency_ms))::integer as median_latency_ms,
                MAX(quoted_at) as last_seen_at
            ")
            ->where('corridor', $this->corridor)
            ->where('trade_size', $this->size)
            ->where('ok', true)
            ->whereNotNull('spread_bps')
            ->groupBy('provider')
            ->orderBy('median_spread_bps')
            ->get()
            ->keyBy('provider');

        // Why a route has no price here, in its own words.
        $reasons = DB::table('raw_quotes')
            ->selectRaw('DISTINCT ON (provider) provider, error')
            ->where('corridor', $this->corridor)
            ->where('trade_size', $this->size)
            ->where('ok', false)
            ->orderByRaw('provider, quoted_at DESC')
            ->pluck('error', 'provider');

        $rows = [];

        foreach (config('providers') as $slug => $definition) {
            if (! ($definition['enabled'] ?? false)) {
                continue;
            }

            if ($priced->has($slug)) {
                $row = $priced->get($slug);
                $row->quoted = true;
                $rows[] = $row;

                continue;
            }

            $rows[] = (object) [
                'provider' => $slug,
                'quoted' => false,
                'sample_count' => 0,
                'median_spread_bps' => null,
                'median_latency_ms' => null,
                'last_seen_at' => null,
                'reason' => $reasons[$slug] ?? 'No quote recorded',
            ];
        }

        // Priced routes first, cheapest at the top; the rest follow in the
        // order config lists them, which is stable.
        usort($rows, fn ($a, $b) => [! $a->quoted, $a->median_spread_bps ?? INF]
            <=> [! $b->quoted, $b->median_spread_bps ?? INF]);

        return $rows;
    }

    /** @return array<int, object> */
    #[Computed]
    public function pricedRows(): array
    {
        return array_values(array_filter($this->rows, fn ($r) => $r->quoted));
    }

    /**
     * Typical-to-tail latency per provider. Drawn as a P50 to P95 range rather
     * than two coloured series, because this is one measure's spread, not two
     * entities - colour stays reserved for provider identity.
     */
    #[Computed]
    public function latency(): array
    {
        return DB::table('raw_quotes')
            ->selectRaw("provider,
                (percentile_cont(0.5) WITHIN GROUP (ORDER BY latency_ms))::integer AS p50,
                (percentile_cont(0.95) WITHIN GROUP (ORDER BY latency_ms))::integer AS p95")
            ->where('corridor', $this->corridor)
            ->where('trade_size', $this->size)
            ->where('ok', true)
            ->whereNotNull('latency_ms')
            ->groupBy('provider')
            ->orderBy('p50')
            ->get()
            ->map(fn ($r) => [
                'name' => config('providers.'.$r->provider.'.name', $r->provider),
                'slot' => config('providers.'.$r->provider.'.slot', 1),
                'p50' => (int) $r->p50,
                'p95' => (int) $r->p95,
            ])->all();
    }

    /**
     * The finding, as three numbers: who wins, by how much, and how much of
     * the field was actually in the race.
     */
    #[Computed]
    public function headline(): object
    {
        $rows = $this->pricedRows;
        $best = $rows[0] ?? null;
        $next = $rows[1] ?? null;

        return (object) [
            'best' => $best ? config('providers.'.$best->provider.'.name', $best->provider) : null,
            'best_bps' => $best?->median_spread_bps,
            'edge_bps' => $best && $next
                ? round((float) $next->median_spread_bps - (float) $best->median_spread_bps, 1)
                : null,
            'routes' => count($rows),
            'offered' => count($this->rows),
            'samples' => array_sum(array_map(fn ($r) => (int) $r->sample_count, $rows)),
        ];
    }

    #[Computed]
    public function meta(): object
    {
        return DB::table('raw_quotes')->selectRaw(
            "MAX(quoted_at) as as_of, COUNT(*) as n, COUNT(*) FILTER (WHERE ok = true) as ok_count"
        )->first();
    }
}; ?>

@push('scripts')
    @include('partials.chart-script')
    <script>
        function latencyChart(initial) {
            return {
                chart: null,
                current: initial,
                options(rows) {
                    return {
                        ...vizBase(),
                        chart: { ...vizBase().chart, type: 'rangeBar', height: 60 + rows.length * 46 },
                        // A rangeBar whose ends are equal draws nothing, which happens
                        // whenever a provider has few samples. Give it a visible floor
                        // for drawing only; the tooltip still reports the true figures.
                        series: [{ data: rows.map(r => ({
                            x: r.name,
                            y: [r.p50, Math.max(r.p95, r.p50 + Math.max(1, r.p50 * 0.02))],
                            fillColor: vizToken('--color-series-' + r.slot),
                        })) }],
                        plotOptions: { bar: { horizontal: true, barHeight: '45%', borderRadius: 4 } },
                        stroke: { width: 0 },
                        xaxis: {
                            title: { text: 'Latency (ms)', style: { fontWeight: 500, fontSize: '12px' } },
                            tickAmount: window.innerWidth < 640 ? 3 : 6,
                            labels: { formatter: v => Math.round(v).toLocaleString() },
                        },
                        tooltip: { ...vizBase().tooltip, custom: ({ dataPointIndex }) => {
                            const r = rows[dataPointIndex];
                            return '<div style="padding:6px 10px">' + r.name +
                                   '<br>P50 ' + r.p50 + ' ms<br>P95 ' + r.p95 + ' ms</div>';
                        } },
                        legend: { show: false },
                    };
                },
                init() {
                    this.chart = new ApexCharts(this.$refs.chart, this.options(this.current));
                    this.chart.render();
                    vizWatchTheme(() => this.chart.updateOptions(this.options(this.current), true, false));
                },
                update(rows) {
                    this.current = rows;
                    this.chart.updateOptions(this.options(rows), true, true);
                },
            };
        }
    </script>
@endpush

<div class="min-h-screen bg-viz-plane text-viz-ink">
    <div class="mx-auto max-w-5xl px-6 py-10">

        <header class="mb-8">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Tripwire</h1>
                <p class="mt-1 text-sm text-viz-ink-soft">
                    Which cross-chain route actually gives you the best price - measured against
                    the CEX reference mid, not advertised.
                </p>
            </div>
        </header>

        <div class="mb-4 flex flex-wrap items-end gap-3">
            <label class="flex flex-col gap-1">
                <span class="text-xs font-medium text-viz-ink-muted">Corridor</span>
                <select wire:model.live="corridor"
                        class="rounded-md border border-viz-axis bg-viz-surface px-3 py-1.5 text-sm">
                    @foreach ($this->corridors as $c)
                        <option value="{{ $c }}">{{ $this->corridorLabels[$c] ?? $c }}</option>
                    @endforeach
                </select>
            </label>

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

        @if ($this->headline->best)
            <div class="mb-4 grid gap-px overflow-hidden rounded-xl border border-viz-grid bg-viz-grid sm:grid-cols-3">
                <div class="bg-viz-surface px-4 py-3.5">
                    <p class="stat-label">Cheapest route</p>
                    <p class="stat-value mt-1.5 flex items-baseline gap-2">
                        <span class="inline-block size-2.5 shrink-0 rounded-full"
                              style="background-color: var(--color-series-{{ config('providers.'.$this->rows[0]->provider.'.slot', 1) }})"></span>
                        <span class="truncate">{{ $this->headline->best }}</span>
                    </p>
                </div>
                <div class="bg-viz-surface px-4 py-3.5">
                    <p class="stat-label">Its spread</p>
                    <p class="stat-value mt-1.5">
                        {{ number_format($this->headline->best_bps, 1) }}
                        <span class="text-sm font-normal text-viz-ink-muted">bps</span>
                    </p>
                </div>
                <div class="bg-viz-surface px-4 py-3.5">
                    <p class="stat-label">{{ $this->headline->edge_bps !== null ? 'Edge over next' : 'Routes quoting' }}</p>
                    <p class="stat-value mt-1.5">
                        @if ($this->headline->edge_bps !== null)
                            {{ number_format($this->headline->edge_bps, 1) }}
                            <span class="text-sm font-normal text-viz-ink-muted">bps better</span>
                        @else
                            {{ $this->headline->routes }}
                            <span class="text-sm font-normal text-viz-ink-muted">of {{ count(array_filter(config('providers'), fn ($p) => $p['enabled'] ?? false)) }}</span>
                        @endif
                    </p>
                </div>
            </div>
        @endif

        <div class="overflow-x-auto rounded-xl border border-viz-grid bg-viz-surface">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-viz-grid text-left text-xs text-viz-ink-muted">
                        <th class="px-4 py-2.5 font-medium">Route</th>
                        <th class="px-4 py-2.5 text-right font-medium">Median spread</th>
                        <th class="px-4 py-2.5 text-right font-medium">Samples</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->rows as $i => $row)
                        <tr wire:key="row-{{ $row->provider }}"
                            class="border-b border-viz-grid/60 last:border-0 {{ $row->quoted ? '' : 'opacity-60' }}">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2.5 whitespace-nowrap">
                                    <span class="size-2.5 shrink-0 rounded-full"
                                          style="background-color: var(--color-series-{{ config('providers.'.$row->provider.'.slot', 1) }})"></span>
                                    <span class="font-medium">{{ config('providers.'.$row->provider.'.name', $row->provider) }}</span>
                                    @if ($i === 0 && $row->quoted)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-status-good/15 px-2 py-0.5 text-[11px] font-medium text-status-good">
                                            <svg class="size-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/>
                                            </svg>
                                            Best
                                        </span>
                                    @endif
                                </div>
                            </td>
                            @if ($row->quoted)
                                <td class="tabular px-4 py-2.5 text-right">{{ number_format($row->median_spread_bps, 1) }} bps</td>
                                <td class="tabular px-4 py-2.5 text-right text-viz-ink-soft">{{ number_format($row->sample_count) }}</td>
                            @else
                                <td class="px-4 py-2.5 text-right text-xs text-viz-ink-soft" colspan="2">{{ $row->reason }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-10 text-center text-sm text-viz-ink-muted">
                                No quotes yet for this corridor and size. Run <code class="rounded bg-viz-grid px-1.5 py-0.5">php artisan quotes:poll</code> to start collecting data.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (count($this->latency) > 0)
            <div class="mt-4 rounded-xl border border-viz-grid bg-viz-surface p-4">
                <h2 class="mb-1 text-sm font-semibold">Quote latency</h2>
                <p class="mb-2 text-xs text-viz-ink-muted">
                    Bar spans typical (P50) to tail (P95). A long bar means the route is
                    inconsistent, not just slow.
                </p>
                <div wire:ignore
                     x-data="latencyChart(@js($this->latency))"
                     x-on:latency-updated.window="update($event.detail.latency)">
                    <div x-ref="chart"></div>
                </div>
            </div>
        @endif

        <p class="mt-3 text-xs text-viz-ink-muted">
            Lower spread is better. Spread is measured in basis points against a reference rate derived from spot USD pairs.
            @if ($this->meta->as_of)
                As of {{ \Illuminate\Support\Carbon::parse($this->meta->as_of)->diffForHumans() }},
                {{ number_format($this->meta->ok_count) }}/{{ number_format($this->meta->n) }} successful quotes.
            @endif
        </p>
    </div>
</div>
