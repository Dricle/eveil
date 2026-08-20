<?php

namespace App\Console\Commands;

use App\Actions\CreateAccount;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * The first account, from the environment, so a container can come up ready to
 * log into.
 *
 * Idempotent by design: this runs on every boot from the entrypoint, and a
 * restart must never reset somebody's password or create a second super admin.
 * Once a user exists it does nothing at all: the setup screen and this command
 * are two doors into the same room, and whoever got there first wins.
 *
 * Deliberately not a fallback to a default password. An instance reachable on
 * the internet with a known admin password is worse than one that cannot be
 * logged into at all: without `ADMIN_PASSWORD` the setup screen is the way in.
 */
class InstallCommand extends Command
{
    protected $signature = 'eveil:install';

    protected $description = 'Create the first super admin from ADMIN_EMAIL and ADMIN_PASSWORD, if there is no account yet';

    public function handle(CreateAccount $createAccount): int
    {
        if (User::query()->exists()) {
            $this->components->info('An account already exists; leaving it alone.');

            return self::SUCCESS;
        }

        $email = config('eveil.admin.email');
        $password = config('eveil.admin.password');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            $this->components->info('No ADMIN_EMAIL and ADMIN_PASSWORD set; the setup screen will ask instead.');

            return self::SUCCESS;
        }

        $data = [
            'name' => (string) (config('eveil.admin.name') ?: 'Administrator'),
            'email' => $email,
            'password' => $password,
            'organization' => (string) (config('eveil.admin.organization') ?: 'My organization'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            // The same rules the setup screen enforces: an eight-character
            // minimum is not much, but a container booting with "admin" as the
            // password of a super admin is a different kind of problem.
            'password' => ['required', 'string', Password::defaults()],
            'organization' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $createAccount->handle($data, isSuperAdmin: true);

        $this->components->info("Created the super admin {$email}.");

        return self::SUCCESS;
    }
}
