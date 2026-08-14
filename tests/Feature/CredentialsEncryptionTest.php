<?php

use App\Models\EmailAccount;
use App\Support\CredentialsCipher;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\DB;

/**
 * User secrets ride on CREDENTIALS_KEY, never APP_KEY, and a canary
 * makes a key mismatch fail loudly instead of silently returning garbage.
 */
it('never stores a mailbox password in plaintext', function () {
    $account = EmailAccount::factory()->create(['smtp_password' => 'hunter2']);

    $stored = DB::table('email_accounts')->where('id', $account->id)->value('smtp_password');

    expect($stored)->not->toBe('hunter2')
        ->and($account->fresh()->smtp_password)->toBe('hunter2');
});

it('keeps passwords out of serialised output', function () {
    $account = EmailAccount::factory()->create(['smtp_password' => 'hunter2']);

    expect($account->toArray())->not->toHaveKeys(['smtp_password', 'imap_password']);
});

it('does not encrypt credentials with APP_KEY', function () {
    $account = EmailAccount::factory()->create(['smtp_password' => 'hunter2']);
    $stored = (string) DB::table('email_accounts')->where('id', $account->id)->value('smtp_password');

    // Decrypting with the application key must fail: if this ever passes, the
    // two keys have been collapsed into one and rotating APP_KEY would destroy
    // every mailbox on the instance.
    expect(fn () => decrypt($stored))->toThrow(DecryptException::class);
});

it('refuses to use a credentials key that does not match the canary', function () {
    app(CredentialsCipher::class)->writeCanary();

    config()->set('app.credentials_key', 'base64:'.base64_encode(random_bytes(32)));

    expect(fn () => (new CredentialsCipher)->verifyCanary())
        ->toThrow(RuntimeException::class, 'does not match the stored canary');
});

it('accepts a fresh install with no canary yet', function () {
    expect(fn () => (new CredentialsCipher)->verifyCanary())->not->toThrow(RuntimeException::class);
});

it('fails loudly when no credentials key is configured', function () {
    config()->set('app.credentials_key', null);

    expect(fn () => (new CredentialsCipher)->encrypt('secret'))
        ->toThrow(RuntimeException::class, 'CREDENTIALS_KEY is not set');
});
