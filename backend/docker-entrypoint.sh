#!/bin/sh
set -eu

if [ "${DB_HOST:-}" ]; then
  echo "Waiting for database at ${DB_HOST}:${DB_PORT:-5432}..."
  while ! nc -z "$DB_HOST" "${DB_PORT:-5432}"; do
    sleep 1
  done
fi

python manage.py migrate --noinput
python manage.py seed_demo

exec "$@"
