#!/usr/bin/env bash
#
# Bring up the development stack and drop into the workspace shell.
# From there: composer install, php artisan migrate, pnpm ..., etc.
#
#   app     http://localhost:${APP_PORT:-8000}
#   vite    http://localhost:${VITE_PORT:-5173}

set -euo pipefail

cd "$(dirname "$0")"

# `env` rather than `export`, because UID is readonly in bash.
run() {
    env UID="$(id -u)" GID="$(id -g)" docker compose -f compose.dev.yml "$@"
}

run up -d --build --remove-orphans
run exec workspace bash
