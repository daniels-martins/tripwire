<?php

use App\Services\TokenRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

/*
 * Every public page must render with an empty database. A judge clones the
 * repo and opens the site before any data exists, and an exception there is
 * the worst possible first impression. This is also what would have caught
 * the authenticated-layout crash the scoreboard shipped with.
 */
$routes = ['home', 'spread', 'size-curve', 'corridors', 'reliability', 'settlements', 'thesis', 'quote'];

foreach ($routes as $name) {
    test("{$name} renders with no data", function () use ($name) {
        $this->get(route($name))->assertOk();
    });
}

test('the scoreboard is public', function () {
    $this->get(route('home'))->assertOk()->assertDontSee('Log in');
});

test('the quote page says so when the registry has not been built', function () {
    // The page must not blow up before registry:sync has ever run; a judge
    // opening a fresh clone hits exactly this state, so put it there.
    File::deleteDirectory(
        storage_path('app/'.TokenRegistry::DIRECTORY)
    );
    cache()->forget('registry:tokens');
    cache()->forget('registry:chains');

    $this->get(route('quote'))->assertOk()->assertSee('registry:sync');
});
