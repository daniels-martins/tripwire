# Tripwire — NEAR Bridge Price Comparison & Investment Thesis

**Track**: Investment Thesis
**Team**: @Brainycyber

## What This Is

A price comparison scanner for crypto bridges — like Skyscanner, but for moving crypto between chains. It polls bridge quotes every 5 minutes, compares them against real market prices, and checks the receipts by pulling actual on-chain settlement data.

The scanner feeds an investment thesis report on NEAR, complete with tripwires — falsifiable claims that re-evaluate themselves on every run.

## Quick Start

```bash
git clone <repo-url> tripwire
cd tripwire
cp .env.example .env
docker compose up -d
docker compose exec laravel.test php artisan migrate --seed
```

Then open [http://localhost](http://localhost) in your browser.

## Do X, See Y

| Do | See |
|----|-----|
| Open `/` | Welcome page with project overview |
| Open `/dashboard` | Leaderboard of bridge providers ranked by spread |
| `GET /api/meta` | As-of timestamp, block heights, sample counts |
| `GET /api/corridors` | All trading pairs and which providers cover them |
| `GET /api/leaderboard?corridor=BTC_USDC&size=medium` | Providers ranked by median spread for BTC→USDC at $1,000 |
| `GET /api/spread-series?corridor=BTC_USDC&size=medium&provider=one-click` | Hourly spread time series |
| `GET /api/size-curve?corridor=BTC_USDC` | How spread changes with trade size |
| `GET /api/settlements` | Quoted vs. actually delivered amounts |
| `GET /api/tripwires` | Each investment thesis tripwire: HOLDING or TRIPPED |
| `php artisan memo:generate` | Regenerates `results.json` and the investment memo with live data |

## Architecture

One Laravel app. Livewire dashboard, materialized SQL views, public JSON API. The dashboard, the report, and the API all read the same views — they cannot disagree.

```
External APIs → Laravel Collectors → PostgreSQL → Dashboard / API / Memo
(1-Click, Binance,   (Scheduler, Queue)   (raw_quotes,      (Livewire, JSON,
 NearBlocks)                                mat. views)        results.json)
```

## Stack

- Laravel 13 + Livewire 4
- PostgreSQL 17
- Alpine.js + ApexCharts
- Docker Compose (Sail)

## Data Collection

The quote poller runs every 5 minutes across 8 trading corridors × 3 trade sizes × 5 bridge providers. Settlement data is ingested from the NEAR blockchain via NearBlocks/FastNEAR REST APIs.

## License

MIT
