<?php

/**
 * Public REST API exposed to the plugin's frontend JavaScript.
 */
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers the eurocross/v1 REST routes.
 *
 * Browser routes verify the WordPress REST nonce and are rate limited. AI
 * routes also require an administrator or the explicit public-AI setting. The
 * SaaS-to-site plan sync route verifies a shared secret issued during the
 * secure connect flow. All calls to the Laravel SaaS API are performed here,
 * on the server.
 */
class EuroCross_AI_REST
{
    /**
     * Register the rest_api_init hook.
     *
     * @return void
     */
    public function register()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register routes.
     *
     * @return void
     */
    public function register_routes()
    {
        $routes = [
            '/plan-sync' => [
                'methods' => 'POST',
                'callback' => [$this, 'plan_sync'],
                'permission_callback' => [$this, 'verify_site_secret'],
            ],
            '/languages' => [
                'methods' => 'GET',
                'callback' => [$this, 'get_languages'],
                'permission_callback' => [$this, 'can_read_languages'],
            ],
            '/translate' => [
                'methods' => 'POST',
                'callback' => [$this, 'translate'],
                'permission_callback' => [$this, 'can_translate'],
                'args' => [
                    'text' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field',
                    ],
                    'source_lang' => [
                        'required' => false,
                        'type' => 'string',
                        'sanitize_callback' => 'eurocross_ai_sanitize_language_code',
                    ],
                    'target_lang' => [
                        'required' => false,
                        'type' => 'string',
                        'sanitize_callback' => 'eurocross_ai_sanitize_language_code',
                    ],
                ],
            ],
            '/locale' => [
                'methods' => 'POST',
                'callback' => [$this, 'set_locale'],
                'permission_callback' => [$this, 'can_edit_locale'],
                'args' => [
                    'locale' => ['required' => true],
                ],
            ],
            '/chat' => [
                'methods' => 'POST',
                'callback' => [$this, 'chat'],
                'permission_callback' => [$this, 'can_chat'],
                'args' => [
                    'message' => ['required' => true],
                    'language' => ['required' => false],
                    'context' => ['required' => false],
                ],
            ],
            '/vat/check' => [
                'methods' => 'POST',
                'callback' => [$this, 'check_vat'],
                'permission_callback' => [$this, 'can_check_vat'],
                'args' => [
                    'customer_country' => ['required' => false],
                    'merchant_country' => ['required' => false],
                    'vat_number' => ['required' => false],
                    'use_oss' => ['required' => false],
                ],
            ],
            '/vat/validate-vies' => [
                'methods' => 'POST',
                'callback' => [$this, 'validate_vies'],
                'permission_callback' => [$this, 'can_validate_vies'],
                'args' => [
                    'vat_number' => ['required' => true],
                ],
            ],
            '/translations' => [
                'methods' => 'GET',
                'callback' => [$this, 'get_translations'],
                'permission_callback' => [$this, 'can_manage_translations'],
                'args' => [
                    'status' => [
                        'required' => false,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                    'target_lang' => [
                        'required' => false,
                        'type' => 'string',
                        'sanitize_callback' => 'eurocross_ai_sanitize_language_code',
                    ],
                    'source_type' => [
                        'required' => false,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                    'per_page' => [
                        'required' => false,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                    'page' => [
                        'required' => false,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
            '/translations/summary' => [
                'methods' => 'GET',
                'callback' => [$this, 'get_translation_summary'],
                'permission_callback' => [$this, 'can_manage_translations'],
                'args' => [
                    'days' => [
                        'required' => false,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
            '/translations/bulk' => [
                'methods' => 'POST',
                'callback' => [$this, 'queue_bulk_translations'],
                'permission_callback' => [$this, 'can_manage_translations'],
                'args' => [
                    'source_lang' => [
                        'required' => false,
                        'type' => 'string',
                        'sanitize_callback' => 'eurocross_ai_sanitize_language_code',
                    ],
                    'target_lang' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'eurocross_ai_sanitize_language_code',
                    ],
                    'items' => [
                        'required' => true,
                        'type' => 'array',
                        'maxItems' => 100,
                    ],
                ],
            ],
            '/translations/bulk-retry' => [
                'methods' => 'POST',
                'callback' => [$this, 'retry_bulk_translations'],
                'permission_callback' => [$this, 'can_manage_translations'],
                'args' => [
                    'ids' => [
                        'required' => true,
                        'type' => 'array',
                        'maxItems' => 100,
                    ],
                ],
            ],
            '/translations/(?P<id>\d+)' => [
                'methods' => 'GET',
                'callback' => [$this, 'get_translation'],
                'permission_callback' => [$this, 'can_manage_translations'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
            '/translations/(?P<id>\d+)/update' => [
                'methods' => 'POST',
                'callback' => [$this, 'update_translation'],
                'permission_callback' => [$this, 'can_manage_translations'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                    'translated_text' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'wp_kses_post',
                    ],
                ],
            ],
            '/translations/(?P<id>\d+)/retry' => [
                'methods' => 'POST',
                'callback' => [$this, 'retry_translation'],
                'permission_callback' => [$this, 'can_manage_translations'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ];

        foreach ($routes as $route => $options) {
            register_rest_route('eurocross/v1', $route, $options);
        }
    }

    /**
     * Require a valid WordPress REST nonce on every route.
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return true|WP_Error
     */
    public function verify_nonce($request)
    {
        $nonce = $request->get_header('X-WP-Nonce');
        if (empty($nonce)) {
            $nonce = $request->get_param('_wpnonce');
        }

        if (empty($nonce) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), 'wp_rest')) {
            return new WP_Error(
                'eurocross_rest_nonce',
                __('Invalid security token. Please reload the page and try again.', 'eurocross-ai'),
                ['status' => 401]
            );
        }

        return true;
    }

    /**
     * Verify the shared secret sent by the SaaS when pushing plan changes.
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return true|WP_Error
     */
    public function verify_site_secret($request)
    {
        $secret = $request->get_header('X-EuroCross-Secret');
        $expected = (string) eurocross_ai_get_option('eurocross_site_secret');

        if ($expected === '' || ! is_string($secret) || ! hash_equals($expected, $secret)) {
            return new WP_Error(
                'eurocross_invalid_site_secret',
                __('Invalid site secret.', 'eurocross-ai'),
                ['status' => 401]
            );
        }

        return true;
    }

    /**
     * Language lookups are public but bounded to prevent abuse.
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return true|WP_Error
     */
    public function can_read_languages($request)
    {
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }

        return $this->enforce_rate_limit($request, 'languages', 60, MINUTE_IN_SECONDS);
    }

    /**
     * Translation consumes quota; allow nonce-authenticated storefront requests
     * so WooCommerce dynamic content can translate, but rate limit aggressively.
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return true|WP_Error
     */
    public function can_translate($request)
    {
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }

        $limit = current_user_can('manage_options') ? 60 : 30;

        return $this->enforce_rate_limit($request, 'translation', $limit, MINUTE_IN_SECONDS);
    }

    /**
     * Chatbot requests are gated by both the chatbot and public-AI settings.
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return true|WP_Error
     */
    public function can_chat($request)
    {
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }

        if (! eurocross_ai_is_enabled('eurocross_chatbot_enabled')) {
            return new WP_Error(
                'eurocross_chatbot_disabled',
                __('The chatbot is not enabled on this site.', 'eurocross-ai'),
                ['status' => 403]
            );
        }

        if (! current_user_can('manage_options') && ! eurocross_ai_is_enabled('eurocross_allow_public_ai')) {
            return new WP_Error(
                'eurocross_public_ai_disabled',
                __('Public AI requests are disabled on this site.', 'eurocross-ai'),
                ['status' => 403]
            );
        }

        return $this->enforce_rate_limit($request, 'chat', 10, MINUTE_IN_SECONDS);
    }

    /**
     * VAT lookups remain available at checkout but are rate limited.
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return true|WP_Error
     */
    public function can_check_vat($request)
    {
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }

        return $this->enforce_rate_limit($request, 'vat_check', 30, MINUTE_IN_SECONDS);
    }

    /**
     * VIES validation remains available at checkout but is rate limited.
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return true|WP_Error
     */
    public function can_validate_vies($request)
    {
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }

        return $this->enforce_rate_limit($request, 'vies', 20, MINUTE_IN_SECONDS);
    }

    /**
     * Locale changes update the site-wide default, so only administrators may use them.
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return true|WP_Error
     */
    public function can_edit_locale($request)
    {
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }

        if (! current_user_can('manage_options')) {
            return new WP_Error(
                'eurocross_rest_forbidden',
                __('You are not allowed to change the site locale.', 'eurocross-ai'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Translation management routes are private to site administrators.
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return true|WP_Error
     */
    public function can_manage_translations($request)
    {
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }

        if (! current_user_can('manage_options')) {
            return new WP_Error(
                'eurocross_rest_forbidden',
                __('You are not allowed to manage translations.', 'eurocross-ai'),
                ['status' => 403]
            );
        }

        return $this->enforce_rate_limit($request, 'admin_translations', 120, MINUTE_IN_SECONDS);
    }

    /**
     * Apply a simple fixed-window rate limit per user or IP address.
     *
     * @param  WP_REST_Request  $request  Request object.
     * @param  string  $bucket  Rate-limit bucket name.
     * @param  int  $limit  Maximum requests in the window.
     * @param  int  $window  Window length in seconds.
     * @return true|WP_Error
     */
    private function enforce_rate_limit($request, $bucket, $limit, $window)
    {
        $key = 'eurocross_rl_'.md5($bucket.'|'.$this->rate_limit_identity($request));
        $count = (int) get_transient($key);

        if ($count >= $limit) {
            return new WP_Error(
                'eurocross_rate_limited',
                __('Too many requests. Please try again shortly.', 'eurocross-ai'),
                ['status' => 429]
            );
        }

        set_transient($key, $count + 1, $window);

        return true;
    }

    /**
     * Build a non-reversible rate-limit identity without logging raw IP addresses.
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return string
     */
    private function rate_limit_identity($request)
    {
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            return 'user:'.$user_id;
        }

        $remote_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

        return $remote_address === '' ? 'unknown' : 'ip:'.md5($remote_address);
    }

    /**
     * GET eurocross/v1/languages
     *
     * @return WP_REST_Response
     */
    public function get_languages()
    {
        return rest_ensure_response(
            [
                'success' => true,
                'data' => eurocross_ai_get_languages(),
            ]
        );
    }

    /**
     * POST eurocross/v1/plan-sync
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function plan_sync($request)
    {
        $payload = $request->get_json_params();
        if (! is_array($payload) || empty($payload)) {
            $payload = $request->get_params();
        }

        if (! eurocross_ai_apply_plan_payload($payload)) {
            return new WP_Error(
                'eurocross_invalid_plan_payload',
                __('The plan payload is missing required plan or language data.', 'eurocross-ai'),
                ['status' => 400]
            );
        }

        return rest_ensure_response(
            [
                'success' => true,
                'data' => [
                    'plan' => (string) eurocross_ai_get_option('eurocross_plan'),
                    'languages' => eurocross_ai_get_languages(),
                ],
            ]
        );
    }

    /**
     * POST eurocross/v1/translate
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function translate($request)
    {
        $text = trim((string) $request->get_param('text'));
        $source_lang = eurocross_ai_sanitize_language_code($request->get_param('source_lang'));
        $target_lang = eurocross_ai_sanitize_language_code($request->get_param('target_lang'));

        if ($text === '') {
            return new WP_Error('eurocross_empty_text', __('Enter the text to translate.', 'eurocross-ai'), ['status' => 400]);
        }

        if (strlen($text) > 10000) {
            return new WP_Error('eurocross_text_too_long', __('The text is too long to translate.', 'eurocross-ai'), ['status' => 400]);
        }

        if ($source_lang === '') {
            $source_lang = eurocross_ai_get_option('eurocross_locale');
        }

        if ($target_lang === '') {
            $target_lang = eurocross_ai_get_current_locale();
        }

        $response = eurocross_ai_get_api()->translate($text, $source_lang, $target_lang);
        if (is_wp_error($response)) {
            return eurocross_ai_rest_error($response);
        }

        $raw_data = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];

        return rest_ensure_response(
            [
                'success' => true,
                'data' => [
                    'original' => sanitize_text_field(isset($raw_data['original']) ? (string) $raw_data['original'] : $text),
                    'translated' => sanitize_text_field(isset($raw_data['translated']) ? (string) $raw_data['translated'] : ''),
                    'target_language' => eurocross_ai_sanitize_language_code(isset($raw_data['target_language']) ? $raw_data['target_language'] : $target_lang),
                    'provider' => isset($raw_data['provider']) ? sanitize_text_field((string) $raw_data['provider']) : '',
                    'tokens' => isset($raw_data['tokens']) ? absint($raw_data['tokens']) : 0,
                    'cost' => isset($raw_data['cost']) ? sanitize_text_field((string) $raw_data['cost']) : '',
                ],
            ]
        );
    }

    /**
     * POST eurocross/v1/locale
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function set_locale($request)
    {
        $locale = eurocross_ai_sanitize_language_code($request->get_param('locale'));

        if ($locale === '') {
            return new WP_Error('eurocross_invalid_locale', __('Enter a valid language code.', 'eurocross-ai'), ['status' => 400]);
        }

        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'eurocross_locale', $locale);
        }

        $language_name = $locale;
        foreach (eurocross_ai_get_languages() as $language) {
            if (isset($language['code']) && $language['code'] === $locale) {
                $language_name = $language['name'];
                break;
            }
        }

        return rest_ensure_response(
            [
                'success' => true,
                'data' => [
                    'locale' => $locale,
                    'name' => sanitize_text_field($language_name),
                ],
            ]
        );
    }

    /**
     * POST eurocross/v1/chat
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function chat($request)
    {
        if (! eurocross_ai_is_enabled('eurocross_chatbot_enabled')) {
            return new WP_Error('eurocross_chatbot_disabled', __('The chatbot is not enabled on this site.', 'eurocross-ai'), ['status' => 403]);
        }

        $message = trim((string) $request->get_param('message'));
        if ($message === '') {
            return new WP_Error('eurocross_empty_message', __('Enter a message.', 'eurocross-ai'), ['status' => 400]);
        }

        if (strlen($message) > 4000) {
            return new WP_Error('eurocross_message_too_long', __('The message is too long.', 'eurocross-ai'), ['status' => 400]);
        }

        $language = eurocross_ai_sanitize_language_code($request->get_param('language'));
        if ($language === '') {
            $language = eurocross_ai_get_current_locale();
        }

        $context = sanitize_text_field((string) $request->get_param('context'));
        if (strlen($context) > 500) {
            $context = substr($context, 0, 500);
        }

        $response = eurocross_ai_get_api()->chat($message, $language, $context);
        if (is_wp_error($response)) {
            return eurocross_ai_rest_error($response);
        }

        $raw_data = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];

        return rest_ensure_response(
            [
                'success' => true,
                'data' => [
                    'reply' => sanitize_text_field(isset($raw_data['reply']) ? (string) $raw_data['reply'] : ''),
                    'language' => eurocross_ai_sanitize_language_code(isset($raw_data['language']) ? $raw_data['language'] : $language),
                    'tokens_used' => isset($raw_data['tokens_used']) ? absint($raw_data['tokens_used']) : 0,
                ],
            ]
        );
    }

    /**
     * POST eurocross/v1/vat/check
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function check_vat($request)
    {
        $customer_country = eurocross_ai_sanitize_country_code($request->get_param('customer_country'));
        $merchant_country = eurocross_ai_sanitize_country_code($request->get_param('merchant_country'));
        $vat_number = eurocross_ai_sanitize_vat_number($request->get_param('vat_number'));
        $use_oss = $request->get_param('use_oss') === null ? eurocross_ai_is_enabled('eurocross_use_oss') : (bool) $request->get_param('use_oss');

        if ($merchant_country === '') {
            $merchant_country = eurocross_ai_sanitize_country_code(eurocross_ai_get_option('eurocross_default_country'));
        }

        if ($customer_country === '' || $vat_number === '') {
            return new WP_Error(
                'eurocross_vat_missing_fields',
                __('A customer country and VAT number are required.', 'eurocross-ai'),
                ['status' => 400]
            );
        }

        $response = eurocross_ai_get_api()->vat_check($customer_country, $merchant_country, $vat_number, $use_oss);
        if (is_wp_error($response)) {
            return eurocross_ai_rest_error($response);
        }

        $raw_data = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];

        return rest_ensure_response(
            [
                'success' => true,
                'data' => [
                    'chargeVat' => isset($raw_data['chargeVat']) ? (bool) $raw_data['chargeVat'] : null,
                    'rate' => isset($raw_data['rate']) ? (float) $raw_data['rate'] : null,
                    'reason' => isset($raw_data['reason']) ? sanitize_text_field((string) $raw_data['reason']) : '',
                ],
            ]
        );
    }

    /**
     * POST eurocross/v1/vat/validate-vies
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function validate_vies($request)
    {
        $vat_number = eurocross_ai_sanitize_vat_number($request->get_param('vat_number'));

        if ($vat_number === '') {
            return new WP_Error(
                'eurocross_vat_missing_number',
                __('A VAT number is required.', 'eurocross-ai'),
                ['status' => 400]
            );
        }

        $response = eurocross_ai_get_api()->validate_vies($vat_number);
        if (is_wp_error($response)) {
            return eurocross_ai_rest_error($response);
        }

        $raw_data = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];

        return rest_ensure_response(
            [
                'success' => true,
                'data' => [
                    'valid' => isset($raw_data['valid']) ? (bool) $raw_data['valid'] : false,
                    'vat_number' => $vat_number,
                    'country_code' => isset($raw_data['country_code']) ? sanitize_text_field((string) $raw_data['country_code']) : '',
                    'name' => isset($raw_data['name']) ? sanitize_text_field((string) $raw_data['name']) : '',
                    'address' => isset($raw_data['address']) ? sanitize_text_field((string) $raw_data['address']) : '',
                ],
            ]
        );
    }

    /**
     * GET eurocross/v1/translations
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_translations($request)
    {
        $args = [];
        foreach (['status', 'target_lang', 'source_type', 'per_page', 'page'] as $key) {
            $value = $request->get_param($key);
            if ($value !== null) {
                $args[$key] = $value;
            }
        }

        $response = eurocross_ai_get_api()->translations($args);
        if (is_wp_error($response)) {
            return eurocross_ai_rest_error($response);
        }

        return rest_ensure_response($response);
    }

    /**
     * GET eurocross/v1/translations/summary
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_translation_summary($request)
    {
        $days = absint($request->get_param('days'));
        if ($days < 1) {
            $days = 30;
        }
        $days = min(90, $days);

        $response = eurocross_ai_get_api()->translation_summary($days);
        if (is_wp_error($response)) {
            return eurocross_ai_rest_error($response);
        }

        return rest_ensure_response($response);
    }

    /**
     * GET eurocross/v1/translations/{id}
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_translation($request)
    {
        $response = eurocross_ai_get_api()->translation(absint($request['id']));
        if (is_wp_error($response)) {
            return eurocross_ai_rest_error($response);
        }

        return rest_ensure_response($response);
    }

    /**
     * POST eurocross/v1/translations/{id}/update
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function update_translation($request)
    {
        $translation_id = absint($request['id']);
        $translated_text = (string) $request->get_param('translated_text');

        if (trim(wp_strip_all_tags($translated_text)) === '') {
            return new WP_Error('eurocross_empty_translation', __('Enter the translated text.', 'eurocross-ai'), ['status' => 400]);
        }

        $response = eurocross_ai_get_api()->update_translation($translation_id, $translated_text);
        if (is_wp_error($response)) {
            return eurocross_ai_rest_error($response);
        }

        $this->store_translation_locally($response);

        return rest_ensure_response($response);
    }

    /**
     * POST eurocross/v1/translations/{id}/retry
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function retry_translation($request)
    {
        $response = eurocross_ai_get_api()->retry_translation(absint($request['id']));
        if (is_wp_error($response)) {
            return eurocross_ai_rest_error($response);
        }

        $this->store_translation_locally($response);

        return rest_ensure_response($response);
    }

    /**
     * POST eurocross/v1/translations/bulk
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function queue_bulk_translations($request)
    {
        $items = $request->get_param('items');
        $target_lang = eurocross_ai_sanitize_language_code($request->get_param('target_lang'));
        $source_lang = eurocross_ai_sanitize_language_code($request->get_param('source_lang'));

        if (! is_array($items) || empty($items)) {
            return new WP_Error('eurocross_empty_translation_items', __('Select at least one translation to queue.', 'eurocross-ai'), ['status' => 400]);
        }
        if ($target_lang === '') {
            return new WP_Error('eurocross_invalid_target_language', __('A valid target language is required.', 'eurocross-ai'), ['status' => 400]);
        }
        if ($source_lang === '') {
            $source_lang = eurocross_ai_sanitize_language_code(eurocross_ai_get_option('eurocross_locale'));
        }

        $response = eurocross_ai_get_api()->bulk_translations($items, $source_lang, $target_lang);
        if (is_wp_error($response)) {
            return eurocross_ai_rest_error($response);
        }

        $records = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
        foreach ($records as $record) {
            $this->store_translation_locally(['data' => $record]);
        }

        return rest_ensure_response($response);
    }

    /**
     * POST eurocross/v1/translations/bulk-retry
     *
     * @param  WP_REST_Request  $request  Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function retry_bulk_translations($request)
    {
        $ids = array_values(array_filter(array_map('absint', (array) $request->get_param('ids'))));
        if (empty($ids)) {
            return new WP_Error('eurocross_empty_translation_ids', __('Select at least one translation to retry.', 'eurocross-ai'), ['status' => 400]);
        }
        if (count($ids) > 100) {
            $ids = array_slice($ids, 0, 100);
        }

        $records = [];
        $errors = [];

        foreach ($ids as $translation_id) {
            $response = eurocross_ai_get_api()->retry_translation($translation_id);
            if (is_wp_error($response)) {
                $errors[] = sprintf(
                    /* translators: 1: translation ID, 2: error message. */
                    __('Translation %1$d: %2$s', 'eurocross-ai'),
                    $translation_id,
                    $response->get_error_message()
                );

                continue;
            }

            $record = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
            $this->store_translation_locally(['data' => $record]);
            $records[] = $record;
        }

        if (empty($records) && ! empty($errors)) {
            return new WP_Error(
                'eurocross_bulk_retry_failed',
                esc_html(implode(' ', $errors)),
                ['status' => 502]
            );
        }

        return rest_ensure_response(
            [
                'success' => true,
                'data' => $records,
                'errors' => $errors,
                'meta' => [
                    'requested' => count($ids),
                    'succeeded' => count($records),
                    'failed' => count($errors),
                ],
            ]
        );
    }

    /**
     * Mirror a SaaS translation record into local product meta when possible.
     *
     * @param  array<string,mixed>  $response  Decoded SaaS response.
     * @return void
     */
    private function store_translation_locally($response)
    {
        if (! class_exists('EuroCross_AI_WooCommerce')) {
            return;
        }

        $record = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
        if (empty($record) || ! method_exists('EuroCross_AI_WooCommerce', 'store_translation_record')) {
            return;
        }

        EuroCross_AI_WooCommerce::store_translation_record($record);
    }
}
