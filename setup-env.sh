#!/usr/bin/env bash
set -euo pipefail

# Create a safe local .env from the committed template.
# This script never stores credentials; fill secrets manually or via your secret manager.

if [[ "${1:-}" == "--help" ]]; then
    echo "Usage: ./setup-env.sh"
    echo "Creates .env from .env.example only when .env does not already exist."
    exit 0
fi

if [[ -f .env ]]; then
    echo ".env already exists. Refusing to overwrite it." >&2
    exit 1
fi

cp .env.example .env
echo "Created .env from .env.example."

if command -v php >/dev/null 2>&1; then
    php artisan key:generate
else
    echo "PHP was not found. Generate APP_KEY with: php artisan key:generate" >&2
fi

echo
echo "Next steps:"
echo "1. Fill database, AI provider, Firebase, and payment credentials in .env."
echo "2. Keep GUMROAD_ACCESS_TOKEN and GUMROAD_WEBHOOK_SECRET out of source control."
echo "3. Run: php artisan migrate --force"
echo "4. Run: npm ci && npm run build"
