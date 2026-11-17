=== EuroCross AI ===
Contributors: eurocrossai
Donate link: https://eurocross.ai
Tags: translation, woocommerce, multilingual, vat, chatbot
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI translation, language switching and EU VAT/OSS handling for WooCommerce, powered by the EuroCross AI SaaS API.

== Description ==

EuroCross AI connects your WooCommerce store to your EuroCross AI SaaS account. Translation, VAT and chatbot requests are made from the server to the Laravel API, and the results are rendered in your store and admin dashboard. No translation data or pricing figures are invented in the browser.

= Features =

* **AI translation** — translate product titles and descriptions through the SaaS API, with local transients that cache results and preserve the original text if the API is unavailable.
* **Language switcher** — a widget and the `[eurocross_language_switcher]` shortcode render the languages actually returned by the API. Visitors can persist their choice in a cookie or local storage.
* **Chatbot** — the `[eurocross_chatbot]` shortcode posts messages to the REST chat endpoint, which calls the SaaS API server-side.
* **VAT and OSS** — add a VAT number field to checkout, validate numbers through VIES, request the VAT treatment from the API and display reverse-charge or OSS notes.
* **Order emails** — translate order email subjects and content for recipients with a different active locale.
* **Dashboard** — the admin dashboard displays live status, language count and chatbot information fetched from the API.
* **Developer friendly** — REST routes under `eurocross/v1`, translatable strings and a debug log mode with redacted API calls.

= External service dependency =

This plugin requires an active EuroCross AI SaaS account and API key. It sends text submitted for translation, VAT numbers and chat messages to the API URL configured in the settings (default: `https://app.eurocross.ai`). The API key is stored on the server and is never exposed to JavaScript or to site visitors.

= Privacy =

The plugin stores the configured settings, cached translations, cached language lists and the last connection status in your WordPress database. Translated text may be cached locally to avoid repeated API calls. All plugin data is removed when the plugin is uninstalled. Requests to the external API are subject to the privacy policy of your EuroCross AI provider.

== Installation ==

1. Upload the `eurocross-ai` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress Plugins screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to **EuroCross AI > Settings**.
4. Enter your SaaS API key and, if needed, change the API URL.
5. Choose the default language, target languages and the WooCommerce translation and VAT options.
6. Click **Save Changes**, then use **Test connection** to verify the API key.

== Frequently Asked Questions ==

= Do I need a WooCommerce store? =

No. The language switcher, REST endpoints and chatbot work without WooCommerce. WooCommerce-specific hooks are only registered when WooCommerce is active.

= Where do I get an API key? =

Create an account with EuroCross AI and copy the key from your SaaS dashboard. Enter it in the plugin settings; it is sent only in server-side requests as the `X-API-Key` header.

= Can I use the plugin without an API key? =

No. All translation, VAT and chatbot calls require a valid key. The settings page and admin notice will remind you when it is missing.

= How are languages listed in the switcher? =

The switcher calls the SaaS languages endpoint and caches the real response in a transient. If the API cannot be reached, the switcher shows an unavailable message rather than a hardcoded language list.

= How do I display the chatbot? =

Enable the chatbot option, then add `[eurocross_chatbot]` to a page. The widget posts to the `eurocross/v1/chat` REST route with the WordPress REST nonce.

= What happens during checkout? =

When a customer enters a VAT number, the plugin validates it through the VIES endpoint and requests the VAT treatment from the API. The returned rate, reason and reverse-charge status are displayed in the order review.

= Is any data stored externally? =

Yes. Text, VAT numbers and messages are transmitted to the configured EuroCross AI API. See the Privacy section above and your provider's privacy policy.

= How do I remove all plugin data? =

Uninstalling the plugin deletes its options, transients, cached translations, user meta, scheduled cron events and any plugin tables.

== Screenshots ==

1. The EuroCross AI settings screen with connection, translation, VAT and chatbot options.
2. The dashboard showing live API status and available languages.
3. A WooCommerce storefront with the language switcher and VAT checkout field.

== Changelog ==

= 1.0.0 =
* Initial WordPress.org release.
* Server-side API client using the WordPress HTTP API with timeouts and consistent error handling.
* Settings page with sanitized options, connection test, trial and checkout actions.
* Live dashboard, language switcher widget and shortcodes.
* WooCommerce product translation, VAT/VIES checkout handling and order email translation.
* REST routes under `eurocross/v1` with WordPress nonce checks.
* Daily health sync, debug logging and complete uninstall cleanup.

== Upgrade Notice ==

= 1.0.0 =
First release. A EuroCross AI SaaS account and API key are required.
