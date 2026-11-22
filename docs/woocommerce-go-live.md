# WooCommerce Go-Live Hardening

## Goal

Make EuroCross AI reliable enough to monetize on WooCommerce stores with clear billing, access control, and translation behavior.

## Supported setup (recommended for selling first)

- WordPress 6.x + WooCommerce 8.x/9.x on PHP 8.1+.
- Classic or block theme with standard WooCommerce templates/blocks.
- Checkout: WooCommerce classic checkout or Checkout Block (both must be QA'd).
- Languages: plan/trial-entitled languages only; trial gives 10 languages.
- EuroCross plugin connected to an active SaaS account (trial or paid plan).
- Server has WP cron or real cron running; cache/queue healthy on SaaS.

## Monetization / access control checklist

- [ ] User must sign in before web checkout (`/billing/checkout/{plan}` account-tied).
- [ ] Active subscription cannot start a duplicate checkout for same plan; plan change requires cancel/current period end.
- [ ] Trial cannot restart after trial already used.
- [ ] Gumroad webhook conflict with active subscription is flagged as `conflicting_active_subscription` and stored for manual review (no silent replacement).
- [ ] Unpaid/inactive shop cannot use protected API endpoints (`/eurocross/translate`, chatbot, etc.); exempt paths only: settings/trial start/checkout/api-keys.
- [ ] Manual API key generation in portal requires active paid subscription; trial key is issued through WP Connect flow.
- [ ] Dashboard Billing shows webhook status, manual payments, and conflict instructions.
- [ ] WP admin shows clear notice when quota/access/API blocks translations.

## Onboarding checklist (customer flow)

1. Install/activate EuroCross AI plugin on WooCommerce site.
2. Connect site to EuroCross account (WP Connect). Confirm API key saved and connection test passes.
3. Start 7-day trial or buy plan (Starter/Growth/Scale) while signed in.
4. Confirm `/eurocross/languages` returns expected languages for plan/trial.
5. Visit main WooCommerce pages as logged-out user: home, shop, product, cart, checkout, my account.
6. Switch language via switcher; confirm URL gets `?eurocross_lang=<code>` and content translates.
7. Confirm mini-cart/cart/checkout updates after quantity/coupon/shipping changes.
8. Confirm chatbot loads only when enabled and account has access/quota.

## WooCommerce QA matrix (minimum before selling widely)

Run each scenario on at least 3 representative setups:

| Setup | Theme/Checkout | Must pass |
|---|---|---|
| A | Storefront + classic cart/checkout | Language switch, product/cart/checkout/my-account, mini-cart fragments, emails/order strings visible in UI |
| B | Block theme (Twenty Twenty-Four/Twenty Twenty-Five) + Checkout Block | Header/footer/nav, product blocks, cart/checkout blocks, Store API dynamic updates |
| C | Common Woo theme/builder setup used by your target customers | Same core flow + any theme-specific nav/widget areas |

## Per-site smoke test

- [ ] Home/shop/product/category/cart/checkout/my-account load in default language.
- [ ] Switch to at least 2 non-English languages and 1 RTL if offered (Arabic).
- [ ] Product title, price labels, add-to-cart, cart totals, checkout fields/buttons, notices translate.
- [ ] Mini-cart updates after add/remove item.
- [ ] Checkout updates after shipping/coupon changes.
- [ ] No broken HTML/JS console errors from EuroCross.
- [ ] After quota/access block, storefront falls back safely (original text/cached text), WP admin shows clear issue.

## Known risks / mitigations

- **Custom JS/inline scripts:** best-effort only; do not promise full rewrite. Mitigation: server-side fragments/Store API, wcPatches, client re-scan; document unsupported custom scripts.
- **Third-party plugins/widgets:** may render outside covered hooks/selectors. Mitigation: test target plugins, add targeted selectors/hooks as needed.
- **Quota/API outage:** translations may pause for uncached strings. Mitigation: cache translated content, clear admin warning, safe fallback to original text.
- **Direct Gumroad payment outside app flow:** cannot be fully prevented at Gumroad. Mitigation: conflict flagged as manual review, no silent double activation; ops must review/refund in Gumroad.
- **Date localization 100%:** depends on WP locale packs/timestamp context. Mitigation: deterministic month/day fallback + wp_date/date_i18n handling + AI fallback.
- **Public AI abuse/cost:** keep public AI requests disabled unless intentionally public; rate limits and plan quotas apply on SaaS.

## Ops runbook

- Check dashboard Billing for webhook conflicts/manual payments.
- Use `php artisan gumroad:manual-payments` (if available in deployment) for pending review items.
- For conflict: confirm active Gumroad subscription, refund accidental duplicate, do not manually force-activate conflicting subscription without audit.
- Before selling to a new niche/theme stack, run the 3-setup QA matrix and record results.
