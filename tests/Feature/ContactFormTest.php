<?php

use App\Mail\ContactFormMail;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config()->set('eveil.edition', 'cloud');
});

it('emails support with the submitted message', function () {
    Mail::fake();

    $this->post(route('contact.store'), [
        'name' => 'Jamie Prospect',
        'email' => 'jamie@example.com',
        'topic' => 'Billing and credits',
        'message' => 'How does the trial work?',
    ])->assertRedirect();

    Mail::assertQueued(ContactFormMail::class, function (ContactFormMail $mail): bool {
        return $mail->hasTo('support@eveil.cloud')
            && $mail->senderEmail === 'jamie@example.com'
            && $mail->topic === 'Billing and credits';
    });
});

it('lets support reply straight to the sender', function () {
    Mail::fake();

    $this->post(route('contact.store'), [
        'name' => 'Jamie Prospect',
        'email' => 'jamie@example.com',
        'topic' => 'Getting started',
        'message' => 'Quick question.',
    ]);

    Mail::assertQueued(ContactFormMail::class, fn (ContactFormMail $mail): bool => $mail->hasReplyTo('jamie@example.com'));
});

it('rejects a message with no email', function () {
    Mail::fake();

    $this->post(route('contact.store'), [
        'name' => 'Jamie Prospect',
        'topic' => 'Getting started',
        'message' => 'Quick question.',
    ])->assertSessionHasErrors('email');

    Mail::assertNothingSent();
});

it('rejects a topic outside the fixed list', function () {
    Mail::fake();

    $this->post(route('contact.store'), [
        'name' => 'Jamie Prospect',
        'email' => 'jamie@example.com',
        'topic' => 'Anything goes',
        'message' => 'Quick question.',
    ])->assertSessionHasErrors('topic');

    Mail::assertNothingSent();
});

it('throttles repeated submissions', function () {
    Mail::fake();

    $payload = [
        'name' => 'Jamie Prospect',
        'email' => 'jamie@example.com',
        'topic' => 'Getting started',
        'message' => 'Quick question.',
    ];

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('contact.store'), $payload)->assertRedirect();
    }

    $this->post(route('contact.store'), $payload)->assertStatus(429);
});
