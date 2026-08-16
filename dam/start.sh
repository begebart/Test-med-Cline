#!/usr/bin/env bash
# Start en lokal PHP-server for Dam-spillet.
# Kræver PHP 8.0+ installeret (php-cli).
set -e
cd "$(dirname "$0")"
PORT="${1:-8000}"
mkdir -p data
echo "Dam-spil kører på http://localhost:${PORT}"
echo "Åbn ovenstående URL i to browsere/faner for at spille."
echo "Tryk Ctrl-C for at stoppe."
exec php -S "127.0.0.1:${PORT}" -t .
