# Tripwire — NEAR Bridge Price Comparison & Investment Thesis

**Track**: Investment Thesis
**Team**: @Brainycyber

## What This Is

A price comparison scanner for crypto bridges — like Skyscanner, but for moving crypto between chains. It polls bridge quotes every 5 minutes across 8 trading corridors, compares them against real Binance spot prices, and checks the receipts by pulling actual on-chain settlement data from NEAR's `intents.near` contract.

The scanner feeds an investment thesis report on NEAR, complete with tripwires — falsifiable claims that re-evaluate themselves on every run. If a tripwire trips, the thesis needs revision.

## Quick Start (Zero API Keys Required)

```bash
git clone <repo-url> tripwire
cd tripwire
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail shell
npm install && npm run build
bash docker/entrypoint.sh
exit
```

Then open [http://localhost](http://localhost) in your browser.

**The seeded data is fabricated.** It exists so a clean clone has something to show, and every
page carries a banner saying so while it is present. For measured figures, run
`php artisan migrate:fresh` then the poller below. The setup script migrates the database,
seeds 7 days of demo data (~12,000 quotes, ~140 settlements across 4 corridors), refreshes all materialized views, and generates the investment memo with tripwire evaluations.

## Routes compared

| Route | Status | Notes |
|-------|--------|-------|
| NEAR 1-Click | live | Quotes all 8 corridors |
| LiFi | live | Cross-chain aggregator; 3 corridors |
| Relay | live | Adds Bitcoin as a destination; 4 corridors |
| deBridge | live | Order-based liquidity network; 3 corridors |
| Across | disabled | Moves the *same* asset between chains, so it cannot quote a cross-asset corridor |
| Stargate | disabled | Quote API deprecated; successor endpoint 404s for these pairs |

Four of the eight corridors are uncontested — BTC as a *source* cannot be dry-quoted without a
funded wallet, and no comparator supports NEAR as a chain. On those, NEAR Intents is the only
route that exists, which is itself part of the finding.

## Do X, See Y

| Do | See |
|----|-----|
| Open `/` | Scoreboard — routes ranked by median spread, with latency spread |
| Open `/spread` | Median spread over time, one line per route |
| Open `/size-curve` | How spread changes between $100, $1,000 and $10,000 |
| Open `/corridors` | Best route per pair, and the pairs nobody quotes |
| Open `/reliability` | Success-rate heatmap per route × corridor |
| Open `/settlements` | Real swaps from `intents.near`, hashes linked to NearBlocks |
| Open `/thesis` | The memo and five tripwires, HOLDING / TRIPPED / PENDING |
| `GET /api/meta` | As-of timestamp, block heights, sample counts |
| `GET /api/corridors` | All trading pairs, provider counts, success rates |
| `GET /api/leaderboard?corridor=BTC_USDC&size=medium` | Providers ranked by median spread for BTC→USDC at $1,000 |
| `GET /api/spread-series?corridor=BTC_USDC` | Hourly spread time series |
| `GET /api/size-curve?corridor=BTC_USDC` | How spread changes with trade size |
| `GET /api/settlements?corridor=BTC_USDC` | Settlement accuracy per corridor |
| `GET /api/tripwires` | Each investment thesis tripwire: HOLDING, TRIPPED, or PENDING |
| `./vendor/bin/sail artisan memo:generate` | Regenerates `results.json` and the investment memo |
| `cat storage/app/memo/investment-memo.md` | The generated investment thesis with live data |
| `cat storage/app/memo/results.json` | Every computation in one JSON file |
| `php artisan quotes:poll` | Queues one job per corridor × size × route |
| `php artisan queue:work --stop-when-empty` | **Required** — `quotes:poll` only queues; nothing lands without a worker |

## Architecture

```
External APIs → Laravel Collectors → PostgreSQL → Dashboard / API / Memo
(1-Click, Binance,   (Scheduler, Queue)   (raw_quotes,      (Livewire, JSON,
 NearBlocks)                                mat. views)        results.json)
```

One Laravel app. Livewire dashboard, materialized SQL views, public JSON API. The dashboard, the report, and the API all read the same materialized views — they cannot disagree.

### Data Pipeline

1. **Quote Poller** (`quotes:poll`) — every 5 min, dispatches one job per corridor × size × provider. Each job: fetch dry-run quote → fetch Binance spot price → compute spread in bps → store row.
2. **Settlement Ingester** (`settlements:ingest`) — pulls `ft_on_transfer` receipts from `intents.near` via NearBlocks API, maps token contracts to corridors, normalizes amounts.
3. **Materialized Views** (`views:refresh`) — five views aggregate raw data into leaderboard, spread series, size curve, settlement accuracy, and corridor coverage.
4. **Memo Generator** (`memo:generate`) — evaluates tripwires, gathers all metrics, writes `results.json` + investment memo from template.

### Tripwires

| Name | Condition | What It Tests |
|------|-----------|---------------|
| Competitiveness | NEAR median spread < 50 bps | Is NEAR price-competitive? |
| Dominance | NEAR win rate > 40% | Does NEAR win most quotes? |
| Execution | Settlement slippage < 50 bps | Do users get what they're quoted? |
| Corridor Coverage | ≥ 4 corridors active | Does NEAR have broad asset coverage? |
| Bear Case | Spread edge > 5 bps | When does the thesis break? |

## Stack

- Laravel 13 + Livewire 4 + Flux Pro
- PostgreSQL 17 with materialized views
- Alpine.js + ApexCharts
- Docker Compose (Laravel Sail)

## Corridors

8 trading pairs × 3 trade sizes ($100, $1,000, $10,000):

BTC → USDC, ETH → USDC, NEAR → USDC, SOL → USDC, BTC → ETH, USDC → NEAR, ETH → BTC, SOL → ETH

## License

MIT
