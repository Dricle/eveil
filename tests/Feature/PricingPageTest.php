<?php

afterEach(fn () => config()->set('eveil.edition', 'self'));

it('shows the credit grid on the hosted edition', function () {
    config()->set('eveil.edition', 'cloud');

    $this->get('/pricing')
        ->assertOk()
        ->assertSee('5,000')
        ->assertSee('No seats. No plans. No subscription.');
});

it('sends a self-hosted visitor to the application', function () {
    $this->get('/pricing')->assertRedirect('/app');
});
