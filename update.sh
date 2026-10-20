#!/bin/bash

# Pulls the current images and restarts the stack. Run it from the directory
# holding `compose.deploy.yaml` and your `.env`.
#
# It does not touch git: the install is a compose file and an image, not a
# clone. If you build from source instead, update with
# `git pull && docker compose -f compose.deploy.yaml -f compose.build.yaml up -d --build`.

# Exit immediately if a command exits with a non-zero status.
set -e

if [ ! -f .env ]; then
  echo "Error: .env file not found. Please follow the installation instructions first."
  exit 1
fi

# A release pinned in the compose file means pulling finds exactly what is
# already running: say so rather than let the script look like it did nothing.
if ! grep -q 'ghcr.io/dricle/eveil:latest' compose.deploy.yaml; then
  echo "Note: compose.deploy.yaml pins a release. Change it to the one you want, then run this again."
fi

echo "Pulling the latest images..."
docker compose -f compose.deploy.yaml pull

# Recreates only the containers whose image actually changed. Migrations run on
# boot, from the container's entrypoint, so there is no separate step here.
echo "Restarting services..."
docker compose -f compose.deploy.yaml up -d

echo "Update complete. Services are running with the latest version."
echo "You can monitor logs with: docker compose logs -f"
