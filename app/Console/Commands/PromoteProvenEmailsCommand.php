<?php

namespace App\Console\Commands;

use App\Actions\PromoteProvenEmails;
use Illuminate\Console\Command;

/**
 * A variant that falls short of the volume floor today may clear it next
 * month, so this re-checks every campaign step daily rather than judging
 * each one once. Already-promoted variants are skipped cheaply — see
 * `PromoteProvenEmails`.
 */
class PromoteProvenEmailsCommand extends Command
{
    protected $signature = 'eveil:promote-proven-emails';

    protected $description = 'Add any campaign step that has earned it to the shared examples bank';

    public function handle(PromoteProvenEmails $promote): int
    {
        $promoted = $promote->handle();

        $this->info($promoted === 0
            ? 'Nothing new proved itself.'
            : "Promoted {$promoted} email(s) to the examples bank.");

        return self::SUCCESS;
    }
}
