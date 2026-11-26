<?php
/**
 * Plugin Name: EuroCross AI
 * Plugin URI: https://eurocross-ai.kadetech.online
 * Description: AI translation, language switching and EU VAT/OSS handling for WooCommerce, powered by the EuroCross AI SaaS API.
 * Version: 1.2.8
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: EuroCross AI
 * Author URI: https://eurocross-ai.kadetech.online
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: eurocross-ai
 * Domain Path: /languages
 */
if (! defined('ABSPATH')) {
    exit;
}

if (! defined('EUROCROSS_AI_VERSION')) {
    define('EUROCROSS_AI_VERSION', '1.2.8');
}
if (! defined('EUROCROSS_AI_PLUGIN_FILE')) {
    define('EUROCROSS_AI_PLUGIN_FILE', __FILE__);
}
if (! defined('EUROCROSS_AI_PLUGIN_DIR')) {
    define('EUROCROSS_AI_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (! defined('EUROCROSS_AI_PLUGIN_URL')) {
    define('EUROCROSS_AI_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (! defined('EUROCROSS_AI_TEXT_DOMAIN')) {
    define('EUROCROSS_AI_TEXT_DOMAIN', 'eurocross-ai');
}
if (! defined('EUROCROSS_AI_CRON_HOOK')) {
    define('EUROCROSS_AI_CRON_HOOK', 'eurocross_ai_daily_sync');
}
if (! defined('EUROCROSS_AI_PRODUCT_TRANSLATION_HOOK')) {
    define('EUROCROSS_AI_PRODUCT_TRANSLATION_HOOK', 'eurocross_ai_translate_product');
}

require_once EUROCROSS_AI_PLUGIN_DIR.'includes/class-eurocross-api.php';
require_once EUROCROSS_AI_PLUGIN_DIR.'includes/class-eurocross-rest.php';
require_once EUROCROSS_AI_PLUGIN_DIR.'includes/class-eurocross-woocommerce.php';
require_once EUROCROSS_AI_PLUGIN_DIR.'includes/class-eurocross-uninstall.php';

/**
 * Return the default value for every plugin option.
 *
 * @return array<string,mixed>
 */
function eurocross_ai_default_options()
{
    return [
        'eurocross_api_url' => 'https://eurocross-ai.kadetech.online',
        'eurocross_api_key' => '',
        'eurocross_site_secret' => '',
        'eurocross_locale' => 'en',
        'eurocross_plan' => 'starter',
        'eurocross_plan_limits' => [],
        'eurocross_last_plan_sync' => 0,
        'eurocross_target_languages' => [],
        'eurocross_use_oss' => 0,
        'eurocross_vat_number' => '',
        'eurocross_default_country' => '',
        'eurocross_chatbot_enabled' => 0,
        'eurocross_allow_public_ai' => 0,
        'eurocross_translate_product_titles' => 1,
        'eurocross_translate_product_descriptions' => 1,
        'eurocross_auto_translate_on_save' => 0,
        'eurocross_translate_seo' => 0,
        'eurocross_translate_slug' => 0,
        'eurocross_debug' => 0,
    ];
}

/**
 * Read a plugin option with a safe default.
 *
 * @param  string  $key  Option name.
 * @return mixed
 */
function eurocross_ai_get_option($key)
{
    $defaults = eurocross_ai_default_options();
    $default = array_key_exists($key, $defaults) ? $defaults[$key] : '';

    return get_option($key, $default);
}

/**
 * Check whether a boolean plugin option is enabled.
 *
 * @param  string  $key  Option name.
 * @return bool
 */
function eurocross_ai_is_enabled($key)
{
    return in_array((string) eurocross_ai_get_option($key), ['1', 'yes', 'true', 'on'], true);
}

/**
 * Sanitize a checkbox value.
 *
 * @param  mixed  $value  Raw value.
 * @return int
 */
function eurocross_ai_sanitize_checkbox($value)
{
    return ! empty($value) ? 1 : 0;
}

/**
 * Sanitize a language code.
 *
 * @param  mixed  $value  Raw value.
 * @return string
 */
function eurocross_ai_sanitize_language_code($value)
{
    $code = strtolower(str_replace('_', '-', sanitize_text_field((string) $value)));
    $code = trim($code, '-');

    return preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $code) ? $code : '';
}

/**
 * Sanitize a two-letter country code.
 *
 * @param  mixed  $value  Raw value.
 * @return string
 */
function eurocross_ai_sanitize_country_code($value)
{
    $country = strtoupper(preg_replace('/[^a-zA-Z]/', '', sanitize_text_field((string) $value)));

    return preg_match('/^[A-Z]{2}$/', $country) ? $country : '';
}

/**
 * Sanitize a VAT number.
 *
 * @param  mixed  $value  Raw value.
 * @return string
 */
function eurocross_ai_sanitize_vat_number($value)
{
    $vat = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', sanitize_text_field((string) $value)));

    return substr($vat, 0, 20);
}

/**
 * Sanitize the API base URL.
 *
 * @param  mixed  $value  Raw value.
 * @return string
 */
function eurocross_ai_sanitize_api_url($value)
{
    $url = untrailingslashit(esc_url_raw(trim((string) $value), ['http', 'https']));

    if ($url === '') {
        $defaults = eurocross_ai_default_options();
        $url = $defaults['eurocross_api_url'];
    }

    return $url;
}

/**
 * Sanitize the SaaS API key.
 *
 * @param  mixed  $value  Raw value.
 * @return string
 */
function eurocross_ai_sanitize_api_key($value)
{
    return substr(sanitize_text_field(trim((string) $value)), 0, 255);
}

/**
 * Sanitize a plan identifier.
 *
 * @param  mixed  $value  Raw value.
 * @return string
 */
function eurocross_ai_sanitize_plan($value)
{
    return sanitize_key((string) $value);
}

/**
 * Sanitize the list of target languages.
 *
 * @param  mixed  $value  Raw value.
 * @return array<int,string>
 */
function eurocross_ai_sanitize_target_languages($value)
{
    if (! is_array($value)) {
        return [];
    }

    $languages = [];
    foreach ($value as $code) {
        $sanitized = eurocross_ai_sanitize_language_code($code);
        if ($sanitized !== '') {
            $languages[] = $sanitized;
        }
    }

    return array_values(array_unique($languages));
}

/**
 * Recursively sanitize data returned by the API before storing or rendering it.
 *
 * @param  mixed  $value  Raw value.
 * @return mixed
 */
function eurocross_ai_sanitize_response_data($value)
{
    if (is_array($value)) {
        $sanitized = [];
        foreach ($value as $key => $item) {
            $sanitized[is_int($key) ? $key : sanitize_key((string) $key)] = eurocross_ai_sanitize_response_data($item);
        }

        return $sanitized;
    }

    if (is_bool($value) || is_int($value) || is_float($value)) {
        return $value;
    }

    if ($value === null) {
        return null;
    }

    return sanitize_text_field((string) $value);
}

/**
 * Return the shared API client.
 *
 * @param  bool  $reset  Rebuild the client after the API key or URL changes.
 * @return EuroCross_AI_API
 */
function eurocross_ai_get_api($reset = false)
{
    if ($reset || ! isset($GLOBALS['eurocross_ai_api_client'])) {
        $GLOBALS['eurocross_ai_api_client'] = new EuroCross_AI_API(
            eurocross_ai_get_option('eurocross_api_url'),
            eurocross_ai_get_option('eurocross_api_key')
        );
    }

    return $GLOBALS['eurocross_ai_api_client'];
}

/**
 * Write a redacted message to the PHP error log when debug mode is enabled.
 *
 * @param  string  $message  Log message.
 * @param  array  $context  Context data.
 * @return void
 */
function eurocross_ai_log($message, $context = [])
{
    if (! eurocross_ai_is_enabled('eurocross_debug')) {
        return;
    }

    $sensitive = ['api_key', 'apikey', 'x-api-key', 'authorization', 'token', 'secret'];
    foreach ($context as $key => $value) {
        if (in_array(strtolower((string) $key), $sensitive, true)) {
            $context[$key] = '[redacted]';
        }
    }

    error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        sprintf(
            '[EuroCross AI] %s %s',
            sanitize_text_field($message),
            wp_json_encode(eurocross_ai_sanitize_response_data($context))
        )
    );
}

/**
 * Clear cached language and dashboard data.
 *
 * @return void
 */
function eurocross_ai_clear_language_cache()
{
    delete_transient('eurocross_language_payload');
    delete_transient('eurocross_dashboard_data');
}

/**
 * Fetch and cache plan-aware language data from the SaaS API.
 *
 * @param  bool  $force  Bypass the cache.
 * @return array{languages: array<int,array<string,string>>, meta: array<string,mixed>, all_languages: array<int,array<string,string>>}
 */
function eurocross_ai_get_language_payload($force = false)
{
    $cache_key = 'eurocross_language_payload';

    if (! $force) {
        $cached = get_transient($cache_key);
        if (is_array($cached) && isset($cached['languages']) && is_array($cached['languages'])) {
            return [
                'languages' => $cached['languages'],
                'meta' => isset($cached['meta']) && is_array($cached['meta']) ? $cached['meta'] : [],
                'all_languages' => isset($cached['all_languages']) && is_array($cached['all_languages']) ? $cached['all_languages'] : [],
            ];
        }
    }

    $response = eurocross_ai_get_api()->languages();
    if (is_wp_error($response)) {
        eurocross_ai_log('Could not fetch languages', ['error' => $response->get_error_message()]);

        return ['languages' => [], 'meta' => [], 'all_languages' => []];
    }

    $languages = eurocross_ai_sanitize_language_list(isset($response['data']) ? $response['data'] : []);
    $meta = isset($response['meta']) && is_array($response['meta'])
        ? eurocross_ai_sanitize_response_data($response['meta'])
        : [];
    $allLanguages = eurocross_ai_sanitize_language_list(isset($response['all_languages']) ? $response['all_languages'] : $languages);

    $status = isset($meta['status']) ? sanitize_key((string) $meta['status']) : '';
    $limit = isset($meta['limits']['languages']) ? absint($meta['limits']['languages']) : count($languages);
    if (in_array($status, ['trial', 'grace'], true)) {
        $limit = max($limit, 10);
    }

    if (! empty($allLanguages) && $limit > 0) {
        $effective = array_slice($allLanguages, 0, $limit);
        if (count($effective) >= count($languages)) {
            $languages = $effective;
        }
    }

    if (! isset($meta['limits']) || ! is_array($meta['limits'])) {
        $meta['limits'] = [];
    }
    $meta['limits']['languages'] = $limit;

    $payload = [
        'languages' => $languages,
        'meta' => $meta,
        'all_languages' => $allLanguages,
    ];

    if (! empty($languages)) {
        set_transient($cache_key, $payload, 12 * HOUR_IN_SECONDS);
    }

    return $payload;
}

/**
 * Sanitize a list of languages returned by the SaaS API.
 *
 * @param  mixed  $raw  Raw language list.
 * @return array<int,array<string,string>>
 */
function eurocross_ai_sanitize_language_list($raw)
{
    $languages = [];

    if (! is_array($raw)) {
        return $languages;
    }

    foreach ($raw as $language) {
        if (! is_array($language)) {
            continue;
        }

        $code = eurocross_ai_sanitize_language_code(isset($language['code']) ? $language['code'] : '');
        if ($code === '') {
            continue;
        }

        $languages[] = [
            'code' => $code,
            'name' => sanitize_text_field(isset($language['name']) ? (string) $language['name'] : $code),
            'native_name' => sanitize_text_field(isset($language['native_name']) ? (string) $language['native_name'] : ''),
        ];
    }

    return $languages;
}

/**
 * Fetch the languages included in the connected plan.
 *
 * @param  bool  $force  Bypass the cache.
 * @return array<int,array<string,string>>
 */
function eurocross_ai_get_languages($force = false)
{
    return eurocross_ai_get_language_payload($force)['languages'];
}

/**
 * Fetch the connected plan metadata.
 *
 * @param  bool  $force  Bypass the cache.
 * @return array<string,mixed>
 */
function eurocross_ai_get_plan_context($force = false)
{
    return eurocross_ai_get_language_payload($force)['meta'];
}

/**
 * Persist a plan payload received from the SaaS API.
 *
 * @param  array<string,mixed>  $payload  Plan payload.
 * @return bool
 */
function eurocross_ai_apply_plan_payload($payload)
{
    if (! is_array($payload)) {
        return false;
    }

    $meta = isset($payload['meta']) && is_array($payload['meta']) ? $payload['meta'] : $payload;
    $plan = sanitize_key(isset($meta['plan']) ? (string) $meta['plan'] : '');
    if (! in_array($plan, ['starter', 'growth', 'scale'], true)) {
        return false;
    }

    $allowed = eurocross_ai_sanitize_language_list(isset($meta['allowed_languages']) ? $meta['allowed_languages'] : []);
    if (empty($allowed)) {
        $allowed = eurocross_ai_sanitize_language_list(isset($meta['languages']) ? $meta['languages'] : []);
    }
    if (empty($allowed)) {
        return false;
    }

    $allowedCodes = wp_list_pluck($allowed, 'code');
    $limits = isset($meta['limits']) && is_array($meta['limits'])
        ? eurocross_ai_sanitize_response_data($meta['limits'])
        : [];

    update_option('eurocross_plan', $plan, false);
    update_option('eurocross_plan_limits', $limits, false);
    update_option('eurocross_last_plan_sync', time(), false);

    $selected = array_values(array_intersect(
        (array) eurocross_ai_get_option('eurocross_target_languages'),
        $allowedCodes
    ));
    if (empty($selected)) {
        $limit = absint(isset($limits['languages']) ? $limits['languages'] : count($allowedCodes));
        $selected = array_slice($allowedCodes, 0, $limit > 0 ? $limit : count($allowedCodes));
    }
    update_option('eurocross_target_languages', $selected, false);

    eurocross_ai_clear_language_cache();

    return true;
}

/**
 * Build the SaaS URL that starts the WordPress connection flow.
 *
 * @return string
 */
function eurocross_ai_connect_url()
{
    $state = wp_generate_password(32, false, false);
    set_transient('eurocross_connect_state_'.get_current_user_id(), $state, 10 * MINUTE_IN_SECONDS);

    $args = [
        'site_url' => home_url(),
        'site_name' => get_bloginfo('name'),
        'return_url' => admin_url('admin-post.php'),
        'state' => $state,
    ];

    return untrailingslashit(eurocross_ai_get_option('eurocross_api_url')).'/connect/start?'.http_build_query($args, '', '&', PHP_QUERY_RFC3986);
}

/**
 * Resolve the locale for the current visitor.
 *
 * @return string
 */
function eurocross_ai_get_current_locale()
{
    static $resolved = null;
    if ($resolved !== null) {
        return $resolved;
    }

    $default = eurocross_ai_sanitize_language_code(eurocross_ai_get_option('eurocross_locale'));
    if ($default === '') {
        $default = 'en';
    }

    $request_locale = '';
    if (isset($_GET['eurocross_lang'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public locale switch.
        $request_locale = eurocross_ai_sanitize_language_code(sanitize_text_field(wp_unslash($_GET['eurocross_lang']))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public locale switch.
    } elseif (isset($_GET['lang'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public locale switch.
        $request_locale = eurocross_ai_sanitize_language_code(sanitize_text_field(wp_unslash($_GET['lang']))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public locale switch.
    }

    if ($request_locale !== '') {
        if (! headers_sent()) {
            setcookie('eurocross_locale', $request_locale, time() + YEAR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false);
        }
        $_COOKIE['eurocross_locale'] = $request_locale;

        $resolved = $request_locale;

        return $resolved;
    }

    if (is_user_logged_in()) {
        $user_locale = eurocross_ai_sanitize_language_code(get_user_meta(get_current_user_id(), 'eurocross_locale', true));
        if ($user_locale !== '') {
            $resolved = $user_locale;

            return $resolved;
        }
    }

    if (isset($_COOKIE['eurocross_locale'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only locale cookie.
        $cookie_locale = eurocross_ai_sanitize_language_code(sanitize_text_field(wp_unslash($_COOKIE['eurocross_locale'])));
        if ($cookie_locale !== '') {
            $resolved = $cookie_locale;

            return $resolved;
        }
    }

    $resolved = $default;

    return $resolved;
}

/**
 * Translate text through the API with a persistent cache and safe fallback.
 *
 * @param  string  $text  Text to translate.
 * @param  string  $source  Source language code.
 * @param  string  $target  Target language code.
 * @param  string  $context  Translation context.
 * @return string
 */
function eurocross_ai_translate_text($text, $source, $target, $context = '')
{
    static $remote_calls = 0;

    if (! is_string($text) || $text === '' || $source === $target) {
        return $text;
    }

    $cache_key = 'eurocross_translation_'.md5($text.'|'.$source.'|'.$target.'|'.$context);
    $cached = get_transient($cache_key);
    if ($cached !== false && is_string($cached)) {
        return $cached;
    }

    $fallback_cache_key = 'eurocross_translation_'.md5($text.'|'.$source.'|'.$target.'|frontend_text');
    if ($fallback_cache_key !== $cache_key) {
        $fallback_cached = get_transient($fallback_cache_key);
        if ($fallback_cached !== false && is_string($fallback_cached)) {
            set_transient($cache_key, $fallback_cached, DAY_IN_SECONDS);

            return $fallback_cached;
        }
    }

    $is_frontend_render = ! is_admin()
        && ! wp_doing_ajax()
        && ! wp_doing_cron()
        && ! (defined('REST_REQUEST') && REST_REQUEST)
        && ! (defined('WP_CLI') && WP_CLI)
        && PHP_SAPI !== 'cli';

    $is_main_content = $context === 'post_content'
        || $context === 'post_excerpt'
        || $context === 'product_description'
        || $context === 'product_short_description'
        || strpos((string) $context, 'block_') === 0;

    if ($is_frontend_render && ! $is_main_content && $remote_calls >= 60) {
        return $text;
    }

    $response = eurocross_ai_get_api()->translate($text, $source, $target);
    if ($is_frontend_render && ! $is_main_content) {
        $remote_calls++;
    }

    if (is_wp_error($response)) {
        $error_data = $response->get_error_data();
        $status = is_array($error_data) && isset($error_data['status']) ? absint($error_data['status']) : 0;
        $message = sanitize_text_field((string) $response->get_error_message());

        eurocross_ai_log('Translation failed', ['error' => $message, 'status' => $status]);

        if (in_array($status, [401, 402, 403, 429], true)) {
            set_transient(
                'eurocross_ai_last_api_issue',
                [
                    'status' => $status,
                    'message' => $message,
                    'time' => time(),
                ],
                HOUR_IN_SECONDS
            );
            set_transient($cache_key, $text, 15 * MINUTE_IN_SECONDS);
        } else {
            set_transient($cache_key, $text, 5 * MINUTE_IN_SECONDS);
        }

        return $text;
    }

    $translated = isset($response['data']['translated']) ? (string) $response['data']['translated'] : '';
    if ($translated === '') {
        set_transient($cache_key, $text, MINUTE_IN_SECONDS);

        return $text;
    }

    delete_transient('eurocross_ai_last_api_issue');

    $allow_html = in_array($context, ['product_description', 'product_short_description', 'order_email', 'post_content', 'post_excerpt', 'widget_text', 'term_description'], true)
        || strpos((string) $context, 'block_') === 0;

    $translated = $allow_html ? wp_kses_post($translated) : sanitize_text_field($translated);

    set_transient($cache_key, $translated, DAY_IN_SECONDS);
    if ($fallback_cache_key !== $cache_key) {
        set_transient($fallback_cache_key, $translated, DAY_IN_SECONDS);
    }

    return $translated;
}

/**
 * Convert an API WP_Error into a REST error with a usable HTTP status.
 *
 * @param  WP_Error  $error  Original error.
 * @return WP_Error
 */
function eurocross_ai_rest_error($error)
{
    $data = $error->get_error_data();
    $status = is_array($data) && isset($data['status']) ? absint($data['status']) : 502;
    if ($status < 400 || $status > 599) {
        $status = 502;
    }

    $code = $error->get_error_code();
    if (empty($code)) {
        $code = 'eurocross_api_error';
    }

    $rest_data = ['status' => $status];
    if (is_array($data) && isset($data['response']) && is_array($data['response'])) {
        $rest_data['context'] = $data['response'];
    }

    return new WP_Error($code, $error->get_error_message(), $rest_data);
}

/**
 * Render a single API value as safe HTML.
 *
 * @param  mixed  $value  Value to render.
 * @param  int  $depth  Current recursion depth.
 * @return string
 */
function eurocross_ai_render_api_value($value, $depth = 0)
{
    if ($depth > 3) {
        return '';
    }

    if (is_array($value)) {
        if (empty($value)) {
            return '<span class="eurocross-empty">'.esc_html__('No data returned.', 'eurocross-ai').'</span>';
        }

        $html = '<dl class="eurocross-data-list">';
        foreach ($value as $key => $item) {
            $label = is_int($key)
                ? sprintf(
                    /* translators: %d: item number. */
                    esc_html__('Item %d', 'eurocross-ai'),
                    $key + 1
                )
                : ucwords(str_replace(['_', '-'], ' ', (string) $key));

            $html .= '<dt>'.esc_html($label).'</dt>';
            $html .= '<dd>'.eurocross_ai_render_api_value($item, $depth + 1).'</dd>';
        }

        return $html.'</dl>';
    }

    if (is_bool($value)) {
        return $value
            ? esc_html__('Yes', 'eurocross-ai')
            : esc_html__('No', 'eurocross-ai');
    }

    if ($value === null || $value === '') {
        return '<span class="eurocross-empty">'.esc_html__('Not available', 'eurocross-ai').'</span>';
    }

    return esc_html((string) $value);
}

/**
 * Collect live dashboard data from the SaaS API.
 *
 * @param  bool  $force  Bypass the cache.
 * @return array<string,mixed>
 */
function eurocross_ai_get_dashboard_data($force = false)
{
    $cache_key = 'eurocross_dashboard_data';

    if (! $force) {
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $api = eurocross_ai_get_api();
    $data = [
        'checked_at' => time(),
        'health' => null,
        'chatbot' => null,
        'account' => null,
        'languages' => [],
        'errors' => [],
    ];

    $health = $api->health();
    if (is_wp_error($health)) {
        $data['errors']['health'] = $health->get_error_message();
    } else {
        $data['health'] = eurocross_ai_sanitize_response_data($health);
    }

    $chatbot = $api->chatbot_status();
    if (is_wp_error($chatbot)) {
        $data['errors']['chatbot'] = $chatbot->get_error_message();
    } else {
        $data['chatbot'] = eurocross_ai_sanitize_response_data($chatbot);
    }

    $account = $api->account_summary();
    if (is_wp_error($account)) {
        $data['errors']['account'] = $account->get_error_message();
    } else {
        $data['account'] = eurocross_ai_sanitize_response_data($account);
    }

    $data['languages'] = eurocross_ai_get_languages($force);
    if (empty($data['languages'])) {
        $data['errors']['languages'] = __('No languages were returned by the API.', 'eurocross-ai');
    }

    set_transient($cache_key, $data, 10 * MINUTE_IN_SECONDS);
    update_option('eurocross_last_sync', time(), false);

    return $data;
}

/**
 * Build accessible language switcher markup from live API languages.
 *
 * @param  array<string,mixed>  $args  Display arguments.
 * @return string
 */
function eurocross_ai_language_switcher_markup($args = [])
{
    $args = wp_parse_args(
        $args,
        [
            'show_label' => true,
            'class' => '',
            'id_prefix' => 'eurocross-language',
        ]
    );

    $languages = eurocross_ai_get_languages();
    if (empty($languages)) {
        return '<p class="eurocross-language-unavailable">'.esc_html__('Languages are currently unavailable. Please try again later.', 'eurocross-ai').'</p>';
    }

    static $instance = 0;
    $instance++;

    $select_id = sanitize_html_class($args['id_prefix']).'-'.$instance;
    $current = eurocross_ai_get_current_locale();
    $label_text = esc_html__('Language', 'eurocross-ai');
    $aria_label = esc_attr__('Language switcher', 'eurocross-ai');

    $current_name = $label_text;
    foreach ($languages as $language) {
        $code = isset($language['code']) ? $language['code'] : '';
        $name = ! empty($language['native_name']) ? $language['native_name'] : (! empty($language['name']) ? $language['name'] : $code);
        if ($code === $current) {
            $current_name = $name;
            break;
        }
    }

    $list_id = $select_id.'-list';
    $html = '<nav class="eurocross-language-switcher '.esc_attr(sanitize_html_class($args['class'])).'" aria-label="'.$aria_label.'" data-eurocross-switcher-root>';
    if ($args['show_label']) {
        $html .= '<span class="eurocross-language-label">'.esc_html($label_text).'</span>';
    }
    $html .= '<div class="eurocross-language-dropdown" data-eurocross-language-dropdown>';
    $html .= '<button type="button" id="'.esc_attr($select_id).'" class="eurocross-language-toggle" data-eurocross-language-toggle aria-haspopup="listbox" aria-expanded="false" aria-controls="'.esc_attr($list_id).'">';
    $html .= '<span class="eurocross-language-toggle-label" data-eurocross-language-current>'.esc_html($current_name).'</span>';
    $html .= '<span class="eurocross-language-caret" aria-hidden="true"></span>';
    $html .= '</button>';
    $html .= '<ul id="'.esc_attr($list_id).'" class="eurocross-language-menu" data-eurocross-language-menu role="listbox" aria-label="'.esc_attr($label_text).'" hidden>';

    foreach ($languages as $language) {
        $code = isset($language['code']) ? $language['code'] : '';
        $name = ! empty($language['native_name']) ? $language['native_name'] : (! empty($language['name']) ? $language['name'] : $code);
        $is_current = ($code === $current);
        $html .= '<li role="option" aria-selected="'.($is_current ? 'true' : 'false').'">';
        $html .= '<button type="button" class="eurocross-language-option'.($is_current ? ' is-active' : '').'" data-eurocross-language-option data-locale="'.esc_attr($code).'">'.esc_html($name).'</button>';
        $html .= '</li>';
    }

    $html .= '</ul></div></nav>';

    return $html;
}

if (class_exists('WP_Widget')) {
    /**
     * Language switcher widget.
     */
    class EuroCross_AI_Language_Switcher_Widget extends WP_Widget
    {
        /**
         * Constructor.
         */
        public function __construct()
        {
            parent::__construct(
                'eurocross_language_switcher',
                esc_html__('EuroCross Language Switcher', 'eurocross-ai'),
                [
                    'description' => esc_html__('Show the languages available from the EuroCross AI API.', 'eurocross-ai'),
                ]
            );
        }

        /**
         * Output the widget.
         *
         * @param  array  $args  Widget arguments.
         * @param  array  $instance  Widget instance.
         * @return void
         */
        public function widget($args, $instance)
        {
            $before_widget = isset($args['before_widget']) ? wp_kses_post($args['before_widget']) : '';
            $after_widget = isset($args['after_widget']) ? wp_kses_post($args['after_widget']) : '';
            $before_title = isset($args['before_title']) ? wp_kses_post($args['before_title']) : '';
            $after_title = isset($args['after_title']) ? wp_kses_post($args['after_title']) : '';

            echo $before_widget; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

            if (! empty($instance['title'])) {
                $title = apply_filters('widget_title', $instance['title'], $instance, $this->id_base);
                echo $before_title.esc_html($title).$after_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }

            echo eurocross_ai_language_switcher_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

            echo $after_widget; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        /**
         * Output the widget settings form.
         *
         * @param  array  $instance  Widget instance.
         * @return string
         */
        public function form($instance)
        {
            $title = isset($instance['title']) ? (string) $instance['title'] : '';
            ?>
		<p>
			<label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
				<?php esc_html_e('Title:', 'eurocross-ai'); ?>
			</label>
			<input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
		</p>
		<?php
            return '';
        }

        /**
         * Update the widget instance.
         *
         * @param  array  $new_instance  New instance.
         * @param  array  $old_instance  Old instance.
         * @return array<string,string>
         */
        public function update($new_instance, $old_instance)
        {
            return [
                'title' => isset($new_instance['title']) ? sanitize_text_field((string) $new_instance['title']) : '',
            ];
        }
    }
}

/**
 * Main plugin class.
 */
class EuroCross_AI
{
    /**
     * Singleton instance.
     *
     * @var EuroCross_AI|null
     */
    private static $instance = null;

    /**
     * Return the singleton instance.
     *
     * @return EuroCross_AI
     */
    public static function init()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Wire up the plugin.
     */
    private function __construct()
    {
        register_activation_hook(EUROCROSS_AI_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(EUROCROSS_AI_PLUGIN_FILE, [$this, 'deactivate']);
        register_uninstall_hook(EUROCROSS_AI_PLUGIN_FILE, ['EuroCross_AI_Uninstall', 'handle']);

        $this->register_hooks();
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function register_hooks()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('wp_footer', [$this, 'render_floating_chatbot']);
        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('widgets_init', [$this, 'register_widget']);
        add_action('wp_head', [$this, 'output_hreflang_tags'], 1);
        add_filter('language_attributes', [$this, 'filter_language_attributes'], 20);
        add_filter('home_url', [$this, 'propagate_language_url'], 20);
        add_filter('page_link', [$this, 'propagate_language_url'], 20);
        add_filter('post_link', [$this, 'propagate_language_url'], 20);
        add_filter('post_type_link', [$this, 'propagate_language_url'], 20);
        add_filter('term_link', [$this, 'propagate_language_url'], 20);

        add_filter('the_title', [$this, 'translate_post_title'], 20, 2);
        add_filter('the_content', [$this, 'translate_post_content'], 20);
        add_filter('get_the_excerpt', [$this, 'translate_post_excerpt'], 20, 2);
        add_filter('wp_nav_menu_objects', [$this, 'translate_menu_items'], 20);
        add_filter('widget_title', [$this, 'translate_widget_title'], 20);
        add_filter('widget_text', [$this, 'translate_widget_text'], 20);
        add_filter('widget_text_content', [$this, 'translate_widget_text'], 20);
        add_filter('document_title_parts', [$this, 'translate_document_title_parts'], 20);
        add_filter('option_blogname', [$this, 'translate_blog_option_frontend'], 20);
        add_filter('option_blogdescription', [$this, 'translate_blog_option_frontend'], 20);
        add_filter('get_term', [$this, 'translate_term'], 20);
        add_filter('render_block', [$this, 'translate_rendered_block'], 20, 2);
        add_filter('get_the_archive_title', [$this, 'translate_archive_title'], 20);
        add_filter('get_the_archive_description', [$this, 'translate_archive_description'], 20);
        add_filter('single_post_title', [$this, 'translate_simple_frontend_text'], 20);
        add_filter('single_term_title', [$this, 'translate_simple_frontend_text'], 20);
        add_filter('bloginfo', [$this, 'filter_bloginfo_frontend'], 20, 2);
        add_filter('gettext', [$this, 'translate_frontend_gettext'], 20, 3);
        add_filter('ngettext', [$this, 'translate_frontend_ngettext'], 20, 5);
        add_filter('gettext_with_context', [$this, 'translate_frontend_gettext_with_context'], 20, 4);
        add_filter('ngettext_with_context', [$this, 'translate_frontend_ngettext_with_context'], 20, 6);
        add_filter('get_the_date', [$this, 'translate_frontend_date'], 20, 3);
        add_filter('get_the_time', [$this, 'translate_frontend_date'], 20, 3);
        add_filter('get_the_modified_date', [$this, 'translate_frontend_date'], 20, 3);
        add_filter('get_the_modified_time', [$this, 'translate_frontend_date'], 20, 3);
        add_filter('get_comment_date', [$this, 'translate_frontend_date'], 20, 3);
        add_filter('get_comment_time', [$this, 'translate_frontend_date'], 20, 3);
        add_filter('wp_date', [$this, 'translate_wp_date'], 20, 4);
        add_filter('date_i18n', [$this, 'translate_date_i18n'], 20, 4);
        add_filter('woocommerce_cart_item_name', [$this, 'translate_simple_frontend_text'], 20);
        add_filter('woocommerce_order_item_name', [$this, 'translate_simple_frontend_text'], 20);
        add_filter('woocommerce_add_to_cart_fragments', [$this, 'translate_wc_fragments'], 999, 1);
        add_filter('woocommerce_update_order_review_fragments', [$this, 'translate_wc_fragments'], 999, 1);
        add_filter('rest_post_dispatch', [$this, 'translate_wc_store_rest_response'], 20, 3);

        add_action(EUROCROSS_AI_CRON_HOOK, [$this, 'run_daily_sync']);

        add_action('admin_post_eurocross_connect', [$this, 'handle_connect_return']);
        add_action('admin_post_eurocross_start_trial', [$this, 'handle_start_trial']);
        add_action('admin_post_eurocross_checkout', [$this, 'handle_checkout']);
        add_action('admin_post_eurocross_dismiss_notice', [$this, 'dismiss_notice']);
        add_action('wp_ajax_eurocross_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_eurocross_start_trial', [$this, 'ajax_start_trial']);
        add_action('wp_ajax_eurocross_checkout', [$this, 'ajax_checkout']);

        add_shortcode('eurocross_language_switcher', [$this, 'language_switcher_shortcode']);
        add_shortcode('eurocross_chatbot', [$this, 'chatbot_shortcode']);

        $rest = new EuroCross_AI_REST;
        $rest->register();

        $woocommerce = new EuroCross_AI_WooCommerce;
        $woocommerce->register();
    }

    /**
     * Activation: create defaults and schedule the daily sync.
     *
     * @return void
     */
    public function activate()
    {
        foreach (eurocross_ai_default_options() as $option_name => $default_value) {
            add_option($option_name, $default_value, '', 'no');
        }

        $this->schedule_daily_sync();
    }

    /**
     * Deactivation: stop the daily sync.
     *
     * @return void
     */
    public function deactivate()
    {
        $this->unschedule_daily_sync();
    }

    /**
     * Schedule the daily health/usage sync.
     *
     * @return void
     */
    public function schedule_daily_sync()
    {
        if (! wp_next_scheduled(EUROCROSS_AI_CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', EUROCROSS_AI_CRON_HOOK);
        }
    }

    /**
     * Unschedule the daily health/usage sync.
     *
     * @return void
     */
    public function unschedule_daily_sync()
    {
        wp_clear_scheduled_hook(EUROCROSS_AI_CRON_HOOK);
    }

    /**
     * Daily cron callback: store real health and chatbot status from the API.
     *
     * @return void
     */
    public function run_daily_sync()
    {
        if (trim((string) eurocross_ai_get_option('eurocross_api_key')) === '') {
            return;
        }

        $health = eurocross_ai_get_api()->health();
        if (is_wp_error($health)) {
            set_transient(
                'eurocross_connection_status',
                [
                    'ok' => false,
                    'message' => $health->get_error_message(),
                    'checked_at' => time(),
                ],
                DAY_IN_SECONDS
            );
            eurocross_ai_log('Daily health sync failed', ['error' => $health->get_error_message()]);

            return;
        }

        set_transient(
            'eurocross_connection_status',
            [
                'ok' => true,
                'message' => __('Connected to the EuroCross AI API.', 'eurocross-ai'),
                'checked_at' => time(),
            ],
            DAY_IN_SECONDS
        );

        $chatbot_status = eurocross_ai_get_api()->chatbot_status();
        if (! is_wp_error($chatbot_status)) {
            set_transient('eurocross_chatbot_status', eurocross_ai_sanitize_response_data($chatbot_status), DAY_IN_SECONDS);
        }

        $languagePayload = eurocross_ai_get_language_payload(true);
        if (! empty($languagePayload['meta'])) {
            eurocross_ai_apply_plan_payload($languagePayload);
        }
        eurocross_ai_get_dashboard_data(true);

        if (class_exists('EuroCross_AI_WooCommerce') && method_exists('EuroCross_AI_WooCommerce', 'sync_product_translations')) {
            EuroCross_AI_WooCommerce::sync_product_translations();
        }
    }

    /**
     * Register the top-level menu and its pages.
     *
     * @return void
     */
    public function add_admin_menu()
    {
        add_menu_page(
            __('EuroCross AI', 'eurocross-ai'),
            __('EuroCross AI', 'eurocross-ai'),
            'manage_options',
            'eurocross-ai',
            [$this, 'dashboard_page'],
            'dashicons-translation',
            100
        );

        add_submenu_page(
            'eurocross-ai',
            __('Settings', 'eurocross-ai'),
            __('Settings', 'eurocross-ai'),
            'manage_options',
            'eurocross-ai-settings',
            [$this, 'settings_page']
        );

        add_submenu_page(
            'eurocross-ai',
            __('Dashboard', 'eurocross-ai'),
            __('Dashboard', 'eurocross-ai'),
            'manage_options',
            'eurocross-ai-dashboard',
            [$this, 'dashboard_page']
        );

        add_submenu_page(
            'eurocross-ai',
            __('Translations', 'eurocross-ai'),
            __('Translations', 'eurocross-ai'),
            'manage_options',
            'eurocross-ai-translations',
            [$this, 'translations_page']
        );

        add_submenu_page(
            'eurocross-ai',
            __('Info', 'eurocross-ai'),
            __('Info', 'eurocross-ai'),
            'manage_options',
            'eurocross-ai-info',
            [$this, 'info_page']
        );
    }

    /**
     * Register all plugin settings.
     *
     * @return void
     */
    public function register_settings()
    {
        register_setting(
            'eurocross-general',
            'eurocross_api_url',
            [
                'type' => 'string',
                'sanitize_callback' => 'eurocross_ai_sanitize_api_url',
                'default' => 'https://eurocross-ai.kadetech.online',
            ]
        );
        register_setting(
            'eurocross-general',
            'eurocross_api_key',
            [
                'type' => 'string',
                'sanitize_callback' => 'eurocross_ai_sanitize_api_key',
                'default' => '',
            ]
        );
        register_setting(
            'eurocross-general',
            'eurocross_locale',
            [
                'type' => 'string',
                'sanitize_callback' => 'eurocross_ai_sanitize_language_code',
                'default' => 'en',
            ]
        );
        register_setting(
            'eurocross-general',
            'eurocross_target_languages',
            [
                'type' => 'array',
                'sanitize_callback' => 'eurocross_ai_sanitize_target_languages',
                'default' => [],
            ]
        );
        register_setting(
            'eurocross-general',
            'eurocross_use_oss',
            [
                'type' => 'boolean',
                'sanitize_callback' => 'eurocross_ai_sanitize_checkbox',
                'default' => false,
            ]
        );
        register_setting(
            'eurocross-general',
            'eurocross_vat_number',
            [
                'type' => 'string',
                'sanitize_callback' => 'eurocross_ai_sanitize_vat_number',
                'default' => '',
            ]
        );
        register_setting(
            'eurocross-general',
            'eurocross_default_country',
            [
                'type' => 'string',
                'sanitize_callback' => 'eurocross_ai_sanitize_country_code',
                'default' => '',
            ]
        );
        register_setting(
            'eurocross-general',
            'eurocross_chatbot_enabled',
            [
                'type' => 'boolean',
                'sanitize_callback' => 'eurocross_ai_sanitize_checkbox',
                'default' => false,
            ]
        );
        register_setting(
            'eurocross-general',
            'eurocross_allow_public_ai',
            [
                'type' => 'boolean',
                'sanitize_callback' => 'eurocross_ai_sanitize_checkbox',
                'default' => false,
            ]
        );
        register_setting(
            'eurocross-general',
            'eurocross_translate_product_titles',
            [
                'type' => 'boolean',
                'sanitize_callback' => 'eurocross_ai_sanitize_checkbox',
                'default' => true,
            ]
        );
        register_setting(
            'eurocross-general',
            'eurocross_translate_product_descriptions',
            [
                'type' => 'boolean',
                'sanitize_callback' => 'eurocross_ai_sanitize_checkbox',
                'default' => true,
            ]
        );
        register_setting(
            'eurocross-general',
            'eurocross_auto_translate_on_save',
            [
                'type' => 'boolean',
                'sanitize_callback' => 'eurocross_ai_sanitize_checkbox',
                'default' => false,
            ]
        );
        register_setting(
            'eurocross-general',
            'eurocross_translate_seo',
            [
                'type' => 'boolean',
                'sanitize_callback' => 'eurocross_ai_sanitize_checkbox',
                'default' => false,
            ]
        );
        register_setting(
            'eurocross-general',
            'eurocross_translate_slug',
            [
                'type' => 'boolean',
                'sanitize_callback' => 'eurocross_ai_sanitize_checkbox',
                'default' => false,
            ]
        );
        register_setting(
            'eurocross-general',
            'eurocross_debug',
            [
                'type' => 'boolean',
                'sanitize_callback' => 'eurocross_ai_sanitize_checkbox',
                'default' => false,
            ]
        );

        add_settings_section('eurocross_api', '', [$this, 'section_api'], 'eurocross-ai-settings');
        add_settings_section('eurocross_translation', '', [$this, 'section_translation'], 'eurocross-ai-settings');
        add_settings_section('eurocross_vat', '', [$this, 'section_vat'], 'eurocross-ai-settings');
        add_settings_section('eurocross_chatbot', '', [$this, 'section_chatbot'], 'eurocross-ai-settings');
        add_settings_section('eurocross_advanced', '', [$this, 'section_advanced'], 'eurocross-ai-settings');

        $fields = [
            'eurocross_api_url' => [__('API URL', 'eurocross-ai'), 'field_api_url', 'eurocross_api'],
            'eurocross_api_key' => [__('API key', 'eurocross-ai'), 'field_api_key', 'eurocross_api'],
            'eurocross_locale' => [__('Default language', 'eurocross-ai'), 'field_locale', 'eurocross_translation'],
            'eurocross_target_languages' => [__('Target languages', 'eurocross-ai'), 'field_target_languages', 'eurocross_translation'],
            'eurocross_plan' => [__('Current plan', 'eurocross-ai'), 'field_plan_summary', 'eurocross_translation'],
            'eurocross_use_oss' => [__('Use OSS', 'eurocross-ai'), 'field_use_oss', 'eurocross_vat'],
            'eurocross_vat_number' => [__('Merchant VAT number', 'eurocross-ai'), 'field_vat_number', 'eurocross_vat'],
            'eurocross_default_country' => [__('Merchant country', 'eurocross-ai'), 'field_default_country', 'eurocross_vat'],
            'eurocross_chatbot_enabled' => [__('Enable chatbot', 'eurocross-ai'), 'field_chatbot_enabled', 'eurocross_chatbot'],
            'eurocross_allow_public_ai' => [__('Allow public AI requests', 'eurocross-ai'), 'field_allow_public_ai', 'eurocross_advanced'],
            'eurocross_translate_product_titles' => [__('Translate product titles', 'eurocross-ai'), 'field_translate_titles', 'eurocross_translation'],
            'eurocross_translate_product_descriptions' => [__('Translate product descriptions', 'eurocross-ai'), 'field_translate_descriptions', 'eurocross_translation'],
            'eurocross_auto_translate_on_save' => [__('Auto-translate on product save', 'eurocross-ai'), 'field_auto_translate_on_save', 'eurocross_translation'],
            'eurocross_translate_seo' => [__('Translate SEO metadata', 'eurocross-ai'), 'field_translate_seo', 'eurocross_translation'],
            'eurocross_translate_slug' => [__('Translate product URL slugs', 'eurocross-ai'), 'field_translate_slug', 'eurocross_translation'],
            'eurocross_debug' => [__('Debug logging', 'eurocross-ai'), 'field_debug', 'eurocross_advanced'],
        ];

        foreach ($fields as $option_name => $field) {
            add_settings_field($option_name, $field[0], [$this, $field[1]], 'eurocross-ai-settings', $field[2]);
        }
    }

    /**
     * Section headings.
     *
     * @return void
     */
    public function section_api()
    {
        echo '<h2 id="eurocross-section-api" class="eurocross-section-title">'.esc_html__('Connection', 'eurocross-ai').'</h2>';
        echo '<p class="description">'.esc_html__('Credentials are stored on the server and are never sent to the browser.', 'eurocross-ai').'</p>';
    }

    /**
     * Translation section heading.
     *
     * @return void
     */
    public function section_translation()
    {
        echo '<h2 id="eurocross-section-translation" class="eurocross-section-title">'.esc_html__('Translation', 'eurocross-ai').'</h2>';
    }

    /**
     * VAT section heading.
     *
     * @return void
     */
    public function section_vat()
    {
        echo '<h2 id="eurocross-section-vat" class="eurocross-section-title">'.esc_html__('VAT and OSS', 'eurocross-ai').'</h2>';
    }

    /**
     * Chatbot section heading.
     *
     * @return void
     */
    public function section_chatbot()
    {
        echo '<h2 id="eurocross-section-chatbot" class="eurocross-section-title">'.esc_html__('Chatbot', 'eurocross-ai').'</h2>';
    }

    /**
     * Advanced section heading.
     *
     * @return void
     */
    public function section_advanced()
    {
        echo '<h2 id="eurocross-section-advanced" class="eurocross-section-title">'.esc_html__('Advanced', 'eurocross-ai').'</h2>';
    }

    /**
     * API URL field.
     *
     * @return void
     */
    public function field_api_url()
    {
        ?>
		<input type="url" name="eurocross_api_url" class="regular-text" value="<?php echo esc_attr(eurocross_ai_get_option('eurocross_api_url')); ?>" autocomplete="off" />
		<p class="description"><?php esc_html_e('Base URL of your EuroCross AI SaaS installation.', 'eurocross-ai'); ?></p>
		<?php
    }

    /**
     * API key field.
     *
     * @return void
     */
    public function field_api_key()
    {
        ?>
		<input type="password" name="eurocross_api_key" class="regular-text" value="<?php echo esc_attr(eurocross_ai_get_option('eurocross_api_key')); ?>" autocomplete="off" />
		<p class="description"><?php esc_html_e('Your SaaS API key. It is used only in server-side requests.', 'eurocross-ai'); ?></p>
		<?php
    }

    /**
     * Default language field.
     *
     * @return void
     */
    public function field_locale()
    {
        $current = eurocross_ai_get_option('eurocross_locale');
        $languages = eurocross_ai_get_languages();
        ?>
		<select name="eurocross_locale" class="regular-select">
		<?php if (empty($languages)) { ?>
			<option value="<?php echo esc_attr($current); ?>"><?php echo esc_html($current); ?></option>
		<?php } else { ?>
			<?php foreach ($languages as $language) { ?>
				<option value="<?php echo esc_attr($language['code']); ?>"<?php selected($current, $language['code']); ?>><?php echo esc_html($language['native_name'] ? $language['native_name'] : $language['name']); ?></option>
			<?php } ?>
		<?php } ?>
		</select>
		<?php if (empty($languages)) { ?>
			<p class="description"><?php esc_html_e('Languages could not be loaded from the API. Save a valid API key first.', 'eurocross-ai'); ?></p>
		<?php } ?>
		<?php
    }

    /**
     * Target languages field.
     *
     * @return void
     */
    public function field_target_languages()
    {
        $languages = eurocross_ai_get_languages();
        $selected = (array) eurocross_ai_get_option('eurocross_target_languages');
        $context = eurocross_ai_get_plan_context();
        $limit = isset($context['limits']['languages']) ? absint($context['limits']['languages']) : count($languages);

        if (empty($languages)) {
            echo '<p class="description">'.esc_html__('Connect your account to load the languages included in your plan.', 'eurocross-ai').'</p>';

            return;
        }
        ?>
		<select name="eurocross_target_languages[]" class="eurocross-language-select" multiple size="<?php echo esc_attr(min(8, max(3, count($languages)))); ?>">
			<?php foreach ($languages as $language) { ?>
				<option value="<?php echo esc_attr($language['code']); ?>"<?php selected(in_array($language['code'], $selected, true), true, true); ?>><?php echo esc_html($language['native_name'] ? $language['native_name'] : $language['name']); ?></option>
			<?php } ?>
		</select>
		<p class="description">
			<?php
            echo esc_html(
                sprintf(
                    /* translators: %d: number of languages included in the plan. */
                    __('Select up to %d languages included in your package. Hold Ctrl/Cmd to choose more than one.', 'eurocross-ai'),
                    $limit > 0 ? $limit : count($languages)
                )
            );
        ?>
		</p>
		<?php
    }

    /**
     * Read-only plan field. The plan is controlled by the SaaS subscription.
     *
     * @return void
     */
    public function field_plan_summary()
    {
        $plan = eurocross_ai_get_option('eurocross_plan');
        $context = eurocross_ai_get_plan_context();
        $limits = isset($context['limits']) && is_array($context['limits']) ? $context['limits'] : [];
        ?>
		<p><strong><?php echo esc_html(ucfirst((string) $plan)); ?></strong></p>
		<p class="description">
			<?php
            echo esc_html(
                sprintf(
                    /* translators: 1: language limit, 2: monthly word limit. */
                    __('Managed by your EuroCross subscription: %1$d languages and %2$s words per month.', 'eurocross-ai'),
                    absint(isset($limits['languages']) ? $limits['languages'] : 0),
                    number_format_i18n(absint(isset($limits['words']) ? $limits['words'] : 0))
                )
            );
        ?>
		</p>
		<?php
    }

    /**
     * OSS checkbox.
     *
     * @return void
     */
    public function field_use_oss()
    {
        ?>
		<label><input type="checkbox" name="eurocross_use_oss" value="1"<?php checked(eurocross_ai_is_enabled('eurocross_use_oss')); ?> /> <?php esc_html_e('Apply the One Stop Shop scheme to VAT calculations.', 'eurocross-ai'); ?></label>
		<?php
    }

    /**
     * Merchant VAT number field.
     *
     * @return void
     */
    public function field_vat_number()
    {
        ?>
		<input type="text" name="eurocross_vat_number" class="regular-text" maxlength="20" value="<?php echo esc_attr(eurocross_ai_get_option('eurocross_vat_number')); ?>" autocomplete="off" />
		<?php
    }

    /**
     * Merchant country field.
     *
     * @return void
     */
    public function field_default_country()
    {
        ?>
		<input type="text" name="eurocross_default_country" class="small-text" maxlength="2" value="<?php echo esc_attr(eurocross_ai_get_option('eurocross_default_country')); ?>" placeholder="DE" />
		<p class="description"><?php esc_html_e('Two-letter country code used as the merchant country for VAT checks.', 'eurocross-ai'); ?></p>
		<?php
    }

    /**
     * Chatbot checkbox.
     *
     * @return void
     */
    public function field_chatbot_enabled()
    {
        ?>
		<label><input type="checkbox" name="eurocross_chatbot_enabled" value="1"<?php checked(eurocross_ai_is_enabled('eurocross_chatbot_enabled')); ?> /> <?php esc_html_e('Enable the [eurocross_chatbot] shortcode and REST chat endpoint.', 'eurocross-ai'); ?></label>
		<?php
    }

    /**
     * Public AI requests checkbox.
     *
     * @return void
     */
    public function field_allow_public_ai()
    {
        ?>
		<label><input type="checkbox" name="eurocross_allow_public_ai" value="1"<?php checked(eurocross_ai_is_enabled('eurocross_allow_public_ai')); ?> /> <?php esc_html_e('Allow public chatbot/AI shortcode requests', 'eurocross-ai'); ?></label>
		<p class="description"><?php esc_html_e('Controls public chatbot/AI shortcode access. Storefront translation requests used by the language switcher are nonce-protected and rate-limited so WooCommerce dynamic content can translate; they still consume your EuroCross plan quota.', 'eurocross-ai'); ?></p>
		<?php
    }

    /**
     * Translate titles checkbox.
     *
     * @return void
     */
    public function field_translate_titles()
    {
        ?>
		<label><input type="checkbox" name="eurocross_translate_product_titles" value="1"<?php checked(eurocross_ai_is_enabled('eurocross_translate_product_titles')); ?> /> <?php esc_html_e('Translate WooCommerce product titles for the active locale.', 'eurocross-ai'); ?></label>
		<?php
    }

    /**
     * Translate descriptions checkbox.
     *
     * @return void
     */
    public function field_translate_descriptions()
    {
        ?>
		<label><input type="checkbox" name="eurocross_translate_product_descriptions" value="1"<?php checked(eurocross_ai_is_enabled('eurocross_translate_product_descriptions')); ?> /> <?php esc_html_e('Translate WooCommerce product descriptions for the active locale.', 'eurocross-ai'); ?></label>
		<?php
    }

    /**
     * Auto-translate on product save checkbox.
     *
     * @return void
     */
    public function field_auto_translate_on_save()
    {
        ?>
		<label><input type="checkbox" name="eurocross_auto_translate_on_save" value="1"<?php checked(eurocross_ai_is_enabled('eurocross_auto_translate_on_save')); ?> /> <?php esc_html_e('Queue translations when a WooCommerce product is created or updated.', 'eurocross-ai'); ?></label>
		<p class="description"><?php esc_html_e('Disabled by default. Requires at least one target language; work is queued in the background and uses your SaaS word quota.', 'eurocross-ai'); ?></p>
		<?php
    }

    /**
     * SEO metadata translation checkbox.
     *
     * @return void
     */
    public function field_translate_seo()
    {
        ?>
		<label><input type="checkbox" name="eurocross_translate_seo" value="1"<?php checked(eurocross_ai_is_enabled('eurocross_translate_seo')); ?> /> <?php esc_html_e('Also queue SEO title and description translations.', 'eurocross-ai'); ?></label>
		<p class="description"><?php esc_html_e('Disabled by default. Completed translations are stored in post meta and applied when Yoast or Rank Math is active.', 'eurocross-ai'); ?></p>
		<?php
    }

    /**
     * Product slug translation checkbox.
     *
     * @return void
     */
    public function field_translate_slug()
    {
        ?>
		<label><input type="checkbox" name="eurocross_translate_slug" value="1"<?php checked(eurocross_ai_is_enabled('eurocross_translate_slug')); ?> /> <?php esc_html_e('Also queue a translated product URL slug.', 'eurocross-ai'); ?></label>
		<p class="description"><?php esc_html_e('Disabled by default. The translated name is converted to a safe slug when it is applied.', 'eurocross-ai'); ?></p>
		<?php
    }

    /**
     * Debug checkbox.
     *
     * @return void
     */
    public function field_debug()
    {
        ?>
		<label><input type="checkbox" name="eurocross_debug" value="1"<?php checked(eurocross_ai_is_enabled('eurocross_debug')); ?> /> <?php esc_html_e('Log redacted API diagnostics to the PHP error log.', 'eurocross-ai'); ?></label>
		<?php
    }

    /**
     * Settings page.
     *
     * @return void
     */
    public function settings_page()
    {
        if (isset($_GET['eurocross_refresh'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            check_admin_referer('eurocross_refresh_settings');

            $payload = eurocross_ai_get_language_payload(true);
            if (eurocross_ai_apply_plan_payload($payload)) {
                $this->flash_and_redirect(__('Plan and languages refreshed from EuroCross.', 'eurocross-ai'));
            }
            $this->flash_and_redirect(__('The plan could not be refreshed. Check the connection and try again.', 'eurocross-ai'), 'error');
        }

        $refresh_url = wp_nonce_url(
            add_query_arg(
                [
                    'page' => 'eurocross-ai-settings',
                    'eurocross_refresh' => '1',
                ],
                admin_url('admin.php')
            ),
            'eurocross_refresh_settings'
        );
        ?>
		<div class="wrap eurocross-ai-page eurocross-settings">
			<h1><?php esc_html_e('EuroCross AI Settings', 'eurocross-ai'); ?></h1>

			<nav class="eurocross-tabs" aria-label="<?php echo esc_attr__('Settings sections', 'eurocross-ai'); ?>">
				<a href="#eurocross-section-api" class="eurocross-tab is-active" data-eurocross-tab="eurocross-section-api"><?php esc_html_e('Connection', 'eurocross-ai'); ?></a>
				<a href="#eurocross-section-translation" class="eurocross-tab" data-eurocross-tab="eurocross-section-translation"><?php esc_html_e('Translation', 'eurocross-ai'); ?></a>
				<a href="#eurocross-section-vat" class="eurocross-tab" data-eurocross-tab="eurocross-section-vat"><?php esc_html_e('VAT and OSS', 'eurocross-ai'); ?></a>
				<a href="#eurocross-section-chatbot" class="eurocross-tab" data-eurocross-tab="eurocross-section-chatbot"><?php esc_html_e('Chatbot', 'eurocross-ai'); ?></a>
				<a href="#eurocross-section-advanced" class="eurocross-tab" data-eurocross-tab="eurocross-section-advanced"><?php esc_html_e('Advanced', 'eurocross-ai'); ?></a>
			</nav>

			<div class="eurocross-settings-layout">
				<div class="eurocross-settings-main">
					<form method="post" action="options.php" class="eurocross-settings-form">
						<?php
                        settings_fields('eurocross-general');
        do_settings_sections('eurocross-ai-settings');
        submit_button(__('Save Changes', 'eurocross-ai'));
        ?>
					</form>
				</div>

				<aside class="eurocross-settings-sidebar">
					<section class="eurocross-card">
						<h2><?php esc_html_e('Connect your account', 'eurocross-ai'); ?></h2>
						<p><?php esc_html_e('Link this site to your EuroCross account. Your purchased plan and its languages are applied automatically.', 'eurocross-ai'); ?></p>
						<a class="button button-primary" href="<?php echo esc_url(eurocross_ai_connect_url()); ?>"><?php esc_html_e('Connect with EuroCross', 'eurocross-ai'); ?></a>
						<p class="description">
							<a href="<?php echo esc_url($refresh_url); ?>"><?php esc_html_e('Refresh plan and languages', 'eurocross-ai'); ?></a>
						</p>
					</section>

					<section class="eurocross-card">
						<h2><?php esc_html_e('Connection test', 'eurocross-ai'); ?></h2>
						<p><?php esc_html_e('Check that the saved API key can reach the SaaS API.', 'eurocross-ai'); ?></p>
						<button type="button" class="button button-secondary" data-eurocross-test-connection><?php esc_html_e('Test connection', 'eurocross-ai'); ?></button>
						<p class="eurocross-ajax-status" data-eurocross-connection-status aria-live="polite"></p>
					</section>

					<section class="eurocross-card">
						<h2><?php esc_html_e('Manage subscription', 'eurocross-ai'); ?></h2>
						<p><?php esc_html_e('Upgrade, downgrade or cancel from your EuroCross dashboard. Changes sync back to this site.', 'eurocross-ai'); ?></p>
						<a class="button button-secondary" href="<?php echo esc_url('https://eurocross-ai.kadetech.online/dashboard/billing'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open billing', 'eurocross-ai'); ?></a>
					</section>
				</aside>
			</div>
		</div>
		<?php
    }

    /**
     * Dashboard page backed by real API data.
     *
     * @return void
     */
    public function dashboard_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $force = false;
        if (isset($_GET['eurocross_refresh'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            check_admin_referer('eurocross_refresh_dashboard');
            $force = true;
        }

        $api_key = trim((string) eurocross_ai_get_option('eurocross_api_key'));
        $dashboard = eurocross_ai_get_dashboard_data($force);
        $refresh_url = wp_nonce_url(
            add_query_arg(
                [
                    'page' => 'eurocross-ai-dashboard',
                    'eurocross_refresh' => '1',
                ],
                admin_url('admin.php')
            ),
            'eurocross_refresh_dashboard'
        );
        ?>
		<div class="wrap eurocross-ai-page eurocross-dashboard">
			<h1><?php esc_html_e('EuroCross AI Dashboard', 'eurocross-ai'); ?></h1>

			<div class="eurocross-dashboard-toolbar">
				<a class="button button-secondary" href="<?php echo esc_url($refresh_url); ?>" data-eurocross-refresh><?php esc_html_e('Refresh data', 'eurocross-ai'); ?></a>
				<span class="eurocross-ajax-status" data-eurocross-refresh-status aria-live="polite"></span>
			</div>

			<div class="eurocross-loading" data-eurocross-loading hidden>
				<span class="eurocross-spinner" aria-hidden="true"></span>
				<?php esc_html_e('Loading live data…', 'eurocross-ai'); ?>
			</div>

			<?php if ($api_key === '') { ?>
				<div class="eurocross-empty-state">
					<h2><?php esc_html_e('Connect your account', 'eurocross-ai'); ?></h2>
					<p><?php esc_html_e('Add your SaaS API key on the settings page to load live data.', 'eurocross-ai'); ?></p>
					<a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=eurocross-ai-settings')); ?>"><?php esc_html_e('Open settings', 'eurocross-ai'); ?></a>
				</div>
			<?php } elseif (! empty($dashboard['errors']) && empty($dashboard['health']) && empty($dashboard['chatbot']) && empty($dashboard['languages'])) { ?>
				<div class="eurocross-error-state" role="alert">
					<h2><?php esc_html_e('The API could not be reached', 'eurocross-ai'); ?></h2>
					<ul>
						<?php foreach ($dashboard['errors'] as $error_message) { ?>
							<li><?php echo esc_html(sanitize_text_field((string) $error_message)); ?></li>
						<?php } ?>
					</ul>
				</div>
			<?php } else { ?>
				<div class="eurocross-stat-grid">
					<section class="eurocross-card eurocross-stat-card">
						<h2><?php esc_html_e('API status', 'eurocross-ai'); ?></h2>
						<?php
                        $status = isset($dashboard['health']['status']) ? $dashboard['health']['status'] : '';
			    if ($status === '') {
			        echo '<p class="eurocross-empty">'.esc_html__('No status was returned by the API.', 'eurocross-ai').'</p>';
			    } else {
			        echo '<p class="eurocross-stat-value">'.eurocross_ai_render_api_value($status).'</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			    }
			    ?>
					</section>

					<section class="eurocross-card eurocross-stat-card">
						<h2><?php esc_html_e('Available languages', 'eurocross-ai'); ?></h2>
						<?php if (empty($dashboard['languages'])) { ?>
							<p class="eurocross-empty"><?php esc_html_e('No languages were returned by the API.', 'eurocross-ai'); ?></p>
						<?php } else { ?>
							<p class="eurocross-stat-value"><?php echo esc_html((string) count($dashboard['languages'])); ?></p>
							<p class="eurocross-stat-note"><?php echo esc_html(implode(', ', wp_list_pluck($dashboard['languages'], 'code'))); ?></p>
						<?php } ?>
					</section>

					<section class="eurocross-card eurocross-stat-card">
						<h2><?php esc_html_e('Chatbot', 'eurocross-ai'); ?></h2>
						<?php
			            $chatbot_data = isset($dashboard['chatbot']['data']) && is_array($dashboard['chatbot']['data']) ? $dashboard['chatbot']['data'] : [];
			    $is_configured = ! empty($chatbot_data['is_configured']);
			    $is_included = ! empty($chatbot_data['included']);
			    $plan_name = isset($chatbot_data['plan']) ? sanitize_text_field((string) $chatbot_data['plan']) : '';
			    $rate_limit = isset($chatbot_data['rate_limit']) ? absint($chatbot_data['rate_limit']) : 0;
			    $remaining = isset($chatbot_data['remaining']) ? absint($chatbot_data['remaining']) : 0;
			    ?>
						<dl class="eurocross-data-list">
							<dt><?php esc_html_e('Configured', 'eurocross-ai'); ?></dt>
							<dd><?php echo $is_configured ? esc_html__('Yes', 'eurocross-ai') : esc_html__('No', 'eurocross-ai'); ?></dd>
							<dt><?php esc_html_e('Included in plan', 'eurocross-ai'); ?></dt>
							<dd><?php echo $is_included ? esc_html__('Yes', 'eurocross-ai') : esc_html__('No', 'eurocross-ai'); ?></dd>
							<?php if ($plan_name !== '') { ?>
								<dt><?php esc_html_e('Plan', 'eurocross-ai'); ?></dt>
								<dd><?php echo esc_html(ucfirst($plan_name)); ?></dd>
							<?php } ?>
							<?php if ($rate_limit > 0) { ?>
								<dt><?php esc_html_e('Rate limit', 'eurocross-ai'); ?></dt>
								<dd><?php echo esc_html((string) $remaining.' / '.(string) $rate_limit); ?></dd>
							<?php } ?>
						</dl>
					</section>

					<section class="eurocross-card eurocross-stat-card">
						<h2><?php esc_html_e('Account', 'eurocross-ai'); ?></h2>
						<?php
			            if (! empty($dashboard['errors']['account'])) {
			                echo '<p class="eurocross-empty">'.esc_html(sanitize_text_field((string) $dashboard['errors']['account'])).'</p>';
			            } elseif (! empty($dashboard['account'])) {
			                $account_response = isset($dashboard['account']['data']) && is_array($dashboard['account']['data']) ? $dashboard['account']['data'] : $dashboard['account'];
			                $account = isset($account_response['account']) && is_array($account_response['account']) ? $account_response['account'] : $account_response;
			                $usage = isset($account_response['usage']) && is_array($account_response['usage']) ? $account_response['usage'] : [];
			                $providers = isset($account_response['providers']) && is_array($account_response['providers']) ? $account_response['providers'] : [];
			                $trial = isset($account['trial']) && is_array($account['trial']) ? $account['trial'] : [];
			                ?>
							<dl class="eurocross-data-list">
								<dt><?php esc_html_e('Plan', 'eurocross-ai'); ?></dt>
								<dd><?php echo esc_html((string) ($account['plan_name'] ?? $account['plan'] ?? '—')); ?></dd>
								<dt><?php esc_html_e('Status', 'eurocross-ai'); ?></dt>
								<dd><span class="eurocross-badge eurocross-badge--<?php echo esc_attr(sanitize_key((string) ($account['status'] ?? 'unknown'))); ?>"><?php echo esc_html(ucfirst((string) ($account['status'] ?? 'unknown'))); ?></span></dd>
								<?php if (! empty($account['access_reason'])) { ?>
									<dt><?php esc_html_e('Access', 'eurocross-ai'); ?></dt>
									<dd><?php echo esc_html((string) $account['access_reason']); ?></dd>
								<?php } ?>
								<dt><?php esc_html_e('Trial active', 'eurocross-ai'); ?></dt>
								<dd><?php echo ! empty($trial['active']) ? esc_html__('Yes', 'eurocross-ai') : esc_html__('No', 'eurocross-ai'); ?></dd>
								<?php if (! empty($trial['expires_at'])) { ?>
									<dt><?php esc_html_e('Trial expires', 'eurocross-ai'); ?></dt>
									<dd><?php echo esc_html((string) $trial['expires_at']); ?></dd>
								<?php } ?>
								<?php if (! empty($trial['grace_ends_at'])) { ?>
									<dt><?php esc_html_e('Grace ends', 'eurocross-ai'); ?></dt>
									<dd><?php echo esc_html((string) $trial['grace_ends_at']); ?></dd>
								<?php } ?>
								<?php if (! empty($usage)) { ?>
									<dt><?php esc_html_e('Words this month', 'eurocross-ai'); ?></dt>
									<dd><?php echo esc_html(number_format_i18n((int) ($usage['words_this_month'] ?? 0))); ?></dd>
									<dt><?php esc_html_e('Remaining words', 'eurocross-ai'); ?></dt>
									<dd><?php echo esc_html(number_format_i18n((int) ($usage['remaining_words'] ?? 0))); ?></dd>
									<dt><?php esc_html_e('Usage', 'eurocross-ai'); ?></dt>
									<dd>
										<div class="eurocross-usage-bar"><span style="width:<?php echo esc_attr(min(100, max(0, (float) ($usage['usage_percentage'] ?? 0)))); ?>%"></span></div>
										<span class="eurocross-stat-note"><?php echo esc_html((string) ($usage['usage_percentage'] ?? 0).'%'); ?></span>
									</dd>
								<?php } ?>
								<?php if (! empty($providers)) { ?>
									<dt><?php esc_html_e('AI provider', 'eurocross-ai'); ?></dt>
									<dd><?php echo esc_html((string) ($providers['primary'] ?? 'unknown')); ?></dd>
								<?php } ?>
							</dl>
							<?php
			            } else {
			                echo '<p class="eurocross-empty">'.esc_html__('No account data available.', 'eurocross-ai').'</p>';
			            }
			    ?>
					</section>
				</div>

				<?php
                $saas_base = untrailingslashit((string) eurocross_ai_get_option('eurocross_api_url'));
			    if ($saas_base === '') {
			        $saas_base = 'https://eurocross-ai.kadetech.online';
			    }
			    ?>
				<section class="eurocross-card" style="margin-top:16px;">
					<h2><?php esc_html_e('Manage your EuroCross account', 'eurocross-ai'); ?></h2>
					<p class="description"><?php esc_html_e('Billing, API keys, plan changes, and account settings are managed securely in your EuroCross dashboard.', 'eurocross-ai'); ?></p>
					<p style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">
						<a class="button button-primary" href="<?php echo esc_url($saas_base.'/dashboard'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open EuroCross Dashboard', 'eurocross-ai'); ?></a>
						<a class="button button-secondary" href="<?php echo esc_url($saas_base.'/dashboard/keys'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Manage API Keys', 'eurocross-ai'); ?></a>
						<a class="button button-secondary" href="<?php echo esc_url($saas_base.'/dashboard/billing'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Billing', 'eurocross-ai'); ?></a>
					</p>
				</section>

				<?php if (! empty($dashboard['errors'])) { ?>
					<div class="notice notice-warning inline" role="status">
						<?php foreach ($dashboard['errors'] as $error_message) { ?>
							<p><?php echo esc_html(sanitize_text_field((string) $error_message)); ?></p>
						<?php } ?>
					</div>
				<?php } ?>
			<?php } ?>
		</div>
		<?php
    }

    /**
     * Translation management page backed by the persisted SaaS records.
     *
     * @return void
     */
    public function translations_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $languages = eurocross_ai_get_languages();
        ?>
		<div class="wrap eurocross-ai-page eurocross-translations" data-eurocross-translations>
			<h1><?php esc_html_e('Translations', 'eurocross-ai'); ?></h1>
			<p class="description"><?php esc_html_e('Review, edit and retry translations stored by your EuroCross AI account. All figures and records below come from the API.', 'eurocross-ai'); ?></p>

			<p class="eurocross-ajax-status" data-eurocross-translation-status aria-live="polite"></p>

			<section class="eurocross-stat-grid" data-eurocross-translation-summary>
				<p class="eurocross-empty"><?php esc_html_e('Loading translation summary…', 'eurocross-ai'); ?></p>
			</section>

			<section class="eurocross-card eurocross-translation-filters">
				<form data-eurocross-translation-filter-form>
					<div class="eurocross-translation-filter-grid">
						<label>
							<span><?php esc_html_e('Status', 'eurocross-ai'); ?></span>
							<select name="status">
								<option value=""><?php esc_html_e('All statuses', 'eurocross-ai'); ?></option>
								<option value="pending"><?php esc_html_e('Pending', 'eurocross-ai'); ?></option>
								<option value="processing"><?php esc_html_e('Processing', 'eurocross-ai'); ?></option>
								<option value="completed"><?php esc_html_e('Completed', 'eurocross-ai'); ?></option>
								<option value="failed"><?php esc_html_e('Failed', 'eurocross-ai'); ?></option>
							</select>
						</label>
						<label>
							<span><?php esc_html_e('Target language', 'eurocross-ai'); ?></span>
							<select name="target_lang">
								<option value=""><?php esc_html_e('All languages', 'eurocross-ai'); ?></option>
								<?php foreach ($languages as $language) { ?>
									<option value="<?php echo esc_attr($language['code']); ?>"><?php echo esc_html(! empty($language['native_name']) ? $language['native_name'] : $language['name']); ?></option>
								<?php } ?>
							</select>
						</label>
						<label>
							<span><?php esc_html_e('Source type', 'eurocross-ai'); ?></span>
							<input type="text" name="source_type" maxlength="64" placeholder="<?php echo esc_attr__('Any', 'eurocross-ai'); ?>" />
						</label>
						<label>
							<span><?php esc_html_e('Rows per page', 'eurocross-ai'); ?></span>
							<select name="per_page">
								<option value="20">20</option>
								<option value="50">50</option>
								<option value="100">100</option>
							</select>
						</label>
					</div>
					<p class="submit">
						<button type="submit" class="button button-primary"><?php esc_html_e('Apply filters', 'eurocross-ai'); ?></button>
					</p>
				</form>
			</section>

			<section class="eurocross-card eurocross-translation-list">
				<div class="eurocross-translation-toolbar">
					<label>
						<span><?php esc_html_e('Bulk action', 'eurocross-ai'); ?></span>
						<select data-eurocross-bulk-action>
							<option value=""><?php esc_html_e('Choose an action', 'eurocross-ai'); ?></option>
							<option value="retry"><?php esc_html_e('Retry selected', 'eurocross-ai'); ?></option>
							<option value="queue"><?php esc_html_e('Queue selected again', 'eurocross-ai'); ?></option>
						</select>
					</label>
					<button type="button" class="button" data-eurocross-bulk-apply><?php esc_html_e('Apply', 'eurocross-ai'); ?></button>
					<span class="eurocross-translation-selected" data-eurocross-selected-count></span>
				</div>

				<table class="widefat striped eurocross-translation-table">
					<thead>
						<tr>
							<th scope="col"><input type="checkbox" data-eurocross-select-all aria-label="<?php echo esc_attr__('Select all rows', 'eurocross-ai'); ?>" /></th>
							<th scope="col"><?php esc_html_e('ID', 'eurocross-ai'); ?></th>
							<th scope="col"><?php esc_html_e('Source', 'eurocross-ai'); ?></th>
							<th scope="col"><?php esc_html_e('Field', 'eurocross-ai'); ?></th>
							<th scope="col"><?php esc_html_e('Languages', 'eurocross-ai'); ?></th>
							<th scope="col"><?php esc_html_e('Status', 'eurocross-ai'); ?></th>
							<th scope="col"><?php esc_html_e('Words', 'eurocross-ai'); ?></th>
							<th scope="col"><?php esc_html_e('Translation', 'eurocross-ai'); ?></th>
							<th scope="col"><?php esc_html_e('Actions', 'eurocross-ai'); ?></th>
						</tr>
					</thead>
					<tbody data-eurocross-translation-rows>
						<tr><td colspan="9"><?php esc_html_e('Loading translations…', 'eurocross-ai'); ?></td></tr>
					</tbody>
				</table>

				<div class="eurocross-translation-pagination">
					<span data-eurocross-translation-page></span>
					<button type="button" class="button" data-eurocross-page-prev><?php esc_html_e('Previous', 'eurocross-ai'); ?></button>
					<button type="button" class="button" data-eurocross-page-next><?php esc_html_e('Next', 'eurocross-ai'); ?></button>
				</div>
			</section>

			<div class="eurocross-modal" data-eurocross-edit-modal hidden>
				<div class="eurocross-modal-backdrop" data-eurocross-edit-close></div>
				<div class="eurocross-modal-content" role="dialog" aria-modal="true" aria-labelledby="eurocross-edit-title">
					<h2 id="eurocross-edit-title"><?php esc_html_e('Edit translation', 'eurocross-ai'); ?></h2>
					<p class="eurocross-translation-source" data-eurocross-edit-source></p>
					<textarea rows="6" maxlength="10000" data-eurocross-edit-text></textarea>
					<p class="eurocross-ajax-status" data-eurocross-edit-status aria-live="polite"></p>
					<p class="eurocross-modal-actions">
						<button type="button" class="button button-primary" data-eurocross-edit-save><?php esc_html_e('Save translation', 'eurocross-ai'); ?></button>
						<button type="button" class="button" data-eurocross-edit-close><?php esc_html_e('Cancel', 'eurocross-ai'); ?></button>
					</p>
				</div>
			</div>
		</div>
		<?php
    }

    /**
     * Information page.
     *
     * @return void
     */
    public function info_page()
    {
        ?>
		<div class="wrap eurocross-ai-page eurocross-info">
			<h1><?php esc_html_e('About EuroCross AI', 'eurocross-ai'); ?></h1>

			<div class="eurocross-info-grid">
				<section class="eurocross-card">
					<h2><?php esc_html_e('What this plugin does', 'eurocross-ai'); ?></h2>
					<p><?php esc_html_e('EuroCross AI sends translation, VAT and chatbot requests to your EuroCross AI SaaS account from the server, then renders the results in your store.', 'eurocross-ai'); ?></p>
				</section>

				<section class="eurocross-card">
					<h2><?php esc_html_e('Shortcodes', 'eurocross-ai'); ?></h2>
					<p><code>[eurocross_language_switcher]</code> &mdash; <?php esc_html_e('renders the language selector returned by the API.', 'eurocross-ai'); ?></p>
					<p><code>[eurocross_chatbot]</code> &mdash; <?php esc_html_e('renders the chatbot when the chatbot option is enabled.', 'eurocross-ai'); ?></p>
				</section>

				<section class="eurocross-card">
					<h2><?php esc_html_e('Privacy', 'eurocross-ai'); ?></h2>
					<p><?php esc_html_e('Text submitted for translation, VAT numbers and chat messages are sent to the configured external SaaS API. The API key is never exposed to site visitors. Cached translations are stored locally and removed when the plugin is uninstalled.', 'eurocross-ai'); ?></p>
				</section>

				<section class="eurocross-card">
					<h2><?php esc_html_e('Support', 'eurocross-ai'); ?></h2>
					<p><a href="<?php echo esc_url('https://eurocross.ai/support'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Visit the support site', 'eurocross-ai'); ?></a></p>
				</section>
			</div>
		</div>
		<?php
    }

    /**
     * Enqueue admin assets on plugin screens only.
     *
     * @param  string  $hook_suffix  Current admin page hook suffix.
     * @return void
     */
    public function enqueue_admin_assets($hook_suffix)
    {
        if (strpos((string) $hook_suffix, 'eurocross') === false) {
            return;
        }

        wp_enqueue_style('eurocross-ai-admin', EUROCROSS_AI_PLUGIN_URL.'assets/admin.css', [], EUROCROSS_AI_VERSION);
        wp_enqueue_script('eurocross-ai-admin', EUROCROSS_AI_PLUGIN_URL.'assets/admin.js', [], EUROCROSS_AI_VERSION, true);

        wp_localize_script(
            'eurocross-ai-admin',
            'EuroCrossAdminData',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'restUrl' => esc_url_raw(rest_url('eurocross/v1')),
                'nonce' => wp_create_nonce('eurocross_admin_action'),
                'nonceTrial' => wp_create_nonce('eurocross_start_trial'),
                'nonceCheckout' => wp_create_nonce('eurocross_checkout'),
                'wpRestNonce' => wp_create_nonce('wp_rest'),
                'strings' => [
                    'testing' => __('Testing connection…', 'eurocross-ai'),
                    'connected' => __('Connection successful.', 'eurocross-ai'),
                    'failed' => __('Connection failed.', 'eurocross-ai'),
                    'processing' => __('Processing…', 'eurocross-ai'),
                    'trialDone' => __('Trial started.', 'eurocross-ai'),
                    'redirect' => __('Opening checkout…', 'eurocross-ai'),
                    'refreshing' => __('Loading…', 'eurocross-ai'),
                    'error' => __('The request failed. Please try again.', 'eurocross-ai'),
                    'loadingSummary' => __('Loading translation summary…', 'eurocross-ai'),
                    'loadingTranslations' => __('Loading translations…', 'eurocross-ai'),
                    'noTranslations' => __('No translations match these filters.', 'eurocross-ai'),
                    'loadError' => __('Translations could not be loaded.', 'eurocross-ai'),
                    'summaryError' => __('The translation summary could not be loaded.', 'eurocross-ai'),
                    'saved' => __('Translation saved.', 'eurocross-ai'),
                    'retried' => __('Translation queued for retry.', 'eurocross-ai'),
                    'queued' => __('Translations queued.', 'eurocross-ai'),
                    'bulkRetried' => __('Selected translations queued for retry.', 'eurocross-ai'),
                    'selectAction' => __('Choose a bulk action first.', 'eurocross-ai'),
                    'selectRows' => __('Select at least one translation.', 'eurocross-ai'),
                    'saving' => __('Saving translation…', 'eurocross-ai'),
                    'saveFailed' => __('The translation could not be saved.', 'eurocross-ai'),
                    'edit' => __('Edit', 'eurocross-ai'),
                    'retry' => __('Retry', 'eurocross-ai'),
                    'manual' => __('Manual', 'eurocross-ai'),
                    'yes' => __('Yes', 'eurocross-ai'),
                    'no' => __('No', 'eurocross-ai'),
                    'page' => __('Page', 'eurocross-ai'),
                    'of' => __('of', 'eurocross-ai'),
                    'totalTranslations' => __('Total translations', 'eurocross-ai'),
                    'totalWords' => __('Words translated', 'eurocross-ai'),
                    'failedTranslations' => __('Failed translations', 'eurocross-ai'),
                    'manualEdits' => __('Manual edits', 'eurocross-ai'),
                    'quotaRemaining' => __('Words remaining', 'eurocross-ai'),
                    'recentTranslations' => __('Recent translations', 'eurocross-ai'),
                    'batches' => __('Batches', 'eurocross-ai'),
                ],
            ]
        );
    }

    /**
     * Enqueue frontend assets and localized public configuration.
     *
     * @return void
     */
    public function enqueue_frontend_assets()
    {
        wp_enqueue_style('eurocross-ai-frontend', EUROCROSS_AI_PLUGIN_URL.'assets/frontend.css', [], EUROCROSS_AI_VERSION);
        wp_enqueue_script('eurocross-ai-frontend', EUROCROSS_AI_PLUGIN_URL.'assets/frontend.js', [], EUROCROSS_AI_VERSION, true);

        $source = $this->get_source_locale();
        $target = eurocross_ai_get_current_locale();

        $strings = [
            'loading' => __('Loading…', 'eurocross-ai'),
            'error' => __('Something went wrong. Please try again.', 'eurocross-ai'),
            'translate' => __('Translate', 'eurocross-ai'),
            'translated' => __('Translation complete.', 'eurocross-ai'),
            'send' => __('Send', 'eurocross-ai'),
            'thinking' => __('Thinking…', 'eurocross-ai'),
            'vatChecking' => __('Checking VAT number…', 'eurocross-ai'),
            'vatAvailable' => __('VAT treatment updated.', 'eurocross-ai'),
            'you' => __('You', 'eurocross-ai'),
            'assistant' => __('Assistant', 'eurocross-ai'),
            'reverseCharge' => __('Reverse charge applies.', 'eurocross-ai'),
            'vatCharged' => __('VAT is charged at checkout.', 'eurocross-ai'),
            'rate' => __('Rate', 'eurocross-ai'),
            'language' => __('Language', 'eurocross-ai'),
        ];

        $wcPatches = [];
        if ($target !== '' && $target !== $source) {
            foreach ($strings as $key => $value) {
                $strings[$key] = eurocross_ai_translate_text((string) $value, $source, $target, 'frontend_text');
            }

            $wcSources = [
                'Subtotal', 'Total', 'Shipping', 'Tax', 'Discount', 'Order total', 'Place order',
                'Proceed to Checkout', 'Add to cart', 'View cart', 'Cart', 'Checkout', 'My account',
                'Your cart is currently empty!', 'Browse store', 'New in store', 'Remove item',
                'Apply', 'Coupon code', 'Coupon code or gift card', 'Product', 'Price', 'Quantity',
                'Order summary', 'Payment options', 'Billing address', 'Shipping address', 'Contact information',
                'Add a note to your order', 'Free shipping', 'Local pickup', 'Shipping options',
            ];
            foreach ($wcSources as $wcText) {
                $translated = eurocross_ai_translate_text($wcText, $source, $target, 'frontend_text');
                if (is_string($translated) && $translated !== '' && $translated !== $wcText) {
                    $wcPatches[$wcText] = $translated;
                }
            }
        }

        wp_localize_script(
            'eurocross-ai-frontend',
            'EuroCrossData',
            [
                'restUrl' => esc_url_raw(rest_url('eurocross/v1')),
                'nonce' => wp_create_nonce('wp_rest'),
                'locale' => $target,
                'sourceLocale' => $source,
                'languages' => eurocross_ai_get_languages(),
                'canEdit' => current_user_can('manage_options') ? true : false,
                'chatbotEnabled' => eurocross_ai_is_enabled('eurocross_chatbot_enabled') ? true : false,
                'strings' => $strings,
                'wcPatches' => $wcPatches,
            ]
        );
    }

    /**
     * Register the language switcher widget.
     *
     * @return void
     */
    public function register_widget()
    {
        if (class_exists('WP_Widget')) {
            register_widget('EuroCross_AI_Language_Switcher_Widget');
        }
    }

    /**
     * Language switcher shortcode.
     *
     * @param  array|string  $atts  Shortcode attributes.
     * @return string
     */
    public function language_switcher_shortcode($atts)
    {
        $atts = shortcode_atts(
            [
                'label' => 'yes',
                'class' => '',
            ],
            $atts,
            'eurocross_language_switcher'
        );

        return eurocross_ai_language_switcher_markup(
            [
                'show_label' => in_array(strtolower((string) $atts['label']), ['yes', '1', 'true'], true),
                'class' => sanitize_html_class((string) $atts['class']),
            ]
        );
    }

    /**
     * Chatbot shortcode.
     *
     * The chatbot is rendered as a floating site-wide widget in the footer.
     * Returning an empty string avoids duplicate widgets when the shortcode is used.
     *
     * @param  array|string  $atts  Shortcode attributes.
     * @return string
     */
    public function chatbot_shortcode($atts)
    {
        return '';
    }

    /**
     * Whether frontend content should be translated for the current request.
     *
     * @return bool
     */
    private function should_translate_frontend()
    {
        if (is_admin() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }

        if (wp_doing_ajax() && ! $this->is_safe_ajax_translation_request()) {
            return false;
        }

        if (is_feed() || is_robots() || is_trackback()) {
            return false;
        }

        $current = eurocross_ai_get_current_locale();
        $source = $this->get_source_locale();

        return $current !== '' && $source !== '' && $current !== $source;
    }

    /**
     * Allow server-side translation only for safe WooCommerce fragment AJAX requests.
     */
    private function is_safe_ajax_translation_request(): bool
    {
        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request type check.
        $wc_ajax = isset($_REQUEST['wc-ajax']) ? sanitize_key(wp_unslash($_REQUEST['wc-ajax'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request type check.

        return in_array($action, ['woocommerce_get_refreshed_fragments', 'woocommerce_add_to_cart', 'woocommerce_remove_from_cart'], true)
            || in_array($wc_ajax, ['get_refreshed_fragments', 'apply_coupon', 'remove_coupon', 'update_shipping_method'], true);
    }

    /**
     * Get the site's configured source/default locale.
     *
     * @return string
     */
    private function get_source_locale()
    {
        $locale = eurocross_ai_sanitize_language_code(eurocross_ai_get_option('eurocross_locale'));

        return $locale !== '' ? $locale : 'en';
    }

    /**
     * Return the current request URL with a specific language parameter.
     *
     * @param  string  $lang  Language code, or empty for default/x-default.
     * @return string
     */
    private function get_localized_current_url($lang = '')
    {
        $scheme = is_ssl() ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : wp_parse_url(home_url(), PHP_URL_HOST);
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
        $url = $scheme.'://'.$host.$request_uri;
        $url = remove_query_arg(['eurocross_lang', 'lang'], $url);

        $lang = eurocross_ai_sanitize_language_code($lang);
        $source = $this->get_source_locale();

        if ($lang !== '' && $lang !== $source) {
            $url = add_query_arg('eurocross_lang', $lang, $url);
        }

        return $url;
    }

    /**
     * Append the active language parameter to internal URLs.
     *
     * @param  string  $url  URL.
     * @return string
     */
    public function propagate_language_url($url)
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || ! is_string($url) || $url === '') {
            return $url;
        }

        $current = eurocross_ai_get_current_locale();
        $source = $this->get_source_locale();
        if ($current === '' || $current === $source) {
            return $url;
        }

        static $home_host = null;
        if ($home_host === null) {
            $home_host = wp_parse_url((string) get_option('home'), PHP_URL_HOST);
            if (! $home_host) {
                $home_host = wp_parse_url((string) get_option('siteurl'), PHP_URL_HOST);
            }
        }
        $url_host = wp_parse_url($url, PHP_URL_HOST);
        if ($url_host && $home_host && strcasecmp($url_host, $home_host) !== 0) {
            return $url;
        }

        if (strpos($url, 'wp-admin') !== false || strpos($url, 'wp-login.php') !== false) {
            return $url;
        }

        return add_query_arg('eurocross_lang', $current, $url);
    }

    /**
     * Output hreflang alternate links for available languages.
     *
     * @return void
     */
    public function output_hreflang_tags()
    {
        if (is_admin() || is_feed() || is_robots() || is_search() || is_404()) {
            return;
        }

        $languages = eurocross_ai_get_languages();
        if (empty($languages)) {
            return;
        }

        $source = $this->get_source_locale();
        $seen = [];

        foreach ($languages as $language) {
            $code = isset($language['code']) ? eurocross_ai_sanitize_language_code($language['code']) : '';
            if ($code === '' || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $href = $this->get_localized_current_url($code);
            echo '<link rel="alternate" hreflang="'.esc_attr($code).'" href="'.esc_url($href).'" />'."\n";
        }

        $default_href = $this->get_localized_current_url($source);
        echo '<link rel="alternate" hreflang="x-default" href="'.esc_url($default_href).'" />'."\n";
    }

    /**
     * Reflect the active locale in the HTML lang attribute.
     *
     * @param  string  $output  Language attributes.
     * @return string
     */
    public function filter_language_attributes($output)
    {
        $current = eurocross_ai_get_current_locale();
        if ($current === '') {
            return $output;
        }

        $lang = str_replace('_', '-', $current);
        if (preg_match('/lang="[^"]*"/', $output)) {
            return preg_replace('/lang="[^"]*"/', 'lang="'.esc_attr($lang).'"', $output);
        }

        return $output.' lang="'.esc_attr($lang).'"';
    }

    /**
     * Translate rendered block output for frontend website details.
     *
     * @param  string  $block_content  Block HTML.
     * @param  array  $block  Parsed block.
     * @return string
     */
    public function translate_rendered_block($block_content, $block)
    {
        if (! $this->should_translate_frontend() || ! is_string($block_content) || trim($block_content) === '') {
            return $block_content;
        }

        $name = isset($block['blockName']) ? (string) $block['blockName'] : '';
        $translatable_blocks = [
            'core/page-list',
            'core/navigation-link',
            'core/navigation-submenu',
            'core/home-link',
            'core/site-title',
            'core/site-tagline',
            'core/query-title',
            'core/post-title',
            'core/post-excerpt',
            'core/post-author-name',
            'core/post-date',
            'core/post-terms',
            'core/post-navigation-link',
            'core/query-pagination-next',
            'core/query-pagination-previous',
            'core/query-no-results',
            'core/read-more',
            'core/loginout',
            'core/search',
            'core/heading',
            'core/paragraph',
            'core/button',
            'core/list-item',
            'core/table',
            'core/quote',
            'core/pullquote',
            'core/verse',
            'core/preformatted',
            'core/details',
            'woocommerce/mini-cart',
            'woocommerce/breadcrumbs',
            'woocommerce/product-categories',
            'woocommerce/customer-account',
            'woocommerce/mini-cart-title-block',
            'woocommerce/mini-cart-items-block',
            'woocommerce/empty-mini-cart-contents-block',
            'woocommerce/filled-mini-cart-contents-block',
            'woocommerce/mini-cart-shopping-button-block',
            'woocommerce/mini-cart-cart-button-block',
            'woocommerce/mini-cart-checkout-button-block',
            'woocommerce/mini-cart-footer-block',
            'woocommerce/product-button',
        ];

        if (in_array($name, $translatable_blocks, true)) {
            $text_length = strlen(wp_strip_all_tags($block_content));
            if ($text_length > 0 && $text_length <= 12000) {
                return $this->translate_block_html($block_content, 'block_'.str_replace('/', '_', $name));
            }
        }

        return $block_content;
    }

    /**
     * Translate HTML block content while preserving tags.
     *
     * @param  string  $html  HTML content.
     * @param  string  $context  Translation context.
     * @return string
     */
    private function translate_block_html($html, $context)
    {
        if (! is_string($html) || trim(wp_strip_all_tags($html)) === '') {
            return $html;
        }

        $source = $this->get_source_locale();
        $target = eurocross_ai_get_current_locale();

        if (class_exists('DOMDocument')) {
            $previous = libxml_use_internal_errors(true);
            $document = new DOMDocument('1.0', 'UTF-8');
            $wrapped = '<?xml encoding="utf-8" ?><div id="eurocross-root">'.$html.'</div>';
            $loaded = $document->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            if ($loaded) {
                $root = $document->getElementById('eurocross-root');
                if ($root instanceof DOMNode) {
                    $xpath = new DOMXPath($document);
                    $nodes = $xpath->query('.//text()', $root);
                    $changed = false;

                    if ($nodes instanceof DOMNodeList) {
                        foreach ($nodes as $node) {
                            if (! $node instanceof DOMText) {
                                continue;
                            }

                            $original = $node->nodeValue;
                            if (! is_string($original)) {
                                continue;
                            }

                            if (trim($original) === '' || ! preg_match('/[\p{L}\p{N}]/u', $original)) {
                                continue;
                            }

                            $translated = eurocross_ai_translate_text($original, $source, $target, $context);
                            if (is_string($translated) && $translated !== '' && $translated !== $original) {
                                $node->nodeValue = $translated;
                                $changed = true;
                            }
                        }
                    }

                    if ($changed) {
                        $result = '';
                        foreach ($root->childNodes as $child) {
                            $result .= $document->saveHTML($child);
                        }

                        libxml_clear_errors();
                        libxml_use_internal_errors($previous);

                        return $result !== '' ? $result : $html;
                    }
                }
            }

            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return eurocross_ai_translate_text($html, $source, $target, $context);
    }

    /**
     * Translate archive titles on the frontend.
     *
     * @param  string  $title  Archive title.
     * @return string
     */
    public function translate_archive_title($title)
    {
        if (! $this->should_translate_frontend() || ! is_string($title) || trim($title) === '') {
            return $title;
        }

        return eurocross_ai_translate_text($title, $this->get_source_locale(), eurocross_ai_get_current_locale(), 'archive_title');
    }

    /**
     * Translate archive descriptions on the frontend.
     *
     * @param  string  $description  Archive description.
     * @return string
     */
    public function translate_archive_description($description)
    {
        if (! $this->should_translate_frontend() || ! is_string($description) || trim($description) === '') {
            return $description;
        }

        return eurocross_ai_translate_text($description, $this->get_source_locale(), eurocross_ai_get_current_locale(), 'term_description');
    }

    /**
     * Translate simple frontend text values.
     *
     * @param  string  $text  Text.
     * @return string
     */
    public function translate_simple_frontend_text($text)
    {
        if (! $this->should_translate_frontend() || ! is_string($text) || trim($text) === '') {
            return $text;
        }

        return eurocross_ai_translate_text($text, $this->get_source_locale(), eurocross_ai_get_current_locale(), 'frontend_text');
    }

    /**
     * Translate WooCommerce cart/checkout AJAX fragments server-side.
     *
     * @param  array<string, string>  $fragments  Fragments.
     * @return array<string, string>
     */
    public function translate_wc_fragments($fragments)
    {
        if (! $this->should_translate_frontend() || ! is_array($fragments)) {
            return $fragments;
        }

        foreach ($fragments as $key => $html) {
            if (! is_string($html) || trim($html) === '' || strlen($html) > 20000) {
                continue;
            }

            $fragments[$key] = eurocross_ai_translate_text($html, $this->get_source_locale(), eurocross_ai_get_current_locale(), 'block_html');
        }

        return $fragments;
    }

    /**
     * Translate selected WooCommerce Store API responses server-side.
     *
     * @param  WP_REST_Response  $result  Response.
     * @param  WP_REST_Server  $server  Server.
     * @param  WP_REST_Request  $request  Request.
     * @return WP_REST_Response
     */
    public function translate_wc_store_rest_response($result, $server, $request)
    {
        if (! ($result instanceof WP_REST_Response)) {
            return $result;
        }

        $route = $request instanceof WP_REST_Request ? (string) $request->get_route() : '';
        if (! preg_match('#^/wc/store/v1/(cart|checkout|batch|products)#', $route)) {
            return $result;
        }

        $target = eurocross_ai_get_current_locale();
        $source = $this->get_source_locale();
        if ($target === '' || $source === '' || $target === $source) {
            return $result;
        }

        $data = $result->get_data();
        if (! is_array($data)) {
            return $result;
        }

        $translatedCount = 0;
        $max = 80;
        $allowedKeys = ['name', 'description', 'short_description', 'label', 'text', 'title', 'message', 'aria-label', 'inner_html'];
        $skipKeys = ['id', 'key', 'slug', 'permalink', 'src', 'href', 'url', 'type', 'sku', 'code', 'currency', 'currency_code', 'currency_symbol', 'currency_minor_unit', 'price', 'regular_price', 'sale_price', 'prices', 'totals', 'extensions'];

        $walker = function ($value, $key = '') use (&$walker, &$translatedCount, $max, $allowedKeys, $skipKeys, $source, $target) {
            if ($translatedCount >= $max) {
                return $value;
            }
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $value[$k] = $walker($v, (string) $k);
                }

                return $value;
            }
            if (! is_string($value) || trim($value) === '' || strlen($value) > 500) {
                return $value;
            }
            $k = strtolower((string) $key);
            if (in_array($k, $skipKeys, true)) {
                return $value;
            }
            $isAllowedKey = in_array($k, $allowedKeys, true) || preg_match('/(name|label|title|description|message|text)$/', $k);
            $looksText = preg_match('/[A-Za-z]/', $value) && ! preg_match('/https?:\/\/|<[^>]+>/', $value);
            if (! $isAllowedKey || ! $looksText) {
                return $value;
            }

            $translated = eurocross_ai_translate_text($value, $source, $target, 'frontend_text');
            if (is_string($translated) && $translated !== '' && $translated !== $value) {
                $translatedCount++;

                return $translated;
            }

            return $value;
        };

        $result->set_data($walker($data));

        return $result;
    }

    /**
     * Translate selected frontend gettext strings (theme/Woo UI labels).
     *
     * @param  string  $translation  Translated text.
     * @param  string  $text  Original text.
     * @param  string  $domain  Text domain.
     * @return string
     */
    public function translate_frontend_gettext($translation, $text, $domain)
    {
        if (! $this->should_translate_frontend() || ! is_string($translation) || trim($translation) === '') {
            return $translation;
        }

        $text = is_string($text) ? trim($text) : '';
        $domain = is_string($domain) ? $domain : '';
        if ($text === '' || strlen($text) > 120) {
            return $translation;
        }

        static $allow = null;
        if ($allow === null) {
            $allow = array_flip([
                'Skip to content', 'Skip to content.', 'by', 'By', 'in', 'In', 'Read more', 'Continue reading',
                'Search', 'Search…', 'Search...', 'Previous', 'Next', 'Page not found', 'Nothing found',
                'No results found.', 'Leave a comment', 'Comments', 'Reply', 'Older posts', 'Newer posts',
                'Add to cart', 'View cart', 'Checkout', 'Cart', 'My account', 'Shop', 'Product', 'Products',
                'Subtotal', 'Total', 'Shipping', 'Tax', 'Discount', 'Coupon code', 'Apply coupon', 'Remove',
                'Update cart', 'Proceed to checkout', 'Place order', 'Billing details', 'Shipping address',
                'Payment method', 'Order summary', 'Your cart is currently empty.', 'Return to shop',
                'No products found', 'Sale', 'Out of stock', 'In stock', 'Description', 'Reviews',
                'Additional information', 'Related products', 'You may also like…', 'SKU', 'Category', 'Categories', 'Tags',
            ]);
        }

        $allowed = isset($allow[$text]) || isset($allow[$translation]);
        $shielded = $translation;
        $placeholders = [];
        if (! $allowed) {
            $looksUi = strlen($text) <= 120
                && preg_match('/[A-Za-z]/', $text)
                && ! preg_match('/https?:\/\/|<[^>]+>|[{}\[\]$=\\\\|;]/', $text)
                && ! preg_match('/^(wp_|wc_|woocommerce_|data-|aria-)/i', $text)
                && ! preg_match('/[\r\n\t]{2,}/', $text);
            if ($looksUi) {
                $shielded = preg_replace_callback('/%(?:\d+\$)?[sd%]|\\{[a-zA-Z0-9_]+\\}/', function ($m) use (&$placeholders) {
                    if ($m[0] === '%%') {
                        return '%%';
                    }
                    $token = '__ECXPH'.count($placeholders).'__';
                    $placeholders[$token] = $m[0];

                    return $token;
                }, (string) $translation);
                $allowed = (bool) preg_match('/^[\p{L}\p{N}\s\.,:;!?\-–—()\'"\/%#&+@*_]+$/u', $shielded);
            }
        }

        if (! $allowed) {
            return $translation;
        }

        $translated = eurocross_ai_translate_text($shielded, $this->get_source_locale(), eurocross_ai_get_current_locale(), 'frontend_text');
        if ($placeholders !== []) {
            $translated = strtr((string) $translated, $placeholders);
        }

        return $translated;
    }

    /**
     * Translate selected frontend ngettext strings.
     *
     * @param  string  $translation  Translated text.
     * @param  string  $single  Singular form.
     * @param  string  $plural  Plural form.
     * @param  int  $number  Number.
     * @param  string  $domain  Text domain.
     * @return string
     */
    public function translate_frontend_ngettext($translation, $single, $plural, $number, $domain)
    {
        if (! $this->should_translate_frontend() || ! is_string($translation) || trim($translation) === '') {
            return $translation;
        }

        $single = is_string($single) ? trim($single) : '';
        $plural = is_string($plural) ? trim($plural) : '';
        $domain = is_string($domain) ? $domain : '';
        $allowed = [
            '%s item', '%s items', '%d item', '%d items',
            '%s comment', '%s comments', '%1$s comment', '%1$s comments',
            '%s review', '%s reviews', '%s product', '%s products',
        ];

        $isAllowed = in_array($single, $allowed, true) || in_array($plural, $allowed, true);
        $shielded = $translation;
        $placeholders = [];
        if (! $isAllowed) {
            $looksUi = strlen($single) <= 120 && strlen($plural) <= 120
                && preg_match('/[A-Za-z]/', $single.$plural)
                && ! preg_match('/https?:\/\/|<[^>]+>|[{}\[\]$=\\\\|;]/', $single.$plural)
                && ! preg_match('/^(wp_|wc_|woocommerce_|data-|aria-)/i', $single.$plural);
            if ($looksUi) {
                $shielded = preg_replace_callback('/%(?:\d+\$)?[sd%]|\\{[a-zA-Z0-9_]+\\}/', function ($m) use (&$placeholders) {
                    if ($m[0] === '%%') {
                        return '%%';
                    }
                    $token = '__ECXPH'.count($placeholders).'__';
                    $placeholders[$token] = $m[0];

                    return $token;
                }, (string) $translation);
                $isAllowed = (bool) preg_match('/^[\p{L}\p{N}\s\.,:;!?\-–—()\'"\/%#&+@*_]+$/u', $shielded);
            }
        }

        if (! $isAllowed) {
            return $translation;
        }

        $translated = eurocross_ai_translate_text($shielded, $this->get_source_locale(), eurocross_ai_get_current_locale(), 'frontend_text');
        if ($placeholders !== []) {
            $translated = strtr((string) $translated, $placeholders);
        }

        return $translated;
    }

    /**
     * Translate selected frontend gettext with context.
     *
     * @param  string  $translation  Translated text.
     * @param  string  $text  Original text.
     * @param  string  $context  Context.
     * @param  string  $domain  Domain.
     * @return string
     */
    public function translate_frontend_gettext_with_context($translation, $text, $context, $domain)
    {
        return $this->translate_frontend_gettext($translation, $text, $domain);
    }

    /**
     * Translate selected frontend ngettext with context.
     *
     * @param  string  $translation  Translated text.
     * @param  string  $single  Singular.
     * @param  string  $plural  Plural.
     * @param  int  $number  Number.
     * @param  string  $context  Context.
     * @param  string  $domain  Domain.
     * @return string
     */
    public function translate_frontend_ngettext_with_context($translation, $single, $plural, $number, $context, $domain)
    {
        return $this->translate_frontend_ngettext($translation, $single, $plural, $number, $domain);
    }

    /**
     * Localize formatted frontend dates when possible, with AI fallback.
     *
     * @param  string  $date  Formatted date.
     * @param  string  $format  Requested format.
     * @param  mixed  $object  Post/comment object when provided.
     * @return string
     */
    public function translate_frontend_date($date, $format = '', $object = null)
    {
        if (! $this->should_translate_frontend() || ! is_string($date) || trim($date) === '') {
            return $date;
        }

        $target = eurocross_ai_get_current_locale();
        $wp_locale = $this->map_wp_locale($target);
        $format = is_string($format) ? $format : '';
        $timestamp = null;

        if (is_object($object) && isset($object->post_date_gmt)) {
            $timestamp = get_post_timestamp($object);
            if ($format === '') {
                $format = (string) get_option('date_format');
            }
        } elseif (is_object($object) && isset($object->comment_date_gmt)) {
            $timestamp = function_exists('get_comment_timestamp') ? get_comment_timestamp($object) : strtotime((string) $object->comment_date_gmt);
            if ($format === '') {
                $format = (string) get_option('date_format');
            }
        }

        if ($wp_locale !== '' && $timestamp) {
            $switched = function_exists('switch_to_locale') && switch_to_locale($wp_locale);
            try {
                $localized = function_exists('wp_date')
                    ? wp_date($format, (int) $timestamp, wp_timezone())
                    : date_i18n($format, (int) $timestamp);
                if (is_string($localized) && trim($localized) !== '') {
                    $date = $localized;
                }
            } finally {
                if ($switched) {
                    restore_previous_locale();
                }
            }
        }

        $mapped = $this->translate_date_string_fallback($date, $target);
        if ($mapped !== $date) {
            return $mapped;
        }

        return eurocross_ai_translate_text($date, $this->get_source_locale(), $target, 'frontend_text');
    }

    /**
     * Translate wp_date() output on frontend.
     *
     * @param  string|false  $date  Formatted date.
     * @param  string  $format  Format.
     * @param  int  $timestamp  Timestamp.
     * @param  DateTimeZone|null  $timezone  Timezone.
     * @return string|false
     */
    public function translate_wp_date($date, $format = '', $timestamp = 0, $timezone = null)
    {
        return $this->translate_date_filter_output($date, (string) $format, (int) $timestamp, $timezone, false);
    }

    /**
     * Translate date_i18n() output on frontend.
     *
     * @param  string  $date  Formatted date.
     * @param  string  $format  Format.
     * @param  int  $timestamp  Timestamp.
     * @param  bool  $gmt  GMT flag.
     * @return string
     */
    public function translate_date_i18n($date, $format = '', $timestamp = 0, $gmt = false)
    {
        return $this->translate_date_filter_output($date, (string) $format, (int) $timestamp, null, (bool) $gmt);
    }

    /**
     * Shared date filter handler with recursion guard.
     *
     * @param  mixed  $date  Date output.
     * @param  string  $format  Format.
     * @param  int  $timestamp  Timestamp.
     * @param  mixed  $timezone  Timezone.
     * @param  bool  $gmt  GMT.
     * @return mixed
     */
    private function translate_date_filter_output($date, string $format, int $timestamp, $timezone, bool $gmt)
    {
        static $inDateFilter = false;

        if ($inDateFilter || ! $this->should_translate_frontend() || ! is_string($date) || trim($date) === '') {
            return $date;
        }

        $target = eurocross_ai_get_current_locale();
        $source = $this->get_source_locale();
        if ($target === '' || $source === '' || $target === $source) {
            return $date;
        }

        // Quick deterministic pass first.
        $mapped = $this->translate_date_string_fallback($date, $target);
        if ($mapped !== $date) {
            return $mapped;
        }

        // Only attempt locale-aware reformat for reasonable timestamps and formats.
        $inDateFilter = true;
        try {
            if ($timestamp > 0 && $format !== '') {
                $wp_locale = $this->map_wp_locale($target);
                if ($wp_locale !== '') {
                    $switched = function_exists('switch_to_locale') && switch_to_locale($wp_locale);
                    try {
                        if ($timezone instanceof DateTimeZone && function_exists('wp_date')) {
                            $localized = wp_date($format, $timestamp, $timezone);
                        } elseif (function_exists('wp_date')) {
                            $localized = wp_date($format, $timestamp, $gmt ? new DateTimeZone('UTC') : wp_timezone());
                        } else {
                            $localized = date_i18n($format, $timestamp, $gmt);
                        }
                        if (is_string($localized) && trim($localized) !== '' && $localized !== $date) {
                            return $localized;
                        }
                    } finally {
                        if ($switched) {
                            restore_previous_locale();
                        }
                    }
                }
            }

            // AI fallback only for short, date-like strings to control cost.
            if (strlen($date) <= 80 && preg_match('/\d|January|February|March|April|May|June|July|August|September|October|November|December|Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sep|Oct|Nov|Dec|Mon|Tue|Wed|Thu|Fri|Sat|Sun/i', $date)) {
                $translated = eurocross_ai_translate_text($date, $source, $target, 'frontend_text');
                if (is_string($translated) && trim($translated) !== '') {
                    return $translated;
                }
            }
        } finally {
            $inDateFilter = false;
        }

        return $date;
    }

    /**
     * Map EuroCross locale to WordPress locale.
     */
    private function map_wp_locale(string $locale): string
    {
        $map = [
            'en' => 'en_US',
            'fr' => 'fr_FR',
            'de' => 'de_DE',
            'es' => 'es_ES',
            'it' => 'it_IT',
            'pt' => 'pt_PT',
            'nl' => 'nl_NL',
            'ar' => 'ar',
            'zh' => 'zh_CN',
            'ja' => 'ja',
        ];

        return $map[$locale] ?? '';
    }

    /**
     * Deterministic month/day translation fallback for dates.
     */
    private function translate_date_string_fallback(string $date, string $locale): string
    {
        $months = [
            'fr' => ['January' => 'janvier', 'February' => 'février', 'March' => 'mars', 'April' => 'avril', 'May' => 'mai', 'June' => 'juin', 'July' => 'juillet', 'August' => 'août', 'September' => 'septembre', 'October' => 'octobre', 'November' => 'novembre', 'December' => 'décembre', 'Jan' => 'janv.', 'Feb' => 'févr.', 'Mar' => 'mars', 'Apr' => 'avr.', 'Jun' => 'juin', 'Jul' => 'juil.', 'Aug' => 'août', 'Sep' => 'sept.', 'Oct' => 'oct.', 'Nov' => 'nov.', 'Dec' => 'déc.'],
            'de' => ['January' => 'Januar', 'February' => 'Februar', 'March' => 'März', 'April' => 'April', 'May' => 'Mai', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'August', 'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Dezember', 'Jan' => 'Jan.', 'Feb' => 'Feb.', 'Mar' => 'März', 'Apr' => 'Apr.', 'Jun' => 'Juni', 'Jul' => 'Juli', 'Aug' => 'Aug.', 'Sep' => 'Sep.', 'Oct' => 'Okt.', 'Nov' => 'Nov.', 'Dec' => 'Dez.'],
            'es' => ['January' => 'enero', 'February' => 'febrero', 'March' => 'marzo', 'April' => 'abril', 'May' => 'mayo', 'June' => 'junio', 'July' => 'julio', 'August' => 'agosto', 'September' => 'septiembre', 'October' => 'octubre', 'November' => 'noviembre', 'December' => 'diciembre', 'Jan' => 'ene.', 'Feb' => 'feb.', 'Mar' => 'mar.', 'Apr' => 'abr.', 'Jun' => 'jun.', 'Jul' => 'jul.', 'Aug' => 'ago.', 'Sep' => 'sept.', 'Oct' => 'oct.', 'Nov' => 'nov.', 'Dec' => 'dic.'],
            'it' => ['January' => 'gennaio', 'February' => 'febbraio', 'March' => 'marzo', 'April' => 'aprile', 'May' => 'maggio', 'June' => 'giugno', 'July' => 'luglio', 'August' => 'agosto', 'September' => 'settembre', 'October' => 'ottobre', 'November' => 'novembre', 'December' => 'dicembre', 'Jan' => 'gen', 'Feb' => 'feb', 'Mar' => 'mar', 'Apr' => 'apr', 'Jun' => 'giu', 'Jul' => 'lug', 'Aug' => 'ago', 'Sep' => 'set', 'Oct' => 'ott', 'Nov' => 'nov', 'Dec' => 'dic'],
            'pt' => ['January' => 'janeiro', 'February' => 'fevereiro', 'March' => 'março', 'April' => 'abril', 'May' => 'maio', 'June' => 'junho', 'July' => 'julho', 'August' => 'agosto', 'September' => 'setembro', 'October' => 'outubro', 'November' => 'novembro', 'December' => 'dezembro', 'Jan' => 'jan.', 'Feb' => 'fev.', 'Mar' => 'mar.', 'Apr' => 'abr.', 'Jun' => 'jun.', 'Jul' => 'jul.', 'Aug' => 'ago.', 'Sep' => 'set.', 'Oct' => 'out.', 'Nov' => 'nov.', 'Dec' => 'dez.'],
            'nl' => ['January' => 'januari', 'February' => 'februari', 'March' => 'maart', 'April' => 'april', 'May' => 'mei', 'June' => 'juni', 'July' => 'juli', 'August' => 'augustus', 'September' => 'september', 'October' => 'oktober', 'November' => 'november', 'December' => 'december', 'Jan' => 'jan.', 'Feb' => 'feb.', 'Mar' => 'mrt.', 'Apr' => 'apr.', 'Jun' => 'jun.', 'Jul' => 'jul.', 'Aug' => 'aug.', 'Sep' => 'sep.', 'Oct' => 'okt.', 'Nov' => 'nov.', 'Dec' => 'dec.'],
            'ar' => ['January' => 'يناير', 'February' => 'فبراير', 'March' => 'مارس', 'April' => 'أبريل', 'May' => 'مايو', 'June' => 'يونيو', 'July' => 'يوليو', 'August' => 'أغسطس', 'September' => 'سبتمبر', 'October' => 'أكتوبر', 'November' => 'نوفمبر', 'December' => 'ديسمبر', 'Jan' => 'يناير', 'Feb' => 'فبراير', 'Mar' => 'مارس', 'Apr' => 'أبريل', 'Jun' => 'يونيو', 'Jul' => 'يوليو', 'Aug' => 'أغسطس', 'Sep' => 'سبتمبر', 'Oct' => 'أكتوبر', 'Nov' => 'نوفمبر', 'Dec' => 'ديسمبر'],
            'zh' => ['January' => '一月', 'February' => '二月', 'March' => '三月', 'April' => '四月', 'May' => '五月', 'June' => '六月', 'July' => '七月', 'August' => '八月', 'September' => '九月', 'October' => '十月', 'November' => '十一月', 'December' => '十二月', 'Jan' => '1月', 'Feb' => '2月', 'Mar' => '3月', 'Apr' => '4月', 'Jun' => '6月', 'Jul' => '7月', 'Aug' => '8月', 'Sep' => '9月', 'Oct' => '10月', 'Nov' => '11月', 'Dec' => '12月'],
            'ja' => ['January' => '1月', 'February' => '2月', 'March' => '3月', 'April' => '4月', 'May' => '5月', 'June' => '6月', 'July' => '7月', 'August' => '8月', 'September' => '9月', 'October' => '10月', 'November' => '11月', 'December' => '12月', 'Jan' => '1月', 'Feb' => '2月', 'Mar' => '3月', 'Apr' => '4月', 'Jun' => '6月', 'Jul' => '7月', 'Aug' => '8月', 'Sep' => '9月', 'Oct' => '10月', 'Nov' => '11月', 'Dec' => '12月'],
        ];
        $days = [
            'fr' => ['Monday' => 'lundi', 'Tuesday' => 'mardi', 'Wednesday' => 'mercredi', 'Thursday' => 'jeudi', 'Friday' => 'vendredi', 'Saturday' => 'samedi', 'Sunday' => 'dimanche', 'Mon' => 'lun.', 'Tue' => 'mar.', 'Wed' => 'mer.', 'Thu' => 'jeu.', 'Fri' => 'ven.', 'Sat' => 'sam.', 'Sun' => 'dim.'],
            'de' => ['Monday' => 'Montag', 'Tuesday' => 'Dienstag', 'Wednesday' => 'Mittwoch', 'Thursday' => 'Donnerstag', 'Friday' => 'Freitag', 'Saturday' => 'Samstag', 'Sunday' => 'Sonntag', 'Mon' => 'Mo.', 'Tue' => 'Di.', 'Wed' => 'Mi.', 'Thu' => 'Do.', 'Fri' => 'Fr.', 'Sat' => 'Sa.', 'Sun' => 'So.'],
            'es' => ['Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles', 'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado', 'Sunday' => 'domingo', 'Mon' => 'lun.', 'Tue' => 'mar.', 'Wed' => 'mié.', 'Thu' => 'jue.', 'Fri' => 'vie.', 'Sat' => 'sáb.', 'Sun' => 'dom.'],
            'it' => ['Monday' => 'lunedì', 'Tuesday' => 'martedì', 'Wednesday' => 'mercoledì', 'Thursday' => 'giovedì', 'Friday' => 'venerdì', 'Saturday' => 'sabato', 'Sunday' => 'domenica', 'Mon' => 'lun', 'Tue' => 'mar', 'Wed' => 'mer', 'Thu' => 'gio', 'Fri' => 'ven', 'Sat' => 'sab', 'Sun' => 'dom'],
            'pt' => ['Monday' => 'segunda-feira', 'Tuesday' => 'terça-feira', 'Wednesday' => 'quarta-feira', 'Thursday' => 'quinta-feira', 'Friday' => 'sexta-feira', 'Saturday' => 'sábado', 'Sunday' => 'domingo', 'Mon' => 'seg.', 'Tue' => 'ter.', 'Wed' => 'qua.', 'Thu' => 'qui.', 'Fri' => 'sex.', 'Sat' => 'sáb.', 'Sun' => 'dom.'],
            'nl' => ['Monday' => 'maandag', 'Tuesday' => 'dinsdag', 'Wednesday' => 'woensdag', 'Thursday' => 'donderdag', 'Friday' => 'vrijdag', 'Saturday' => 'zaterdag', 'Sunday' => 'zondag', 'Mon' => 'ma', 'Tue' => 'di', 'Wed' => 'wo', 'Thu' => 'do', 'Fri' => 'vr', 'Sat' => 'za', 'Sun' => 'zo'],
            'ar' => ['Monday' => 'الاثنين', 'Tuesday' => 'الثلاثاء', 'Wednesday' => 'الأربعاء', 'Thursday' => 'الخميس', 'Friday' => 'الجمعة', 'Saturday' => 'السبت', 'Sunday' => 'الأحد', 'Mon' => 'اثنين', 'Tue' => 'ثلاثاء', 'Wed' => 'أربعاء', 'Thu' => 'خميس', 'Fri' => 'جمعة', 'Sat' => 'سبت', 'Sun' => 'أحد'],
            'zh' => ['Monday' => '星期一', 'Tuesday' => '星期二', 'Wednesday' => '星期三', 'Thursday' => '星期四', 'Friday' => '星期五', 'Saturday' => '星期六', 'Sunday' => '星期日', 'Mon' => '周一', 'Tue' => '周二', 'Wed' => '周三', 'Thu' => '周四', 'Fri' => '周五', 'Sat' => '周六', 'Sun' => '周日'],
            'ja' => ['Monday' => '月曜日', 'Tuesday' => '火曜日', 'Wednesday' => '水曜日', 'Thursday' => '木曜日', 'Friday' => '金曜日', 'Saturday' => '土曜日', 'Sunday' => '日曜日', 'Mon' => '月', 'Tue' => '火', 'Wed' => '水', 'Thu' => '木', 'Fri' => '金', 'Sat' => '土', 'Sun' => '日'],
        ];
        $map = array_merge($months[$locale] ?? [], $days[$locale] ?? []);
        if ($map === []) {
            return $date;
        }
        uksort($map, fn ($a, $b) => strlen($b) <=> strlen($a));
        $translated = strtr($date, $map);

        return $translated;
    }

    /**
     * Translate bloginfo name/description on the frontend.
     *
     * @param  string  $output  Blog info output.
     * @param  string  $show  Requested field.
     * @return string
     */
    public function filter_bloginfo_frontend($output, $show)
    {
        if (! $this->should_translate_frontend() || ! in_array((string) $show, ['name', 'description'], true)) {
            return $output;
        }

        if (! is_string($output) || trim($output) === '') {
            return $output;
        }

        return eurocross_ai_translate_text($output, $this->get_source_locale(), eurocross_ai_get_current_locale(), 'blog_option');
    }

    /**
     * Translate post/page titles on the frontend.
     *
     * @param  string  $title  Title.
     * @param  int  $post_id  Post ID.
     * @return string
     */
    public function translate_post_title($title, $post_id = 0)
    {
        if (! $this->should_translate_frontend() || ! is_string($title) || $title === '') {
            return $title;
        }

        $post_type = $post_id ? get_post_type($post_id) : '';
        if ($post_type === 'nav_menu_item') {
            return $title;
        }

        $context = $post_type === 'product' ? 'product_title' : 'post_title';

        return eurocross_ai_translate_text($title, $this->get_source_locale(), eurocross_ai_get_current_locale(), $context);
    }

    /**
     * Translate post/page content on the frontend.
     *
     * @param  string  $content  Content.
     * @return string
     */
    public function translate_post_content($content)
    {
        if (! $this->should_translate_frontend() || ! is_string($content) || trim(wp_strip_all_tags($content)) === '') {
            return $content;
        }

        global $post;
        $post_type = ($post && isset($post->post_type)) ? $post->post_type : '';
        $context = $post_type === 'product' ? 'product_description' : 'post_content';

        return eurocross_ai_translate_text($content, $this->get_source_locale(), eurocross_ai_get_current_locale(), $context);
    }

    /**
     * Translate excerpts on the frontend.
     *
     * @param  string  $excerpt  Excerpt.
     * @param  WP_Post  $post  Post.
     * @return string
     */
    public function translate_post_excerpt($excerpt, $post = null)
    {
        if (! $this->should_translate_frontend() || ! is_string($excerpt) || trim($excerpt) === '') {
            return $excerpt;
        }

        $post_object = $post instanceof WP_Post ? $post : (get_post($post) ?: null);
        if ($post_object && empty($post_object->post_excerpt)) {
            return $excerpt;
        }

        $post_type = $post_object && isset($post_object->post_type) ? $post_object->post_type : '';
        $context = $post_type === 'product' ? 'product_short_description' : 'post_excerpt';

        return eurocross_ai_translate_text($excerpt, $this->get_source_locale(), eurocross_ai_get_current_locale(), $context);
    }

    /**
     * Translate navigation menu item labels.
     *
     * @param  array  $items  Menu items.
     * @return array
     */
    public function translate_menu_items($items)
    {
        if (! $this->should_translate_frontend() || ! is_array($items)) {
            return $items;
        }

        $source = $this->get_source_locale();
        $target = eurocross_ai_get_current_locale();

        foreach ($items as $item) {
            if (! is_object($item)) {
                continue;
            }
            if (! empty($item->title)) {
                $item->title = eurocross_ai_translate_text((string) $item->title, $source, $target, 'menu_item');
            }
            if (! empty($item->attr_title)) {
                $item->attr_title = eurocross_ai_translate_text((string) $item->attr_title, $source, $target, 'menu_attr');
            }
            if (! empty($item->url)) {
                $item->url = $this->propagate_language_url((string) $item->url);
            }
        }

        return $items;
    }

    /**
     * Translate widget titles.
     *
     * @param  string  $title  Widget title.
     * @return string
     */
    public function translate_widget_title($title)
    {
        if (! $this->should_translate_frontend() || ! is_string($title) || trim($title) === '') {
            return $title;
        }

        return eurocross_ai_translate_text($title, $this->get_source_locale(), eurocross_ai_get_current_locale(), 'widget_title');
    }

    /**
     * Translate widget text content.
     *
     * @param  string  $text  Widget text.
     * @return string
     */
    public function translate_widget_text($text)
    {
        if (! $this->should_translate_frontend() || ! is_string($text) || trim($text) === '') {
            return $text;
        }

        return eurocross_ai_translate_text($text, $this->get_source_locale(), eurocross_ai_get_current_locale(), 'widget_text');
    }

    /**
     * Translate document title parts.
     *
     * @param  array  $parts  Title parts.
     * @return array
     */
    public function translate_document_title_parts($parts)
    {
        if (! $this->should_translate_frontend() || ! is_array($parts)) {
            return $parts;
        }

        $source = $this->get_source_locale();
        $target = eurocross_ai_get_current_locale();

        foreach ($parts as $key => $value) {
            if (is_string($value) && trim($value) !== '') {
                $parts[$key] = eurocross_ai_translate_text($value, $source, $target, 'document_title');
            }
        }

        return $parts;
    }

    /**
     * Translate blog name/description options on the frontend.
     *
     * @param  mixed  $value  Option value.
     * @return mixed
     */
    public function translate_blog_option_frontend($value)
    {
        if (! $this->should_translate_frontend() || ! is_string($value) || trim($value) === '') {
            return $value;
        }

        return eurocross_ai_translate_text($value, $this->get_source_locale(), eurocross_ai_get_current_locale(), 'blog_option');
    }

    /**
     * Translate taxonomy term name and description on the frontend.
     *
     * @param  WP_Term|mixed  $term  Term object.
     * @return WP_Term|mixed
     */
    public function translate_term($term)
    {
        if (! $this->should_translate_frontend() || ! is_object($term) || empty($term->term_id)) {
            return $term;
        }

        if (! empty($term->eurocross_translated)) {
            return $term;
        }

        $source = $this->get_source_locale();
        $target = eurocross_ai_get_current_locale();

        $clone = clone $term;
        if (! empty($clone->name)) {
            $clone->name = eurocross_ai_translate_text((string) $clone->name, $source, $target, 'term_name');
        }
        if (! empty($clone->description)) {
            $clone->description = eurocross_ai_translate_text((string) $clone->description, $source, $target, 'term_description');
        }
        $clone->eurocross_translated = true;

        return $clone;
    }

    /**
     * Render the floating chatbot widget in the footer.
     *
     * @return void
     */
    public function render_floating_chatbot()
    {
        if (is_admin() || ! eurocross_ai_is_enabled('eurocross_chatbot_enabled')) {
            return;
        }

        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;

        $source = $this->get_source_locale();
        $target = eurocross_ai_get_current_locale();

        $title = __('Chat with us', 'eurocross-ai');
        $subtitle = __('We usually reply in a moment', 'eurocross-ai');
        $welcome = __('Hi! How can we help you today?', 'eurocross-ai');
        $close_chat = __('Close chat', 'eurocross-ai');
        $open_chat = __('Open chat', 'eurocross-ai');
        $your_message = __('Your message', 'eurocross-ai');
        $placeholder = __('Type your message…', 'eurocross-ai');
        $send = __('Send', 'eurocross-ai');

        if ($target !== '' && $target !== $source) {
            $title = eurocross_ai_translate_text($title, $source, $target, 'frontend_text');
            $subtitle = eurocross_ai_translate_text($subtitle, $source, $target, 'frontend_text');
            $welcome = eurocross_ai_translate_text($welcome, $source, $target, 'frontend_text');
            $close_chat = eurocross_ai_translate_text($close_chat, $source, $target, 'frontend_text');
            $open_chat = eurocross_ai_translate_text($open_chat, $source, $target, 'frontend_text');
            $your_message = eurocross_ai_translate_text($your_message, $source, $target, 'frontend_text');
            $placeholder = eurocross_ai_translate_text($placeholder, $source, $target, 'frontend_text');
            $send = eurocross_ai_translate_text($send, $source, $target, 'frontend_text');
        }

        $input_id = 'eurocross-chatbot-message-float';
        $panel_id = 'eurocross-chatbot-panel';
        ?>
		<div class="eurocross-chatbot-float is-open" data-eurocross-chatbot data-eurocross-chatbot-float>
			<section id="<?php echo esc_attr($panel_id); ?>" class="eurocross-chatbot-panel" aria-label="<?php echo esc_attr($title); ?>">
				<header class="eurocross-chatbot-header">
					<div class="eurocross-chatbot-header-main">
						<span class="eurocross-chatbot-badge" aria-hidden="true"><i class="eurocross-chatbot-badge-icon"></i></span>
						<div>
							<h2 class="eurocross-chatbot-title"><?php echo esc_html($title); ?></h2>
							<p class="eurocross-chatbot-subtitle"><?php echo esc_html($subtitle); ?></p>
						</div>
					</div>
					<button type="button" class="eurocross-chatbot-close" data-eurocross-chatbot-toggle aria-controls="<?php echo esc_attr($panel_id); ?>" aria-expanded="true" aria-label="<?php echo esc_attr($close_chat); ?>">
						<span aria-hidden="true">&times;</span>
					</button>
				</header>
				<div class="eurocross-chatbot-messages" role="log" aria-live="polite" aria-relevant="additions">
					<div class="eurocross-chatbot-message is-assistant">
						<div class="eurocross-chatbot-bubble eurocross-chatbot-welcome">
							<span class="eurocross-chatbot-body"><?php echo esc_html($welcome); ?></span>
						</div>
					</div>
				</div>
				<form class="eurocross-chatbot-form">
					<label class="screen-reader-text" for="<?php echo esc_attr($input_id); ?>"><?php echo esc_html($your_message); ?></label>
					<div class="eurocross-chatbot-input-row">
						<textarea id="<?php echo esc_attr($input_id); ?>" name="message" rows="2" maxlength="4000" required placeholder="<?php echo esc_attr($placeholder); ?>"></textarea>
						<button type="submit" class="eurocross-chatbot-send" aria-label="<?php echo esc_attr($send); ?>">
							<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M2.01 21 23 12 2.01 3 2 10l15 2-15 2z"/></svg>
						</button>
					</div>
					<p class="eurocross-chatbot-status" role="status" aria-live="polite"></p>
				</form>
			</section>
			<button type="button" class="eurocross-chatbot-launcher" data-eurocross-chatbot-toggle aria-controls="<?php echo esc_attr($panel_id); ?>" aria-expanded="true" aria-label="<?php echo esc_attr($open_chat); ?>">
				<span class="eurocross-chatbot-launcher-open" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zm0 14H5.17L4 17.17V4h16v12z"/><path d="M7 9h10v2H7zm0-3h10v2H7zm0 6h7v2H7z"/></svg>
				</span>
				<span class="eurocross-chatbot-launcher-close" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M18.3 5.71 12 12l6.3 6.29-1.41 1.42L10.59 13.4 4.29 19.71 2.88 18.3 9.17 12 2.88 5.71 4.29 4.29l6.3 6.3 6.29-6.3z"/></svg>
				</span>
			</button>
		</div>
		<?php
    }

    /**
     * Show dismissible admin notices for missing keys and failed connections.
     *
     * @return void
     */
    public function admin_notices()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && strpos((string) $screen->id, 'eurocross') === false) {
            return;
        }

        $this->render_flash_notice();

        $dismissed = (int) get_user_meta(get_current_user_id(), 'eurocross_notice_dismissed', true);
        if ($dismissed > time() - WEEK_IN_SECONDS) {
            return;
        }

        $message = '';
        $isCritical = false;
        if (trim((string) eurocross_ai_get_option('eurocross_api_key')) === '') {
            $message = __('Add your EuroCross AI API key on the settings page to enable translations and VAT checks.', 'eurocross-ai');
        } else {
            $lastIssue = get_transient('eurocross_ai_last_api_issue');
            if (is_array($lastIssue) && ! empty($lastIssue['message'])) {
                $statusCode = isset($lastIssue['status']) ? absint($lastIssue['status']) : 0;
                $isCritical = in_array($statusCode, [401, 402, 403, 429], true);
                $prefix = in_array($statusCode, [402, 403, 429], true)
                    ? __('EuroCross translation is paused: ', 'eurocross-ai')
                    : __('EuroCross API issue: ', 'eurocross-ai');
                $message = $prefix.sanitize_text_field((string) $lastIssue['message']);
                if (in_array($statusCode, [402, 403, 429], true)) {
                    $message .= ' '.__('Check your EuroCross plan/quota, then reconnect or recharge. Existing cached translations may still display.', 'eurocross-ai');
                }
            }

            if ($message === '') {
                $status = get_transient('eurocross_connection_status');
                if (is_array($status) && isset($status['ok']) && (bool) $status['ok'] === false) {
                    $message = sprintf(
                        /* translators: %s: API error message. */
                        __('The last EuroCross AI connection check failed: %s', 'eurocross-ai'),
                        sanitize_text_field((string) (isset($status['message']) ? $status['message'] : ''))
                    );
                }
            }
        }

        if ($message === '') {
            return;
        }

        $dismiss_url = wp_nonce_url(
            admin_url('admin-post.php?action=eurocross_dismiss_notice'),
            'eurocross_dismiss_notice'
        );
        $notice_class = $isCritical ? 'notice notice-error eurocross-notice' : 'notice notice-warning is-dismissible eurocross-notice';
        ?>
		<div class="<?php echo esc_attr($notice_class); ?>">
			<p>
				<?php echo esc_html($message); ?>
				<a href="<?php echo esc_url(admin_url('admin.php?page=eurocross-ai-settings')); ?>"><?php esc_html_e('Open settings', 'eurocross-ai'); ?></a>
				<?php if (! $isCritical) { ?>
					<a href="<?php echo esc_url($dismiss_url); ?>" class="eurocross-dismiss"><?php esc_html_e('Dismiss for a week', 'eurocross-ai'); ?></a>
				<?php } ?>
			</p>
		</div>
		<?php
    }

    /**
     * Render a one-time notice left by an admin action.
     *
     * @return void
     */
    private function render_flash_notice()
    {
        $flash = get_transient('eurocross_admin_flash_'.get_current_user_id());
        if (! is_array($flash) || empty($flash['message'])) {
            return;
        }
        delete_transient('eurocross_admin_flash_'.get_current_user_id());

        $type = ! empty($flash['type']) && $flash['type'] === 'error' ? 'error' : 'success';
        ?>
		<div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
			<p><?php echo esc_html(sanitize_text_field((string) $flash['message'])); ?></p>
		</div>
		<?php
    }

    /**
     * Store a one-time admin notice and redirect to settings.
     *
     * @param  string  $message  Notice message.
     * @param  string  $type  Notice type.
     * @return void
     */
    private function flash_and_redirect($message, $type = 'success')
    {
        set_transient(
            'eurocross_admin_flash_'.get_current_user_id(),
            [
                'message' => sanitize_text_field($message),
                'type' => $type === 'error' ? 'error' : 'success',
            ],
            5 * MINUTE_IN_SECONDS
        );

        wp_safe_redirect(admin_url('admin.php?page=eurocross-ai-settings'));
        exit;
    }

    /**
     * Dismiss the connection notice for one week.
     *
     * @return void
     */
    public function dismiss_notice()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to perform this action.', 'eurocross-ai'), 403);
        }

        check_admin_referer('eurocross_dismiss_notice');
        update_user_meta(get_current_user_id(), 'eurocross_notice_dismissed', time() + WEEK_IN_SECONDS);

        wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url());
        exit;
    }

    /**
     * Start a trial through the SaaS API.
     *
     * @return array<string,mixed>|WP_Error
     */
    private function do_start_trial()
    {
        $response = eurocross_ai_get_api()->start_trial();
        if (is_wp_error($response)) {
            return $response;
        }

        $data = isset($response['data']) ? eurocross_ai_sanitize_response_data($response['data']) : [];
        update_option('eurocross_trial_data', $data, false);
        delete_transient('eurocross_dashboard_data');

        return [
            'message' => __('Trial started.', 'eurocross-ai'),
            'data' => $data,
        ];
    }

    /**
     * Create a hosted checkout session through the SaaS API.
     *
     * @param  string  $plan  Plan identifier.
     * @param  string  $email  Checkout email.
     * @return string|WP_Error Checkout URL or error.
     */
    private function do_checkout($plan, $email)
    {
        $plan = eurocross_ai_sanitize_plan($plan);
        $email = sanitize_email(wp_unslash($email));

        if ($plan === '' || $email === '' || ! is_email($email)) {
            return new WP_Error('eurocross_invalid_checkout', __('Enter a valid plan and email address.', 'eurocross-ai'), ['status' => 400]);
        }

        $response = eurocross_ai_get_api()->checkout($plan, $email);
        if (is_wp_error($response)) {
            return $response;
        }

        $url = isset($response['checkout_url']) ? esc_url_raw((string) $response['checkout_url']) : '';
        if ($url === '' || wp_parse_url($url, PHP_URL_SCHEME) !== 'https' || ! wp_parse_url($url, PHP_URL_HOST)) {
            return new WP_Error('eurocross_invalid_checkout_url', __('The API did not return a valid checkout URL.', 'eurocross-ai'), ['status' => 502]);
        }

        return $url;
    }

    /**
     * Exchange the one-time code returned by the SaaS connection screen.
     *
     * The WordPress-generated state transient is the CSRF check for this
     * callback, so it is consumed before any code exchange is attempted.
     *
     * @return void
     */
    public function handle_connect_return()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to perform this action.', 'eurocross-ai'), 403);
        }

        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $site_url = isset($_GET['site_url']) ? esc_url_raw(wp_unslash($_GET['site_url'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $state_key = 'eurocross_connect_state_'.get_current_user_id();
        $expected_state = get_transient($state_key);
        delete_transient($state_key);

        if ($code === '' || $state === '' || $site_url === ''
            || ! is_string($expected_state) || ! hash_equals($expected_state, $state)) {
            $this->flash_and_redirect(__('The connection request expired or is invalid. Start again from your WordPress admin.', 'eurocross-ai'), 'error');
        }

        if (untrailingslashit($site_url) !== untrailingslashit(home_url())) {
            $this->flash_and_redirect(__('The connection code belongs to a different site.', 'eurocross-ai'), 'error');
        }

        $response = eurocross_ai_get_api()->exchange_connect_code($code, $site_url, $state);
        if (is_wp_error($response)) {
            $this->flash_and_redirect($response->get_error_message(), 'error');
        }

        $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
        $api_key = eurocross_ai_sanitize_api_key(isset($data['api_key']) ? $data['api_key'] : '');
        $site_secret = sanitize_text_field(isset($data['site_secret']) ? (string) $data['site_secret'] : '');

        if ($api_key === '' || $site_secret === '' || ! eurocross_ai_apply_plan_payload($data)) {
            $this->flash_and_redirect(__('EuroCross returned an incomplete connection response.', 'eurocross-ai'), 'error');
        }

        update_option('eurocross_api_key', $api_key, false);
        update_option('eurocross_site_secret', $site_secret, false);
        eurocross_ai_get_api(true);

        $this->flash_and_redirect(__('EuroCross connected. Your plan and languages are now synced.', 'eurocross-ai'));
    }

    /**
     * admin-post handler for starting a trial.
     *
     * @return void
     */
    public function handle_start_trial()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to perform this action.', 'eurocross-ai'), 403);
        }

        check_admin_referer('eurocross_start_trial');

        $result = $this->do_start_trial();
        if (is_wp_error($result)) {
            $this->flash_and_redirect($result->get_error_message(), 'error');
        }

        $this->flash_and_redirect($result['message']);
    }

    /**
     * admin-post handler for checkout.
     *
     * @return void
     */
    public function handle_checkout()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to perform this action.', 'eurocross-ai'), 403);
        }

        check_admin_referer('eurocross_checkout');

        $plan = isset($_POST['eurocross_plan']) ? sanitize_text_field(wp_unslash($_POST['eurocross_plan'])) : '';
        $email = isset($_POST['eurocross_email']) ? sanitize_email(wp_unslash($_POST['eurocross_email'])) : '';

        $result = $this->do_checkout($plan, $email);
        if (is_wp_error($result)) {
            $this->flash_and_redirect($result->get_error_message(), 'error');
        }

        $this->redirect_to_checkout($result);
    }

    /**
     * Safely redirect to the SaaS-hosted checkout URL.
     *
     * @param  string  $url  Checkout URL.
     * @return void
     */
    private function redirect_to_checkout($url)
    {
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            wp_die(esc_html__('Invalid checkout URL.', 'eurocross-ai'));
        }

        $allow_host = function ($hosts) use ($host) {
            $hosts[] = $host;

            return array_values(array_unique(array_filter($hosts)));
        };

        add_filter('allowed_redirect_hosts', $allow_host);
        wp_safe_redirect($url);
        exit;
    }

    /**
     * AJAX handler for the connection test.
     *
     * @return void
     */
    public function ajax_test_connection()
    {
        check_ajax_referer('eurocross_admin_action', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You are not allowed to perform this action.', 'eurocross-ai')], 403);
        }

        $response = eurocross_ai_get_api()->health();
        if (is_wp_error($response)) {
            set_transient(
                'eurocross_connection_status',
                [
                    'ok' => false,
                    'message' => $response->get_error_message(),
                    'checked_at' => time(),
                ],
                DAY_IN_SECONDS
            );
            wp_send_json_error(['message' => $response->get_error_message()], 502);
        }

        $status = isset($response['status']) ? sanitize_text_field((string) $response['status']) : '';
        if ($status !== 'ok') {
            wp_send_json_error(['message' => __('The API did not report a healthy status.', 'eurocross-ai')], 502);
        }

        set_transient(
            'eurocross_connection_status',
            [
                'ok' => true,
                'message' => __('Connected to the EuroCross AI API.', 'eurocross-ai'),
                'checked_at' => time(),
            ],
            DAY_IN_SECONDS
        );

        wp_send_json_success(
            [
                'message' => __('Connection successful.', 'eurocross-ai'),
                'status' => $status,
            ]
        );
    }

    /**
     * AJAX handler for starting a trial.
     *
     * @return void
     */
    public function ajax_start_trial()
    {
        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $valid = wp_verify_nonce($nonce, 'eurocross_start_trial')
            || wp_verify_nonce($nonce, 'eurocross_admin_action');

        if (! $valid) {
            wp_send_json_error(['message' => __('Invalid security token. Please reload the page and try again.', 'eurocross-ai')], 403);
        }

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You are not allowed to perform this action.', 'eurocross-ai')], 403);
        }

        $result = $this->do_start_trial();
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 502);
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX handler for checkout.
     *
     * @return void
     */
    public function ajax_checkout()
    {
        // Accept either the form-specific nonce or the shared admin nonce.
        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $valid = wp_verify_nonce($nonce, 'eurocross_checkout')
            || wp_verify_nonce($nonce, 'eurocross_admin_action');

        if (! $valid) {
            wp_send_json_error(['message' => __('Invalid security token. Please reload the page and try again.', 'eurocross-ai')], 403);
        }

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You are not allowed to perform this action.', 'eurocross-ai')], 403);
        }

        $plan = isset($_POST['eurocross_plan']) ? sanitize_text_field(wp_unslash($_POST['eurocross_plan'])) : '';
        $email = isset($_POST['eurocross_email']) ? sanitize_email(wp_unslash($_POST['eurocross_email'])) : '';

        $result = $this->do_checkout($plan, $email);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 502);
        }

        wp_send_json_success(
            [
                'message' => __('Opening checkout…', 'eurocross-ai'),
                'checkout_url' => esc_url_raw($result),
            ]
        );
    }
}

EuroCross_AI::init();
