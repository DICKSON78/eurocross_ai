=== EuroCross AI ===
Contributors: eurocrossai
Donate link: https://eurocross-ai.kadetech.online
Tags: translation, woocommerce, multilingual, vat, chatbot
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.2.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI translation and language switching for WooCommerce, with optional VAT and chatbot tools, powered by the EuroCross AI SaaS API.

== Description ==

EuroCross AI connects your WooCommerce store to your EuroCross AI SaaS account. Translation requests are made from the server to the Laravel API, and the results are rendered in your store. Optional VAT and chatbot modules are also available. No translation data or pricing figures are invented in the browser.

= Features =

* **AI translation** — translate product titles and descriptions through the SaaS API, with local transients that cache results and preserve the original text if the API is unavailable.
* **Persisted translations** — completed translations are stored by your SaaS account and mirrored into product meta for the active locale, so saved work is not translated or billed twice. Administrators can review, edit and retry records from **EuroCross AI > Translations**.
* **Auto-translate on save (optional)** — queue product titles, descriptions and optional SEO fields for every target language when a WooCommerce product is created or updated. Disabled by default; a content fingerprint prevents duplicate jobs.
* **Quota visibility** — the Translations screen shows real totals, per-language counts and the monthly word allowance returned by the API. Requests reserve words before work is queued and release them on failure or manual edit.
* **SEO and slugs (optional)** — translated Yoast/Rank Math titles and descriptions are served for the active locale, and translated product slugs are applied to generated product links. Both options are disabled by default.
* **Language switcher** — a widget and the `[eurocross_language_switcher]` shortcode render the languages actually returned by the API. Visitors can persist their choice in a cookie or local storage.
* **Chatbot (optional)** — the `[eurocross_chatbot]` shortcode posts messages to the REST chat endpoint, which calls the SaaS API server-side. Both the chatbot and public AI requests must be enabled.
* **VAT and OSS (optional)** — add a VAT number field to checkout, validate numbers through VIES, request VAT treatment information from the API and display reverse-charge or OSS notes. This is informational only and is not tax or legal advice.
* **Order emails** — translate order email subjects and content for recipients with a different active locale.
* **Dashboard** — the admin dashboard displays live status, language count and chatbot information fetched from the API.
* **Security controls** — public REST routes are rate limited, public AI requests are disabled by default, and only administrators can change the site-wide default language.
* **Developer friendly** — REST routes under `eurocross/v1`, translatable strings and a debug log mode with redacted API calls.

== External services ==

This plugin connects to the EuroCross AI SaaS API to provide translation, language, chatbot, VAT, and account/plan features. An active EuroCross AI account and API key are required for API-powered features. A permanent Free plan is available with monthly limits; paid plans increase limits and features. After a trial ends, accounts fall back to the Free plan instead of being locked out.

= EuroCross AI API =

* **Service URL:** `https://eurocross-ai.kadetech.online` (default; configurable by the site administrator)
* **What data is sent:** text/content submitted for translation, source/target language codes, site/domain and connection identifiers, API key/site secret for server-to-server authentication, VAT numbers and country codes when VAT checks are used, and chat messages when the chatbot is used.
* **When data is sent:** when an administrator connects/tests the site, when translations are requested (server-side or nonce-protected REST requests), when VAT validation/treatment is requested, and when chatbot requests are submitted.
* **Why data is sent:** to authenticate the connected site, enforce plan/quota/access, return translations, validate VAT/treatment information, and return chatbot responses.
* **Terms and privacy:** [Terms of Service](https://eurocross-ai.kadetech.online/terms) | [Privacy Policy](https://eurocross-ai.kadetech.online/privacy)

= Privacy =

The plugin stores configured settings, cached translations, cached language lists, connection status, and last API issue status in your WordPress database. Completed product translations may also be stored in post meta so they can be rendered without another API call. API keys/secrets are stored server-side in WordPress options and are not exposed to public frontend JavaScript. Plugin data is removed by the uninstall routine. Data sent to the external EuroCross AI service is subject to your EuroCross AI service terms and privacy policy.

== Installation ==

1. Upload the `eurocross-ai` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress Plugins screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to **EuroCross AI > Settings**.
4. Click **Connect with EuroCross**, sign in to your SaaS account and approve the site. The API key, plan and languages are returned to WordPress server-to-server.
5. Choose the default language, target languages and the WooCommerce translation and VAT options. The language dropdown only lists languages included in your purchased plan.
6. Click **Save Changes**, then use **Test connection** to verify the connection. Plan and language changes are pushed to the site after each subscription change.

== Frequently Asked Questions ==

= Do I need a WooCommerce store? =

No. The language switcher, REST endpoints and chatbot work without WooCommerce. WooCommerce-specific hooks are only registered when WooCommerce is active.

= Where do I get an API key? =

Use **Connect with EuroCross** in the plugin settings. After you approve the site in the SaaS dashboard, the key is exchanged server-to-server and stored in WordPress options. It is sent only in server-side requests as the `X-API-Key` header.

= Can I use the plugin without an API key? =

No. All translation, VAT and chatbot calls require a valid key. The settings page and admin notice will remind you when it is missing.

= How are languages listed in the switcher? =

The switcher calls the SaaS languages endpoint and caches the plan-limited response in a transient. Only languages included in the connected subscription are offered. If the API cannot be reached, the switcher shows an unavailable message rather than a hardcoded language list.

= How do I display the chatbot? =

Enable the chatbot option and the **Allow public AI requests** option, then add `[eurocross_chatbot]` to a page. The widget posts to the `eurocross/v1/chat` REST route with the WordPress REST nonce. Administrators can use the endpoint without enabling public AI requests.

= What happens during checkout? =

When a customer enters a VAT number and the optional VAT module is enabled, the plugin validates it through the VIES endpoint and requests VAT treatment information from the API. The returned rate, reason and reverse-charge status are displayed in the order review. Confirm the final tax treatment with your tax adviser; the plugin does not provide legal or tax compliance guarantees.

= How does auto-translation on product save work? =

Enable it in **EuroCross AI > Settings > Translation** and select at least one target language. Saving a WooCommerce product schedules a background job that queues one translation per field and target language through the SaaS API. Only changed content is queued, and the job never blocks the save request. The words shown on the Translations screen come from your SaaS plan and usage records.

= Are translated slugs resolved automatically? =

Translated slugs are stored per locale and applied to generated product links when the option is enabled. Whether those URLs resolve depends on your permalink and multilingual setup; the plugin does not create separate products or rewrite rules for each language.

= Is any data stored externally? =

Yes. Text, VAT numbers and messages are transmitted to the configured EuroCross AI API. See the Privacy section above and your provider's privacy policy.

= How do I remove all plugin data? =

Uninstalling the plugin deletes its options, transients, cached translations, stored product translation meta, user meta, scheduled cron events and any plugin tables.

== Screenshots ==

1. The EuroCross AI settings screen with connection, translation, VAT and chatbot options.
2. The dashboard showing live API status and available languages.
3. A WooCommerce storefront with the language switcher and VAT checkout field.

== Changelog ==

= 1.2.6 =
* Fixed Plugin Check (PCP) escaping/sanitization findings and removed discouraged load_plugin_textdomain call.
* Updated "Tested up to" for current WordPress compatibility.

= 1.2.5 =
* Added a permanent Free plan fallback (monthly limits) so functionality is not locked after trial expiration.
* Clarified external service, privacy, terms, and quota reset behavior for WordPress.org compliance.
* Allowed API key generation for Free/trial accounts; paid plans increase limits and features.

= 1.2.4 =
* Allowed nonce-protected, rate-limited storefront translation REST requests for WooCommerce dynamic content while keeping chatbot public-AI gating.
* Added clear WP admin notices for quota/access/API translation pauses with safe fallback behavior.
* Added billing ops visibility in dashboard: webhook events, manual payment review, and conflict/refund guidance.
* Added WooCommerce go-live hardening checklist and supported-setup documentation.

= 1.2.3 =
* Added wp_date/date_i18n frontend translation handlers with recursion guards and locale-aware reformatting.
* Added gettext/ngettext placeholder shielding to safely translate more UI strings with variables.
* Improved date fallback by preferring deterministic month/day mapping before AI fallback.

= 1.2.2 =
* Added server-side translation for classic WooCommerce cart fragments and selected Woo Store API REST responses.
* Broadened frontend gettext/ngettext translation with safer UI-string heuristics across domains.
* Added deterministic date name fallback for month/day localization when WP locale packs are unavailable.
* Added server-provided wcSettings patch map and improved client re-patching for dynamic Woo blocks.

= 1.2.1 =
* Improved frontend translation coverage for WooCommerce blocks, dynamic mini-cart/checkout fragments, and selected gettext strings.
* Improved wcSettings patching with repeated best-effort updates for late-initialized Woo block labels.
* Improved date localization by using WordPress locale-aware formatting when available, with AI fallback.
* Added safeguards against trial restart and duplicate active checkout/subscription activation.

= 1.2.0 =
* Added a Translations screen backed by persisted SaaS records: live filters, status table, manual edits, single and bulk retries, and quota/usage figures from the API.
* Added optional auto-translate on WooCommerce product save with fingerprint deduplication and background scheduling.
* Added optional translation of SEO metadata and product slugs, stored per locale and applied for the active language.
* Added local product translation storage with a daily sync from the SaaS API and reuse of completed translations before any new API call.
* Propagated real API error messages and HTTP statuses to the admin UI, including word-quota responses.
* Extended uninstall cleanup for stored product translation meta and the product translation cron event.

= 1.1.1 =
* Public AI requests are now disabled by default; enable the setting only for intentionally public translation/chatbot shortcodes.
* Public REST routes are rate limited per administrator or visitor IP address.
* Only administrators can change the site-wide default language.
* Clarified that VAT/VIES tools are informational and not a compliance guarantee.

= 1.1.0 =
* Secure connect flow: the plugin generates a state value, the user approves in the SaaS dashboard, and a one-time code is exchanged server-to-server for an API key and site secret.
* Plan and language limits are pushed to connected sites after subscription changes via a secret-protected REST endpoint.
* Target languages use a multiselect populated only from the purchased plan; the plan field is read-only.
* Updated default API URL and documentation for the live EuroCross SaaS domain.

= 1.0.0 =
* Initial WordPress.org release.
* Server-side API client using the WordPress HTTP API with timeouts and consistent error handling.
* Settings page with sanitized options, connection test, trial and checkout actions.
* Live dashboard, language switcher widget and shortcodes.
* WooCommerce product translation, VAT/VIES checkout handling and order email translation.
* REST routes under `eurocross/v1` with WordPress nonce checks.
* Daily health sync, debug logging and complete uninstall cleanup.

== Upgrade Notice ==

= 1.2.0 =
Review the new Translations screen and enable auto-translation or SEO/slug options only if you want them. All new translation options are disabled by default.

= 1.1.0 =
Reconnect from EuroCross AI > Settings to enable automatic plan and language syncing. A EuroCross AI SaaS account is required.

= 1.0.0 =
First release. A EuroCross AI SaaS account and API key are required.
