<?php

use App\Models\User;

test('guests can view the public dashboard', function () {
    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});
