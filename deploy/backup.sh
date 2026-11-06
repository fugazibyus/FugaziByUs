#!/usr/bin/env bash
# Nightly database dump, kept for 7 days in /opt/fugazi/backups. Run by cron (see
# scripts/droplet-init.sh). These copies live on the same Droplet, so they protect against
# mistakes, not against losing the server: turn on DigitalOcean Droplet backups for that.
#
# Restore: gunzip -c backups/db-<date>.sql.gz | docker compose -f compose.yml exec -T db \
#            sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u"$MYSQL_USER" "$MYSQL_DATABASE"'
set -euo pipefail
cd "$(dirname "$0")"
mkdir -p backups

file="backups/db-$(date -u +%Y-%m-%d).sql.gz"

docker compose --file compose.yml exec -T db sh -c \
  'MYSQL_PWD="$MYSQL_PASSWORD" mysqldump --single-transaction --no-tablespaces -u"$MYSQL_USER" "$MYSQL_DATABASE"' |
  gzip >"${file}.tmp"
mv "${file}.tmp" "$file"
chmod 600 "$file"

find backups -name 'db-*.sql.gz' -mtime +7 -delete
echo "$(date -u +%FT%TZ) saved ${file} ($(du -h "$file" | cut -f1))"
