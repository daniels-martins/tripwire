# Tripwire

**Live: https://tripwire.brainycyber.com**

A price comparison scanner for crypto bridges. Like Skyscanner, but for moving
crypto between chains.

It asks four bridges the same question every 15 minutes, in 8 corridors at 3
trade sizes: *"I hold X, I want Y, how much do I get?"* Each answer is compared
against a reference rate derived from spot USD pairs, and the difference is
recorded in basis points. Lower is better.

On top of that sits an investment thesis on NEAR made of **tripwires**:
falsifiable claims that re-check themselves against live data. If NEAR stops
being competitive, the page says so. A thesis that cannot fail is marketing.

## What you can do with it

| page | question it answers |
|---|---|
| Scoreboard | Which route is cheapest right now, and by how much? |
| Spread | Is that lead stable, or noise? |
| Size curve | Does the advantage survive a bigger trade? |
| Corridors | Who covers which routes? |
| Reliability | How often does each route actually answer? |
| Settlements | Did users receive what they were quoted? |
| Quote | Compare any of NEAR's 215 assets across 38 chains, at any amount |
| Thesis | Does the argument still hold? |

## Running it

```bash
git clone https://github.com/daniels-martins/tripwire.git
cd tripwire
composer setup          # installs, builds assets, migrates
php artisan registry:sync   # builds the asset list for the Quote page
php artisan quotes:poll     # collect a first batch
php artisan memo:generate   # build the thesis
php artisan dev             # serve
```

Needs PHP 8.4+, Node 22+ and PostgreSQL. The scheduler drives collection in
production; `routes/console.php` is the cadence of record.

## How it is built

Laravel 13 and Livewire 4, PostgreSQL with materialised views for the
leaderboard and spread series, Tailwind 4, charts via ApexCharts.

Quotes are **dry runs**. Nothing is ever signed, submitted or funded, and no
wallet or private key is involved anywhere in this codebase.

## What it does not claim

- NEAR Intents settles to its own ledger while the comparators deliver to a
  named chain. The economic question is the same; the destinations are not
  identical.
- Four of the eight polled corridors have no comparator, so they show NEAR
  alone. That is coverage, not a win.
- Settlement slippage is not yet trustworthy and the `execution` tripwire
  stays PENDING rather than assert a number nobody has audited.
- Chains with no configured address format are declined outright instead of
  sending a request we know is malformed.

`HACKATHON.md` has the architecture, the KPIs and the submission detail.
