<?php

namespace App\Console\Commands\Concerns;

/**
 * Typed reads of string arguments and options. Larastan cannot see commands
 * the kernel discovers (larastan/larastan#2260), so `argument()` and
 * `option()` come back as Symfony's `mixed`-like union and PHPStan 2.3
 * refuses to cast that to string.
 */
trait ReadsStringInput
{
    /**
     * The argument when given and non-empty, null otherwise.
     */
    protected function stringArgument(string $name): ?string
    {
        $value = $this->argument($name);

        return $this->asString($value);
    }

    /**
     * The option when given and non-empty, null otherwise.
     */
    protected function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return $this->asString($value);
    }

    /**
     * Scalars too: a test or `$this->call()` passes an id as an int.
     */
    private function asString(mixed $value): ?string
    {
        return is_scalar($value) && ! is_bool($value) && $value !== '' ? (string) $value : null;
    }
}
