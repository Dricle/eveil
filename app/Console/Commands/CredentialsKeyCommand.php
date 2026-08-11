<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Generates CREDENTIALS_KEY, the key that encrypts user secrets — deliberately
 * separate from APP_KEY (ADR-012).
 *
 * Rotating it makes every stored SMTP/IMAP password unreadable, so the command
 * refuses to overwrite an existing key unless forced.
 */
class CredentialsKeyCommand extends Command
{
    protected $signature = 'eveil:credentials-key {--show : Print the key instead of writing it}
                                                  {--force : Overwrite an existing key}';

    protected $description = 'Set the credentials encryption key';

    public function handle(): int
    {
        $key = 'base64:'.base64_encode(Str::random(32));

        if ($this->option('show')) {
            $this->line($key);

            return self::SUCCESS;
        }

        $path = app()->environmentFilePath();
        $contents = file_exists($path) ? (string) file_get_contents($path) : '';
        $current = preg_match('/^CREDENTIALS_KEY=(.*)$/m', $contents, $matches) ? trim($matches[1]) : '';

        if ($current !== '' && ! $this->option('force')) {
            $this->components->warn('CREDENTIALS_KEY is already set. Replacing it makes every stored credential unreadable — pass --force if that is really what you want.');

            return self::SUCCESS;
        }

        if ($current !== '') {
            $this->components->warn('Overwriting CREDENTIALS_KEY. Every stored SMTP/IMAP password and provider key must be re-entered.');
        }

        $contents = preg_match('/^CREDENTIALS_KEY=/m', $contents)
            ? (string) preg_replace('/^CREDENTIALS_KEY=.*$/m', 'CREDENTIALS_KEY='.$key, $contents)
            : rtrim($contents, "\n")."\nCREDENTIALS_KEY=".$key."\n";

        file_put_contents($path, $contents);

        $this->components->info('Credentials key set successfully.');

        return self::SUCCESS;
    }
}
