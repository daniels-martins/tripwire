<?php

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('Size impact')] class extends Component
{
    #[Url]
    public string $corridor = '';

    public function mount(): void
    {
        if ($this->corridor === '') {
            $this->corridor = $this->corridors[0] ?? 'BTC_USDC';
        }
    }

    public function updated(string $property): void
    {
        if ($property === 'corridor') {
            unset($this->series);
            $this->dispatch('size-curve-updated', series: $this->series, categories: $this->categories);
        }
    }

    #[Computed]
    public function corridors(): array
    {
        return DB::table('raw_quotes')->where('ok', true)
            ->distinct()->orderBy('corridor')->pluck('corridor')->all();
    }

    #[Computed]
    public function corridorLabels(): array
    {
        return collect(config('corridors.pairs'))->map(fn ($c, $k) => $c['label'] ?? $k)->all();
    }

    /** Ordered small → large, labelled with the USD amount they map to. */
    #[Computed]
    public function categories(): array
    {
        return collect(config('corridors.trade_sizes'))
            ->map(fn ($usd) => '$'.number_format($usd))->values()->all();
    }

    #[Computed]
    public function series(): array
    {
        $order = array_keys(config('corridors.trade_sizes'));

        $rows = DB::table('raw_quotes')
            ->selectRaw("provider, trade_size,
                (percentile_cont(0.5) WITHIN GROUP (ORDER BY spread_bps))::numeric(12,4) AS spread")
            ->where('corridor', $this->corridor)
            ->where('ok', true)
            ->whereNotNull('spread_bps')
            ->groupBy('provider', 'trade_size')
            ->get();

        return $rows->groupBy('provider')
            ->map(function ($points, $provider) use ($order) {
                $bySize = $points->keyBy('trade_size');

                return [
                    'name' => config('providers.'.$provider.'.name', $provider),
                    'slot' => config('providers.'.$provider.'.slot', 1),
                    // null keeps the category slot so bars stay aligned across providers
                    'data' => array_map(
                        fn ($size) => isset($bySize[$size]) ? round((float) $bySize[$size]->spread, 2) : null,
                        $order
                    ),
                ];
            })->values()->all();
    }
}; ?>

@push('scripts')
    @include('partials.chart-script')
    <script>
        function sizeCurveChart(initialSeries, initialCategories) {
            return {
                chart: null,
                current: initialSeries,
                categories: initialCategories,
                options(series, categories) {
                    return {
                        ...vizBase(),
                        chart: { ...vizBase().chart, type: 'bar', height: 360 },
                        series: series.map(s => ({ name: s.name, data: s.data })),
                        colors: series.map(s => vizToken('--color-series-' + s.slot)),
                        plotOptions: { bar: { columnWidth: series.length > 1 ? '70%' : '40%', borderRadius: 4, borderRadiusApplication: 'end' } },
                        stroke: { width: 0 },
                        xaxis: { categories, title: { text: 'Trade size', style: { fontWeight: 500, fontSize: '12px' } } },
                        yaxis: {
                            title: { text: 'Median spread (bps)', style: { fontWeight: 500, fontSize: '12px' } },
                            decimalsInFloat: 1,
                        },
                        legend: { ...vizBase().legend, position: 'top', horizontalAlign: 'left' },
                    };
                },
                init() {
                    this.chart = new ApexCharts(this.$refs.chart, this.options(this.current, this.categories));
                    this.chart.render();
                    vizWatchTheme(() => this.chart.updateOptions(this.options(this.current, this.categories), true, false));
                },
                update(series, categories) {
                    this.current = series;
                    this.categories = categories;
                    this.chart.updateOptions(this.options(series, categories), true, true);
                },
            };
        }
    </script>
@endpush

<div class="min-h-screen bg-viz-plane text-viz-ink">
    <div class="mx-auto max-w-5xl px-6 py-10">

        <header class="mb-8">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Size impact</h1>
                <p class="mt-1 text-sm text-viz-ink-soft">
                    How each route's spread changes with trade size. A route that is cheap small and
                    expensive large has thin liquidity, and that is where depth shows.
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
            <div wire:loading class="pb-2 text-xs text-viz-ink-muted">Updating…</div>
        </div>

        <div class="rounded-xl border border-viz-grid bg-viz-surface p-4">
            @if (count($this->series) === 0)
                <p class="py-16 text-center text-sm text-viz-ink-muted">
                    No successful quotes yet for this corridor.
                </p>
            @else
                <div wire:ignore
                     x-data="sizeCurveChart(@js($this->series), @js($this->categories))"
                     x-on:size-curve-updated.window="update($event.detail.series, $event.detail.categories)">
                    <div x-ref="chart"></div>
                </div>
            @endif
        </div>

        <p class="mt-3 text-xs text-viz-ink-muted">
            Median spread in basis points against a reference rate derived from spot USD pairs. A gap means that route returned
            no successful quote at that size.
        </p>
    </div>
</div>
