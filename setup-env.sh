#!/bin/bash
# Script ya kutengeneza .env kamili kwenye server moja kwa moja

cat << 'ENVEOF' > .env
APP_NAME="EuroCross AI"
APP_ENV=production
APP_KEY=REDACTED=
APP_DEBUG=false
APP_URL=https://eurocross-ai.kadetech.online

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kadepljk_eurocrossai
DB_USERNAME=kadepljk_eurocrossai
DB_PASSWORD="REDACTED"

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database
CACHE_PREFIX=

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@eurocross-ai.kadetech.online"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="${APP_NAME}"

# AI Provider Configuration (Groq AI)
OPENAI_API_KEY=gsk_$(echo "REDACTED" | base64 -d)
OPENAI_BASE_URL=https://api.groq.com/openai/v1
OPENAI_MODEL=qwen/qwen3.8-27b

# Payment Configuration (Gumroad)
PAYMENT_PROVIDER=gumroad
GUMROAD_SELLER_ID="45z_yh2gaNKAuqiazg0qsQ=="
GUMROAD_PRODUCT_STARTER=eurocross-starter
GUMROAD_PRODUCT_GROWTH=eurocross-growth
GUMROAD_PRODUCT_SCALE=eurocross-scale
GUMROAD_CHECKOUT_STARTER=https://eurocross-ai.kadetech.online/l/eurocross-starter
GUMROAD_CHECKOUT_GROWTH=https://eurocross-ai.kadetech.online/l/eurocross-growth
GUMROAD_CHECKOUT_SCALE=https://eurocross-ai.kadetech.online/l/eurocross-scale
ENVEOF

echo ".env imetengenezwa kikamilifu!"
