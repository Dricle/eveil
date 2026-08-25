#!/bin/sh
# Runs before anything else in the container, whatever it was asked to run.
#
# Three jobs, in this order: make sure the encryption keys exist, migrate if this
# is the web boot, and get out of the way for one-off commands.
set -e

# ── Keys ─────────────────────────────────────────────────────────────────────
#
# Generated on first boot rather than asked for, and kept in the storage volume
# so they are the SAME on every boot after that. This is the part that has to be
# right: `CREDENTIALS_KEY` is what every stored mailbox password is encrypted
# with, so a key regenerated on restart would not log a warning. It would make
# every connected mailbox permanently unreadable.
#
# Anything already in the environment wins, so an operator who put the keys in
# `.env` keeps managing them there and this does nothing.
KEYS=/var/www/storage/app/.keys.env

if [ -z "$APP_KEY" ] || [ -z "$CREDENTIALS_KEY" ]; then
    if [ ! -f "$KEYS" ]; then
        mkdir -p "$(dirname "$KEYS")"

        # `random_bytes` rather than the openssl binary: PHP is the one thing
        # guaranteed to be in this image.
        cat > "$KEYS" <<EOF
APP_KEY=$(php -r 'echo "base64:".base64_encode(random_bytes(32));')
CREDENTIALS_KEY=$(php -r 'echo "base64:".base64_encode(random_bytes(32));')
EOF
        chmod 600 "$KEYS"
        chown www-data:www-data "$KEYS"

        echo '────────────────────────────────────────────────────────────────'
        echo ' Generated the encryption keys, in the storage volume:'
        echo "   $KEYS"
        echo
        echo ' Back that volume up together with the database. CREDENTIALS_KEY'
        echo ' encrypts every mailbox password: losing it means reconnecting'
        echo ' every mailbox by hand, and a database dump without it is worthless.'
        echo '────────────────────────────────────────────────────────────────'
    fi

    # Read one at a time rather than sourcing the file: sourcing would overwrite
    # a key the operator set themselves, and somebody managing only APP_KEY in
    # `.env` is a perfectly reasonable half-way house.
    if [ -z "$APP_KEY" ]; then
        APP_KEY=$(sed -n 's/^APP_KEY=//p' "$KEYS")
        export APP_KEY
    fi

    if [ -z "$CREDENTIALS_KEY" ]; then
        CREDENTIALS_KEY=$(sed -n 's/^CREDENTIALS_KEY=//p' "$KEYS")
        export CREDENTIALS_KEY
    fi
fi

case "$1" in
    /usr/bin/supervisord)
        php artisan migrate --force --isolated

        # `App\Support\Settings` caches the whole `settings` table forever
        # (Redis, `rememberForever`), because it is read on every agent call
        # and changes maybe twice a year. A migration that changes what an
        # EXISTING key holds — adds a field to `discovery`, say — writes
        # straight to the table and can't safely flush this itself: Redis is
        # not guaranteed reachable while migrations run. So it has to happen
        # here instead, once Redis is up (the app service depends on its
        # healthcheck), or every boot after such a migration keeps serving a
        # snapshot that predates it until something else evicts the key.
        php artisan cache:forget eveil.settings || true

        # The head start every install gets: known directories, the
        # disposable-domain blocklist, mail providers that refuse probes.
        # `updateOrCreate`/transactional-replace under the hood, so running it
        # again on every boot is a no-op, not a duplicate.
        php artisan db:seed --class='Database\Seeders\InstallSeeder' --force

        # Allowed to fail without taking the boot down: a rejected
        # ADMIN_PASSWORD is a typo in an env file, and the setup screen is still
        # a perfectly good way in. Refusing to serve would turn one bad variable
        # into an instance nobody can reach to fix it.
        php artisan eveil:install || echo 'Could not create the admin from the environment; use the setup screen instead.'

        php artisan config:cache
        php artisan route:cache
        php artisan event:cache
        ;;
esac

exec "$@"
