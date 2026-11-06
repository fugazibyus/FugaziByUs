#!/bin/sh
# The certbot container renews certificates in place; reload nginx every 6 hours so it
# picks up a renewed one without a restart.
(while sleep 6h; do nginx -s reload; done) &
