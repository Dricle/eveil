<?php

afterEach(fn () => config()->set('eveil.edition', 'self'));

it('shows the credit grid on the hosted edition', function () {
    config()->set('eveil.edition', 'cloud');

    $this->get('/pricing')
        ->assertOk()
        ->assertSee('5,000')
        ->assertSee('No seats. No plans. No subscription.')
        ->assertSee('Planning where to look for companies')
        ->assertSee('Reading a reply and deciding what to do with it')
        ->assertSee('Asking Evie a question');
});

it('sends a self-hosted visitor to the application', function () {
    $this->get('/pricing')->assertRedirect('/app');
});
