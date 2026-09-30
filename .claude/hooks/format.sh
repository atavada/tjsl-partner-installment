#!/usr/bin/env bash
set -e

# Run pint on modified PHP files if pint exists
if [ -f "./vendor/bin/pint" ]; then
    ./vendor/bin/pint --dirty --quiet || true
fi

# Run prettier on modified frontend files if installed
if [ -f "./node_modules/.bin/prettier" ]; then
    npx prettier --write "resources/**/*.{ts,tsx,js,jsx,css}" --ignore-unknown --log-level warn || true
fi
