<?php

namespace App\Services\Ads;

use RuntimeException;

/**
 * A network refused. `$revoked` when the refusal is about access itself (a
 * revoked grant, a removed app): retrying cannot help, the account has to be
 * connected again.
 */
class AdPlatformException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $revoked = false)
    {
        parent::__construct($message);
    }

    public static function revoked(string $message): self
    {
        return new self($message, revoked: true);
    }
}
