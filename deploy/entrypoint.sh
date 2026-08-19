#!/bin/sh
# Every service in the stack runs this, and only the web one does the migrating.
#
# The order matters: migrate before anything serves or consumes a queue, so a
# worker never picks up a job whose table does not exist yet. `eveil:install` is
# idempotent and does nothing once an account exists, which is what makes it safe
# on every restart.
set -e

if [ "$1" = "frankenphp" ]; then
    php artisan migrate --force --isolated

    # Deliberately allowed to fail without taking the boot down. A rejected
    # ADMIN_PASSWORD is a typo in an env file, and the setup screen is still a
    # perfectly good way in — refusing to serve at all would turn one bad
    # variable into an instance nobody can reach to fix it.
    php artisan eveil:install || echo 'Could not create the admin from the environment; use the setup screen.'

    php artisan config:cache
    php artisan route:cache
    php artisan event:cache
else
    # Workers wait for the web service to finish migrating rather than racing it.
    until php artisan migrate:status >/dev/null 2>&1; do
        echo "Waiting for the database to be migrated…"
        sleep 2
    done
fi

exec "$@"
