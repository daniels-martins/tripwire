<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('responses carry the headers Nginx does not set', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    expect($response->headers->get('Permissions-Policy'))->toContain('geolocation=()');

    // Nginx owns these two and appends them after PHP. Setting them here as
    // well sent each one twice, so the app must leave them alone.
    expect($response->headers->has('X-Frame-Options'))->toBeFalse()
        ->and($response->headers->has('X-Content-Type-Options'))->toBeFalse();
});

test('HSTS is sent only over TLS', function () {
    // Promising HSTS over plain HTTP would be a promise we cannot keep.
    expect($this->get(route('home'))->headers->get('Strict-Transport-Security'))->toBeNull();

    expect($this->get('https://localhost'.route('home', absolute: false))
        ->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000');
});
