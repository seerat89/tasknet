#!/bin/sh
# Container entrypoint for Vercel: the web server, plus Reverb beside it when broadcasting is on.
#
# Reverb listens on loopback only. Caddy proxies browser WebSocket traffic (/app/*) to it and Laravel
# publishes events to it directly, so it is never exposed on its own port. If Reverb stops, it is
# restarted; while it is down, realtime is unavailable but the web app keeps working.

if [ "$BROADCAST_CONNECTION" = "reverb" ]; then
    (
        while true; do
            php artisan reverb:start --no-interaction \
                --host="${REVERB_SERVER_HOST:-127.0.0.1}" --port="${REVERB_SERVER_PORT:-8080}"
            sleep 2
        done
    ) &
fi

exec frankenphp run --config /etc/frankenphp/Caddyfile
