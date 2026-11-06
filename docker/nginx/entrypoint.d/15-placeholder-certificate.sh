#!/bin/sh
# nginx won't start without a certificate, and Let's Encrypt can't issue one until nginx
# answers on port 80. So on a brand-new server we start with a short-lived self-signed
# certificate; deploy/deploy.sh then swaps in the real one and reloads nginx.
set -eu

: "${APP_DOMAIN:?APP_DOMAIN is not set}"
live="/etc/letsencrypt/live/${APP_DOMAIN}"

if [ ! -f "${live}/fullchain.pem" ]; then
    echo "No certificate for ${APP_DOMAIN} yet; using a temporary self-signed one."
    mkdir -p "${live}"
    openssl req -x509 -nodes -newkey rsa:2048 -days 2 \
        -keyout "${live}/privkey.pem" -out "${live}/fullchain.pem" \
        -subj "/CN=${APP_DOMAIN}" 2>/dev/null
fi
