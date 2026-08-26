<?php

use App\Enums\EmailExampleSource;
use App\Models\EmailExample;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;

it('keeps the bank away from an ordinary user', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('app-settings.email-examples.index'))
        ->assertForbidden();
});

it('adds a pasted example', function () {
    $this->actingAs(superAdmin())
        ->post(route('app-settings.email-examples.store'), [
            'subject' => 'vos commandes en ligne',
            'body' => 'Bonjour, ...',
        ])
        ->assertSessionHasNoErrors();

    $example = EmailExample::query()->sole();

    expect($example->subject)->toBe('vos commandes en ligne')
        ->and($example->source)->toBe(EmailExampleSource::Manual)
        ->and($example->added_by_user_id)->not->toBeNull();
});

it('adds an example from an uploaded .eml file', function () {
    $raw = "From: sender@example.test\r\n"
        ."To: prospect@example.test\r\n"
        ."Subject: vos commandes en ligne\r\n"
        ."Content-Type: text/plain; charset=utf-8\r\n"
        ."\r\n"
        ."Bonjour, ceci est un email de test.\r\n";

    $file = UploadedFile::fake()->createWithContent('example.eml', $raw);

    $this->actingAs(superAdmin())
        ->post(route('app-settings.email-examples.store'), ['file' => $file])
        ->assertSessionHasNoErrors();

    $example = EmailExample::query()->sole();

    expect($example->subject)->toBe('vos commandes en ligne')
        ->and($example->body)->toContain('ceci est un email de test');
});

it('refuses a file that is not a .eml', function () {
    $file = UploadedFile::fake()->create('example.txt', 10);

    $this->actingAs(superAdmin())
        ->post(route('app-settings.email-examples.store'), ['file' => $file])
        ->assertSessionHasErrors('file');

    expect(EmailExample::query()->count())->toBe(0);
});

it('refuses neither a pasted example nor a file', function () {
    $this->actingAs(superAdmin())
        ->post(route('app-settings.email-examples.store'), [])
        ->assertSessionHasErrors(['subject', 'file']);
});

it('deletes an example', function () {
    $example = EmailExample::factory()->create();

    $this->actingAs(superAdmin())
        ->delete(route('app-settings.email-examples.destroy', $example))
        ->assertSessionHasNoErrors();

    expect(EmailExample::query()->count())->toBe(0);
});

it('saves the promotion thresholds', function () {
    $this->actingAs(superAdmin())
        ->put(route('app-settings.email-examples.thresholds'), [
            'min_sends' => 30,
            'min_positive_rate' => 0.15,
            'max_unsubscribe_rate' => 0.03,
        ])
        ->assertSessionHasNoErrors();

    expect(app(Settings::class)->int('email_examples.min_sends'))->toBe(30)
        ->and(app(Settings::class)->float('email_examples.min_positive_rate'))->toBe(0.15)
        ->and(app(Settings::class)->float('email_examples.max_unsubscribe_rate'))->toBe(0.03);
});
