#!/usr/bin/env bash
# Installs dependencies, prepares the database and runs every check.
# Stops at the first step that fails.
set -euo pipefail

cd "$(dirname "$0")"

step() { printf '\n==> %s\n' "$1"; }

step "Install PHP dependencies"
composer install --no-interaction --prefer-dist

step "Install JavaScript dependencies"
npm install

step "Prepare the environment file"
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --ansi
fi

step "Rebuild the database with sample data"
touch database/database.sqlite
php artisan migrate:fresh --seed --force

step "Run the tests"
php artisan test

step "Check the code style"
vendor/bin/pint --test

step "Check the TypeScript types"
npm run typecheck

step "Lint the frontend code"
npm run lint

step "Check the frontend formatting"
npm run format:check

step "Build the frontend"
npm run build

printf '\nAll checks passed.\n'
