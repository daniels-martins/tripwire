<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Every public page must render with an empty database. A judge clones the
 * repo and opens the site before any data exists, and an exception there is
 * the worst possible first impression. This is also what would have caught
 * the authenticated-layout crash the scoreboard shipped with.
 */
$routes = ['home', 'spread', 'size-curve', 'corridors', 'reliability', 'settlements', 'thesis'];

foreach ($routes as $name) {
    test("{$name} renders with no data", function () use ($name) {
        $this->get(route($name))->assertOk();
    });
}

test('the scoreboard is public', function () {
    $this->get(route('home'))->assertOk()->assertDontSee('Log in');
});
