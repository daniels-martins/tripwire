<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('Spread over time')] class extends Component
{
    #[Url]
    public string $corridor = '';

    #[Url]
    public string $size = 'medium';

    public const PROVIDER_SLOTS = [
        'one-click' => 1,
    ];

    public const PROVIDER_LABELS = [
        'one-click' => '1-Click (NEAR Intents)',
    ];

    public function mount(): void
    {
        if ($this->corridor === '') {
            $this->corridor = $this->corridors[0] ?? 'BTC_USDC';
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['corridor', 'size'], true)) {
            unset($this->series);
            $this->dispatch('spread-updated', series: $this->series);
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
        return collect(config('corridors.pairs'))
            ->map(fn ($c, $k) => $c['label'] ?? $k)->all();
    }

    /**
     * Bucket width follows the span of data we actually have: five minutes while
     * the run is young, hourly once there is more than six hours of it. Fixed
     * hourly buckets would collapse a fresh run into a single point.
     */
    #[Computed]
    public function bucketSeconds(): int
    {
        $span = (float) DB::table('raw_quotes')
            ->where('corridor', $this->corridor)
            ->where('trade_size', $this->size)
            ->selectRaw('EXTRACT(EPOCH FROM (MAX(quoted_at) - MIN(quoted_at))) AS s')
            ->value('s');

        return match (true) {
            $span > 21600 => 3600,   // over 6h of data: hourly
            $span > 1800 => 300,     // over 30m: five-minute
            default => 60,           // young run: per minute
        };
    }

    #[Computed]
    public function series(): array
    {
        $bucket = in_array($this->bucketSeconds, [60, 300, 3600], true) ? $this->bucketSeconds : 300;
        $expr = "to_timestamp(floor(extract(epoch from quoted_at) / {$bucket}) * {$bucket})";

        return DB::table('raw_quotes')
            ->selectRaw("provider, {$expr} AS bucket,
                (percentile_cont(0.5) WITHIN GROUP (ORDER BY spread_bps))::numeric(12,4) AS spread")
            ->where('corridor', $this->corridor)
            ->where('trade_size', $this->size)
            ->where('ok', true)
            ->whereNotNull('spread_bps')
            ->groupByRaw("provider, {$expr}")
            ->orderBy('bucket')
            ->get()
            ->groupBy('provider')
            ->map(fn ($points, $provider) => [
                'name' => self::PROVIDER_LABELS[$provider] ?? $provider,
                'slot' => self::PROVIDER_SLOTS[$provider] ?? 1,
                'data' => $points->map(fn ($r) => [
                    'x' => Carbon::parse($r->bucket)->valueOf(),
                    'y' => round((float) $r->spread, 2),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    #[Computed]
    public function pointCount(): int
    {
        return collect($this->series)->sum(fn ($s) => count($s['data']));
    }
}; ?>

@push('scripts')
    @include('partials.chart-script')
    <script>
        function spreadChart(initial) {
            return {
                chart: null,
                options(series) {
                    return {
                        ...vizBase(),
                        chart: { ...vizBase().chart, type: 'line', height: 360 },
                        series: series.map(s => ({ name: s.name, data: s.data })),
                        colors: series.map(s => vizToken('--color-series-' + s.slot)),
                        xaxis: { type: 'datetime', labels: { datetimeUTC: false } },
                        yaxis: {
                            title: { text: 'Median spread (bps)', style: { fontWeight: 500, fontSize: '12px' } },
                            decimalsInFloat: 1,
                            labels: { formatter: v => v == null ? '' : v.toFixed(1) },
                        },
                        markers: { size: series.some(s => s.data.length < 3) ? 5 : 0, hover: { size: 6 } },
                        legend: { ...vizBase().legend, position: 'top', horizontalAlign: 'left' },
                    };
                },
                init() {
                    this.chart = new ApexCharts(this.$refs.chart, this.options(initial));
                    this.chart.render();
                    vizWatchTheme(() => this.chart.updateOptions(this.options(this.current), true, false));
                    this.current = initial;
                },
                current: [],
                update(series) {
                    this.current = series;
                    this.chart.updateOptions(this.options(series), true, true);
                },
            };
        }
    </script>
@endpush

<div class="min-h-screen bg-viz-plane text-viz-ink">
    <div class="mx-auto max-w-5xl px-6 py-10">

        <header class="mb-8 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Spread over time</h1>
                <p class="mt-1 text-sm text-viz-ink-soft">
                    Median quoted spread against the Binance mid, bucketed over the polling run.
                    Lower is better.
                </p>
            </div>
            <flux:radio.group x-data variant="segmented" size="sm" x-model="$flux.appearance">
                <flux:radio value="light" icon="sun" />
                <flux:radio value="dark" icon="moon" />
                <flux:radio value="system" icon="computer-desktop" />
            </flux:radio.group>
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

        <div class="rounded-xl border border-viz-grid bg-viz-surface p-4">
            @if ($this->pointCount === 0)
                <p class="py-16 text-center text-sm text-viz-ink-muted">
                    No successful quotes yet for this corridor and size.
                </p>
            @else
                <div wire:ignore
                     x-data="spreadChart(@js($this->series))"
                     x-on:spread-updated.window="update($event.detail.series)">
                    <div x-ref="chart"></div>
                </div>
            @endif
        </div>

        <p class="mt-3 text-xs text-viz-ink-muted">
            Buckets are {{ ['60' => 'per-minute', '300' => '5-minute', '3600' => 'hourly'][(string) $this->bucketSeconds] }} medians.
            Points are shown while a series is still short.
        </p>
    </div>
</div>
