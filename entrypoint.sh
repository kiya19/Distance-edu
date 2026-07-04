#!/bin/sh
set -e

# Render (and most PaaS providers) tell your app which port to bind to via
# the $PORT env var. Apache hardcodes port 80 by default, so we patch it
# at container start.
PORT="${PORT:-10000}"

sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/\*:80/*:${PORT}/" /etc/apache2/sites-available/000-default.conf

# First run on a fresh persistent disk: seed it with the sample files baked
# into the image, so the demo data's sample downloads work immediately.
if [ -n "$UPLOAD_PATH" ] && [ ! -d "$UPLOAD_PATH" ]; then
    mkdir -p "$UPLOAD_PATH"
    cp -r /var/www/html/uploads/. "$UPLOAD_PATH/" 2>/dev/null || true
    chown -R www-data:www-data "$UPLOAD_PATH"
fi

exec "$@"
