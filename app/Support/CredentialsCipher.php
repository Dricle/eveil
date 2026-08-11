<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * User secrets — SMTP/IMAP passwords, the AI provider key, future OAuth tokens
 * — are encrypted with a dedicated `CREDENTIALS_KEY`, never with `APP_KEY`
 * (ADR-012).
 *
 * APP_KEY also encrypts cookies and sessions and should be rotated after a
 * leak. Coupled to credentials, rotating it would destroy every email account
 * on the instance, so in practice nobody ever would.
 *
 * The canary is checked once per process, lazily, the first time a credential
 * is actually touched. That is deliberately narrower than a boot-time check:
 * it costs nothing on requests that read no secrets, and it still fails loudly
 * at the exact moment a secret would otherwise be silently misread.
 */
class CredentialsCipher
{
    private const CANARY_KEY = 'credentials.canary';

    private const CANARY_PLAINTEXT = 'eveil-credentials-canary';

    private ?Encrypter $encrypter = null;

    private bool $canaryVerified = false;

    public function encrypt(string $value): string
    {
        $this->verifyCanary();

        return $this->encrypter()->encryptString($value);
    }

    public function decrypt(string $payload): string
    {
        $this->verifyCanary();

        return $this->encrypter()->decryptString($payload);
    }

    /**
     * Writes the canary row. Called at setup, and again after a key rotation
     * once every credential has been re-encrypted.
     */
    public function writeCanary(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => self::CANARY_KEY],
            [
                'value' => $this->encrypter()->encryptString(self::CANARY_PLAINTEXT),
                'is_encrypted' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $this->canaryVerified = true;
    }

    /**
     * @throws RuntimeException when the stored canary no longer decrypts, which
     *                          means CREDENTIALS_KEY changed and every stored
     *                          secret is unreadable.
     */
    public function verifyCanary(): void
    {
        if ($this->canaryVerified) {
            return;
        }

        $stored = DB::table('settings')->where('key', self::CANARY_KEY)->value('value');

        // No canary yet: a fresh install that has not run setup. Nothing to
        // contradict, so let the first write establish it.
        if ($stored === null) {
            $this->canaryVerified = true;

            return;
        }

        try {
            $decrypted = $this->encrypter()->decryptString($stored);
        } catch (DecryptException) {
            throw new RuntimeException(
                'CREDENTIALS_KEY does not match the stored canary: every saved SMTP/IMAP password '
                .'and provider key is unreadable. Restore the previous key, or re-enter the '
                .'credentials and run the canary rewrite. A database dump without its matching '
                .'.env is worthless — back up both together.',
            );
        }

        if ($decrypted !== self::CANARY_PLAINTEXT) {
            throw new RuntimeException('The stored credentials canary is corrupt.');
        }

        $this->canaryVerified = true;
    }

    private function encrypter(): Encrypter
    {
        return $this->encrypter ??= new Encrypter($this->key(), config('app.cipher'));
    }

    private function key(): string
    {
        $key = config('app.credentials_key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException(
                'CREDENTIALS_KEY is not set. It encrypts user secrets and is deliberately separate '
                .'from APP_KEY (ADR-012). Generate one with: php artisan eveil:credentials-key',
            );
        }

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), strict: true) ?: '';
        }

        return $key;
    }
}
