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
 * Browser routes verify the WordPress REST nonce. The SaaS-to-site plan sync
 * route verifies a shared secret issued during the secure connect flow. All
 * calls to the Laravel SaaS API are performed here, on the server.
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
                'permission_callback' => [$this, 'verify_nonce'],
            ],
            '/translate' => [
                'methods' => 'POST',
                'callback' => [$this, 'translate'],
                'permission_callback' => [$this, 'verify_nonce'],
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
                'permission_callback' => [$this, 'verify_nonce'],
                'args' => [
                    'message' => ['required' => true],
                    'language' => ['required' => false],
                    'context' => ['required' => false],
                ],
            ],
            '/vat/check' => [
                'methods' => 'POST',
                'callback' => [$this, 'check_vat'],
                'permission_callback' => [$this, 'verify_nonce'],
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
                'permission_callback' => [$this, 'verify_nonce'],
                'args' => [
                    'vat_number' => ['required' => true],
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
     * Locale changes are restricted to users who can edit content.
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

        if (! current_user_can('edit_posts')) {
            return new WP_Error(
                'eurocross_rest_forbidden',
                __('You are not allowed to change the site locale.', 'eurocross-ai'),
                ['status' => 403]
            );
        }

        return true;
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

        update_option('eurocross_locale', $locale, false);
        update_user_meta(get_current_user_id(), 'eurocross_locale', $locale);

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
}
