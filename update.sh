#!/bin/bash

# Exit immediately if a command exits with a non-zero status.
set -e

echo "Updating to the latest version..."

# Stash any local changes
echo "Stashing local changes, if any..."
git stash

# Pull latest changes from git repository
echo "Pulling latest code..."
git pull

# Stop running containers
echo "Stopping current services..."
docker compose down

# Check if .env file exists
if [ ! -f .env ]; then
  echo "Error: .env file not found. Please follow the installation instructions first."
  exit 1
fi

# Rebuild and start containers
echo "Rebuilding and starting updated services..."
docker compose -f compose.deploy.yaml up -d --build

echo "Update complete. Services are running with the latest version."
echo "You can monitor logs with: docker compose logs -f"
