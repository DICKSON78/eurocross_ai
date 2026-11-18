<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## EuroCross — Gumroad Billing Setup

### Environment variables

```
PAYMENT_PROVIDER=gumroad
APP_URL=https://eurocross-ai.kadetech.online
GUMROAD_ACCESS_TOKEN=
GUMROAD_WEBHOOK_SECRET=
GUMROAD_SELLER_ID="45z_yh2gaNKAuqiazg0qsQ=="
GUMROAD_PRODUCT_STARTER=eurocross-starter
GUMROAD_PRODUCT_GROWTH=eurocross-growth
GUMROAD_PRODUCT_SCALE=eurocross-scale
GUMROAD_PRODUCT_ID_STARTER="GWkd8qsP7GxksJcVdr_oMw=="
GUMROAD_PRODUCT_ID_GROWTH="03Ex_UcTD52UiV3lhwHZhA=="
GUMROAD_PRODUCT_ID_SCALE="fM75G5JEZvgvN1MpprCdcA=="
GUMROAD_CHECKOUT_STARTER=https://augustinodickson.gumroad.com/l/eurocross-starter
GUMROAD_CHECKOUT_GROWTH=https://augustinodickson.gumroad.com/l/eurocross-growth
GUMROAD_CHECKOUT_SCALE=https://augustinodickson.gumroad.com/l/eurocross-scale
GUMROAD_TRIAL_DAYS=7
```

Quote any value ending in `==` in `.env`.

### Checkout

The `/pricing` page embeds Gumroad's overlay by loading `https://gumroad.com/js/gumroad.js` exactly once. Subscribe links come from `GUMROAD_CHECKOUT_*`. Logged-in users get `?email=...&wanted=true` appended, but payment matching also works when a different email is typed (see manual review below).

Short marketing links: `/starter`, `/growth`, `/scale` redirect to the configured checkout URLs.

### Enable Ping + signed pings

In Gumroad: **Settings → Advanced → Ping**:
- Ping URL: `https://eurocross-ai.kadetech.online/eurocross/webhook/gumroad`
- Enable **Sign pings**.

The webhook verifies the `X-Gumroad-Signature` header as an HMAC-SHA256 digest of the raw body using `GUMROAD_WEBHOOK_SECRET`. A secret in the query string is never accepted. It also rejects requests whose `seller_id` does not match.

> Note: Gumroad's public "Verifying pings" page could not be fetched when this was implemented. The header/algorithm/encoding follow the commonly documented `X-Gumroad-Signature` + HMAC-SHA256 hex convention and are flagged as unverified. The verifier accepts hex (with or without `sha256=` prefix) and base64 forms defensively.

### Extra subscription events

Run once (idempotent):

```
php artisan gumroad:register-webhooks
```

This calls `POST https://api.gumroad.com/v2/resource_subscriptions` with `access_token`, `resource_name`, `post_url` for cancellation, subscription_ended, subscription_restarted, subscription_updated, refund, dispute, dispute_won. The current Gumroad API docs could not be read during implementation; the event names and parameters are taken from the request and should be verified in your Gumroad dashboard.

### Queue worker (Supervisor)

Webhook work is queued. Run under Supervisor:

```
[program:eurocross-worker]
command=php /home/kadepljk/eurocross-ai.kadetech.online/artisan queue:work --tries=3 --stop-when-empty
autostart=true
autorestart=true
user=kadepljk
redirect_stderr=true
stdout_logfile=/home/kadepljk/eurocross-ai.kadetech.online/storage/logs/worker.log
```

Then `supervisorctl reread && supervisorctl update && supervisorctl start eurocross-worker`.

### Manual review

If a buyer's email does not match a user, the payment is stored in `eurocross_manual_payments` — no payment is lost.

```
php artisan gumroad:manual-payments
php artisan gumroad:link-payment {event_id} {email}
```

### After-purchase page

Set Gumroad's product redirect (if available) to:

`https://eurocross-ai.kadetech.online/billing/thank-you`

The page polls `/billing/status` and reloads once the plan is active.

### Testing

Use a 100% discount code in Gumroad to test checkout end-to-end without charging. Then run:

```
php artisan migrate
php artisan config:clear
php artisan test --compact
php artisan gumroad:register-webhooks
php artisan queue:work
```

### Documentation caveat

Gumroad's ping-verification and resource-subscription API docs could not be fetched from this environment. The webhook verifier and webhook-registration command are implemented from the provided specification plus the commonly used HMAC convention; verify them against Gumroad's current docs before relying on them in production.

## Persisted translation layer (P1)

Persistent per-language translations are stored in `eurocross_translations`, with queued work tracked in `eurocross_translation_jobs`. The tables are additive: existing synchronous `/eurocross/translate` and `/eurocross/translate/batch` endpoints are unchanged.

Authenticated API routes:

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/eurocross/translations` | List translations with optional `status`, `target_lang`, `source_type`, and `per_page` filters. |
| `POST` | `/eurocross/translations` | Queue a single persisted translation. |
| `POST` | `/eurocross/translations/bulk` | Queue up to 100 translations under one `batch_id`. |
| `GET` | `/eurocross/translations/{translation}` | Inspect one translation and its latest job status. |
| `PUT` | `/eurocross/translations/{translation}` | Save a manual translation; this is never overwritten by a queued job. |
| `POST` | `/eurocross/translations/{translation}/retry` | Retry a failed or completed translation. |

Deduplication uses a unique key of shop, source type, source ID, field, source locale, and target locale. Jobs are unique per translation, use three attempts with backoff, and mark both the translation and job as `failed` when all attempts are exhausted. Manual edits set `manually_edited = true` and short-circuit future provider calls.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
