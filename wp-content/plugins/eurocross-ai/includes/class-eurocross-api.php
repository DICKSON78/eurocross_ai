<?php

/**
 * Server-side client for the EuroCross AI SaaS API.
 */
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handles all communication with the Laravel SaaS API.
 *
 * The API key never leaves the server: it is only attached to outbound HTTP
 * requests made with the WordPress HTTP API.
 */
class EuroCross_AI_API
{
    /**
     * Base URL of the SaaS API.
     *
     * @var string
     */
    private $base_url;

    /**
     * SaaS API key.
     *
     * @var string
     */
    private $api_key;

    /**
     * Request timeout in seconds.
     *
     * @var int
     */
    private $timeout;

    /**
     * Constructor.
     *
     * @param  string  $base_url  Base URL stored in the eurocross_api_url option.
     * @param  string  $api_key  SaaS key stored in the eurocross_api_key option.
     */
    public function __construct($base_url = '', $api_key = '')
    {
        $this->base_url = ! empty($base_url) ? untrailingslashit((string) $base_url) : 'https://eurocross-ai.kadetech.online';
        $this->api_key = (string) $api_key;
        $timeout = absint(apply_filters('eurocross_ai_request_timeout', 15));
        $this->timeout = $timeout > 0 ? $timeout : 15;
    }

    /**
     * Return the configured base URL.
     *
     * @return string
     */
    public function get_base_url()
    {
        return $this->base_url;
    }

    /**
     * GET /eurocross/health
     *
     * @return array|WP_Error
     */
    public function health()
    {
        return $this->request('GET', '/eurocross/health');
    }

    /**
     * GET /eurocross/languages
     *
     * @return array|WP_Error
     */
    public function languages()
    {
        return $this->request('GET', '/eurocross/languages');
    }

    /**
     * POST /eurocross/translate
     *
     * @param  string  $text  Text to translate.
     * @param  string  $source_lang  Source language code.
     * @param  string  $target_lang  Target language code.
     * @return array|WP_Error
     */
    public function translate($text, $source_lang, $target_lang)
    {
        return $this->request(
            'POST',
            '/eurocross/translate',
            [
                'text' => (string) $text,
                'source_lang' => (string) $source_lang,
                'target_lang' => (string) $target_lang,
            ]
        );
    }

    /**
     * POST /eurocross/vat/check
     *
     * @param  string  $customer_country  Customer country code.
     * @param  string  $merchant_country  Merchant country code.
     * @param  string  $vat_number  VAT number.
     * @param  bool  $use_oss  Whether OSS applies.
     * @return array|WP_Error
     */
    public function vat_check($customer_country, $merchant_country, $vat_number, $use_oss)
    {
        return $this->request(
            'POST',
            '/eurocross/vat/check',
            [
                'customer_country' => (string) $customer_country,
                'merchant_country' => (string) $merchant_country,
                'vat_number' => (string) $vat_number,
                'use_oss' => (bool) $use_oss,
            ]
        );
    }

    /**
     * POST /eurocross/vat/validate-vies
     *
     * @param  string  $vat_number  VAT number to validate.
     * @return array|WP_Error
     */
    public function validate_vies($vat_number)
    {
        return $this->request(
            'POST',
            '/eurocross/vat/validate-vies',
            [
                'vat_number' => (string) $vat_number,
            ]
        );
    }

    /**
     * GET /eurocross/chatbot/status
     *
     * @return array|WP_Error
     */
    public function chatbot_status()
    {
        return $this->request('GET', '/eurocross/chatbot/status');
    }

    /**
     * POST /eurocross/chatbot/message
     *
     * @param  string  $message  Visitor message.
     * @param  string  $language  Language code.
     * @param  string  $context  Optional page context.
     * @return array|WP_Error
     */
    public function chat($message, $language, $context = '')
    {
        return $this->request(
            'POST',
            '/eurocross/chatbot/message',
            [
                'message' => (string) $message,
                'language' => (string) $language,
                'context' => (string) $context,
            ]
        );
    }

    /**
     * GET /eurocross/account/summary
     *
     * @return array|WP_Error
     */
    public function account_summary()
    {
        return $this->request('GET', '/eurocross/account/summary');
    }

    /**
     * GET /eurocross/account/analytics
     *
     * @return array|WP_Error
     */
    public function account_analytics()
    {
        return $this->request('GET', '/eurocross/account/analytics');
    }

    /**
     * POST /eurocross/trial/start
     *
     * @return array|WP_Error
     */
    public function start_trial()
    {
        return $this->request('POST', '/eurocross/trial/start');
    }

    /**
     * POST /eurocross/checkout
     *
     * @param  string  $plan  Plan identifier.
     * @param  string  $email  Checkout email.
     * @return array|WP_Error
     */
    public function checkout($plan, $email)
    {
        return $this->request(
            'POST',
            '/eurocross/checkout',
            [
                'plan' => (string) $plan,
                'email' => (string) $email,
            ]
        );
    }

    /**
     * Perform an authenticated request and decode the JSON response.
     *
     * @param  string  $method  HTTP method.
     * @param  string  $path  API path.
     * @param  array|null  $payload  Optional JSON payload.
     * @return array|WP_Error Decoded response body or error.
     */
    private function request($method, $path, $payload = null)
    {
        if ($this->api_key === '') {
            return new WP_Error(
                'eurocross_missing_api_key',
                __('Enter your EuroCross AI API key in the plugin settings first.', 'eurocross-ai'),
                ['status' => 400]
            );
        }

        $url = $this->base_url.'/'.ltrim($path, '/');

        $args = [
            'method' => $method,
            'timeout' => $this->timeout,
            'redirection' => 3,
            'headers' => [
                'Accept' => 'application/json',
                'X-API-Key' => $this->api_key,
            ],
        ];

        if ($payload !== null) {
            $encoded = wp_json_encode($payload);
            if ($encoded === false) {
                return new WP_Error('eurocross_invalid_payload', __('Could not encode the API request.', 'eurocross-ai'));
            }
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = $encoded;
        }

        if (function_exists('eurocross_ai_log')) {
            eurocross_ai_log(
                'API request',
                [
                    'method' => $method,
                    'path' => $path,
                ]
            );
        }

        $response = wp_safe_remote_request($url, $args);

        if (is_wp_error($response)) {
            return new WP_Error(
                'eurocross_request_failed',
                sprintf(
                    /* translators: %s: HTTP error detail. */
                    __('EuroCross AI API request failed: %s', 'eurocross-ai'),
                    sanitize_text_field($response->get_error_message())
                ),
                ['status' => 502]
            );
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code < 200 || $status_code >= 300) {
            return new WP_Error(
                'eurocross_http_error',
                sprintf(
                    /* translators: %d: HTTP status code. */
                    __('EuroCross AI API returned HTTP %d.', 'eurocross-ai'),
                    $status_code
                ),
                ['status' => 502]
            );
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return new WP_Error(
                'eurocross_invalid_response',
                __('EuroCross AI API returned an invalid JSON response.', 'eurocross-ai'),
                ['status' => 502]
            );
        }

        if (array_key_exists('success', $decoded) && ! $decoded['success']) {
            $message = isset($decoded['message']) ? sanitize_text_field(wp_strip_all_tags((string) $decoded['message'])) : '';
            if ($message === '') {
                $message = __('The EuroCross AI API reported an unsuccessful request.', 'eurocross-ai');
            }

            return new WP_Error(
                'eurocross_api_error',
                $message,
                ['status' => 502]
            );
        }

        return $decoded;
    }
}
