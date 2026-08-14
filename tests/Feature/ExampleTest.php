<?php

use App\Models\User;

test('returns a successful response', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    $response->assertOk();
});
