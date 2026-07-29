#!/usr/bin/env bash

set -Eeuo pipefail

APP_ROOT="${APP_ROOT:-/var/www/talentxpanse/current/backend}"
BACKUP_DIRECTORY="${BACKUP_DIRECTORY:-/var/backups/talentxpanse}"
BACKUP_RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
: "${BACKUP_GPG_RECIPIENT:?Set BACKUP_GPG_RECIPIENT to the public-key recipient used for encrypted backups.}"

if ! command -v mysqldump >/dev/null || ! command -v gpg >/dev/null; then
  echo 'mysqldump and gpg must both be installed for encrypted database backups.' >&2
  exit 1
fi

set -a
source "${APP_ROOT}/.env"
set +a

if [[ "${DB_CONNECTION:-}" != 'mysql' ]]; then
  echo 'This backup script supports MySQL only.' >&2
  exit 1
fi

umask 077
mkdir -p "${BACKUP_DIRECTORY}"

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
temporary_backup="$(mktemp "${BACKUP_DIRECTORY}/.talentxpanse-${timestamp}.sql.gz.XXXXXX")"
encrypted_backup="${BACKUP_DIRECTORY}/talentxpanse-${timestamp}.sql.gz.gpg"
trap 'rm -f "${temporary_backup}"' EXIT

MYSQL_PWD="${DB_PASSWORD:-}" mysqldump \
  --host="${DB_HOST:-127.0.0.1}" \
  --port="${DB_PORT:-3306}" \
  --user="${DB_USERNAME}" \
  --single-transaction \
  --quick \
  --routines \
  --events \
  --triggers \
  --set-gtid-purged=OFF \
  "${DB_DATABASE}" | gzip -9 > "${temporary_backup}"

gzip -t "${temporary_backup}"
gpg --batch --yes --trust-model always --encrypt --recipient "${BACKUP_GPG_RECIPIENT}" --output "${encrypted_backup}" "${temporary_backup}"
find "${BACKUP_DIRECTORY}" -maxdepth 1 -type f -name 'talentxpanse-*.sql.gz.gpg' -mtime "+${BACKUP_RETENTION_DAYS}" -delete

echo "Encrypted backup created: ${encrypted_backup}"
