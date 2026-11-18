<?php

/**
 * WooCommerce integration for EuroCross AI.
 */
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Adds translation and VAT/OSS features to WooCommerce.
 *
 * All hooks are registered only when WooCommerce is active.
 */
class EuroCross_AI_WooCommerce
{
    /**
     * Products currently being written by this integration, keyed by product ID.
     *
     * @var array<int,int>
     */
    private static $guarded_product_ids = [];

    /**
     * Register WooCommerce hooks.
     *
     * @return void
     */
    public function register()
    {
        if (! class_exists('WooCommerce')) {
            return;
        }

        add_action('woocommerce_update_product', [$this, 'schedule_product_translation'], 20, 1);
        add_action('woocommerce_new_product', [$this, 'schedule_product_translation'], 20, 1);
        add_action(EUROCROSS_AI_PRODUCT_TRANSLATION_HOOK, [$this, 'run_scheduled_product_translation'], 10, 1);

        if (eurocross_ai_is_enabled('eurocross_translate_seo')) {
            add_filter('get_post_meta', [$this, 'translate_seo_post_meta'], 20, 4);
        }
        if (eurocross_ai_is_enabled('eurocross_translate_slug')) {
            add_filter('post_link', [$this, 'translate_product_permalink'], 20, 3);
        }

        add_filter('woocommerce_product_get_name', [$this, 'translate_product_name'], 20, 2);
        add_filter('woocommerce_product_get_short_description', [$this, 'translate_product_short_description'], 20, 2);
        add_filter('woocommerce_product_get_description', [$this, 'translate_product_description'], 20, 2);
        add_filter('the_title', [$this, 'translate_product_post_title'], 20, 2);

        add_filter('woocommerce_checkout_fields', [$this, 'add_checkout_fields']);
        add_action('woocommerce_after_checkout_billing_form', [$this, 'render_vat_status']);
        add_action('woocommerce_checkout_update_order_review', [$this, 'refresh_vat_note']);
        add_action('woocommerce_checkout_process', [$this, 'validate_checkout_vat']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_order_meta']);
        add_action('woocommerce_review_order_after_order_total', [$this, 'display_vat_note']);

        add_filter('woocommerce_mail_content', [$this, 'translate_order_email'], 20, 2);
        add_filter('woocommerce_email_subject_customer_processing_order', [$this, 'translate_email_subject'], 20, 2);
        add_filter('woocommerce_email_subject_customer_completed_order', [$this, 'translate_email_subject'], 20, 2);
        add_filter('woocommerce_email_subject_new_order', [$this, 'translate_email_subject'], 20, 2);
    }

    /**
     * Translate a WooCommerce product name.
     *
     * @param  string  $name  Product name.
     * @param  WC_Product  $product  Product object.
     * @return string
     */
    public function translate_product_name($name, $product = null)
    {
        if (! eurocross_ai_is_enabled('eurocross_translate_product_titles')) {
            return $name;
        }

        return $this->maybe_translate($name, 'product_title', $product);
    }

    /**
     * Translate a WooCommerce product short description.
     *
     * @param  string  $description  Short description.
     * @param  WC_Product  $product  Product object.
     * @return string
     */
    public function translate_product_short_description($description, $product = null)
    {
        if (! eurocross_ai_is_enabled('eurocross_translate_product_descriptions')) {
            return $description;
        }

        return $this->maybe_translate($description, 'product_short_description', $product);
    }

    /**
     * Translate a WooCommerce product description.
     *
     * @param  string  $description  Description.
     * @param  WC_Product  $product  Product object.
     * @return string
     */
    public function translate_product_description($description, $product = null)
    {
        if (! eurocross_ai_is_enabled('eurocross_translate_product_descriptions')) {
            return $description;
        }

        return $this->maybe_translate($description, 'product_description', $product);
    }

    /**
     * Translate product titles rendered through the WordPress loop.
     *
     * @param  string  $title  Post title.
     * @param  int  $post_id  Post ID.
     * @return string
     */
    public function translate_product_post_title($title, $post_id = 0)
    {
        if (! eurocross_ai_is_enabled('eurocross_translate_product_titles') || is_admin()) {
            return $title;
        }

        if (get_post_type($post_id) !== 'product') {
            return $title;
        }

        if (! (function_exists('is_woocommerce') && is_woocommerce()) && ! is_singular('product')) {
            return $title;
        }

        return $this->maybe_translate($title, 'product_title', $post_id);
    }

    /**
     * Add the VAT number field to the WooCommerce checkout.
     *
     * @param  array  $fields  Checkout fields.
     * @return array
     */
    public function add_checkout_fields($fields)
    {
        if (! isset($fields['billing']) || ! is_array($fields['billing'])) {
            return $fields;
        }

        $fields['billing']['billing_eurocross_vat_toggle'] = [
            'type' => 'checkbox',
            'label' => __('I have an EU VAT number', 'eurocross-ai'),
            'required' => false,
            'class' => ['form-row-wide'],
            'priority' => 110,
            'clear' => true,
            'custom_attributes' => [
                'data-eurocross-vat-toggle' => '',
            ],
        ];

        $fields['billing']['billing_eurocross_vat_number'] = [
            'type' => 'text',
            'label' => __('VAT number (EU B2B)', 'eurocross-ai'),
            'placeholder' => __('Enter your VAT number', 'eurocross-ai'),
            'required' => false,
            'class' => ['form-row-wide'],
            'priority' => 120,
            'clear' => true,
            'custom_attributes' => [
                'autocomplete' => 'off',
                'maxlength' => 20,
                'data-eurocross-vat-number' => '',
            ],
        ];

        return $fields;
    }

    /**
     * Output the live region used by the frontend VAT checker.
     *
     * @return void
     */
    public function render_vat_status()
    {
        echo '<p class="eurocross-vat-status" data-eurocross-vat-status role="status" aria-live="polite"></p>';
    }

    /**
     * Recalculate the VAT treatment when the checkout review is refreshed.
     *
     * @param  string  $posted_data  URL-encoded checkout data.
     * @return void
     */
    public function refresh_vat_note($posted_data = '')
    {
        if (! function_exists('WC') || ! WC()->session) {
            return;
        }

        $posted = [];
        if (is_string($posted_data) && $posted_data !== '') {
            parse_str($posted_data, $posted);
        }

        $vat_number = isset($posted['billing_eurocross_vat_number']) ? eurocross_ai_sanitize_vat_number($posted['billing_eurocross_vat_number']) : '';
        $country = isset($posted['billing_country']) ? eurocross_ai_sanitize_country_code($posted['billing_country']) : '';

        if ($vat_number === '' || $country === '') {
            WC()->session->set('eurocross_vat_result', null);

            return;
        }

        WC()->session->set('eurocross_vat_result', $this->calculate_vat_treatment($vat_number, $country));
    }

    /**
     * Validate the submitted VAT number using the VIES endpoint.
     *
     * @return void
     */
    public function validate_checkout_vat()
    {
        if (! function_exists('WC') || ! WC()->checkout() || ! function_exists('wc_add_notice')) {
            return;
        }

        $posted = WC()->checkout()->get_posted_data();
        $vat_number = isset($posted['billing_eurocross_vat_number']) ? eurocross_ai_sanitize_vat_number($posted['billing_eurocross_vat_number']) : '';
        $country = isset($posted['billing_country']) ? eurocross_ai_sanitize_country_code($posted['billing_country']) : '';

        if ($vat_number === '' || $country === '') {
            return;
        }

        $validation = eurocross_ai_get_api()->validate_vies($vat_number);

        if (is_wp_error($validation)) {
            wc_add_notice(__('We could not verify the VAT number right now. Please try again.', 'eurocross-ai'), 'error');

            return;
        }

        $valid = isset($validation['data']['valid']) ? (bool) $validation['data']['valid'] : false;
        if (! $valid) {
            wc_add_notice(__('The VAT number could not be validated with VIES.', 'eurocross-ai'), 'error');

            return;
        }

        if (WC()->session) {
            WC()->session->set('eurocross_vat_result', $this->calculate_vat_treatment($vat_number, $country));
        }
    }

    /**
     * Call the VAT/OSS endpoint and sanitize the result.
     *
     * @param  string  $vat_number  VAT number.
     * @param  string  $customer_country  Customer country code.
     * @return array
     */
    private function calculate_vat_treatment($vat_number, $customer_country)
    {
        $merchant_country = eurocross_ai_sanitize_country_code(eurocross_ai_get_option('eurocross_default_country'));

        if ($merchant_country === '' && function_exists('wc_get_base_location')) {
            $base_location = wc_get_base_location();
            $merchant_country = eurocross_ai_sanitize_country_code(isset($base_location['country']) ? $base_location['country'] : '');
        }

        $response = eurocross_ai_get_api()->vat_check(
            $customer_country,
            $merchant_country,
            $vat_number,
            eurocross_ai_is_enabled('eurocross_use_oss')
        );

        if (is_wp_error($response)) {
            return [
                'error' => $response->get_error_message(),
            ];
        }

        $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];

        return [
            'chargeVat' => isset($data['chargeVat']) ? (bool) $data['chargeVat'] : null,
            'rate' => isset($data['rate']) ? (float) $data['rate'] : null,
            'reason' => isset($data['reason']) ? sanitize_text_field((string) $data['reason']) : '',
        ];
    }

    /**
     * Display the real VAT treatment returned by the API.
     *
     * @return void
     */
    public function display_vat_note()
    {
        if (! function_exists('WC') || ! WC()->session) {
            return;
        }

        $result = WC()->session->get('eurocross_vat_result');
        if (! is_array($result) || empty($result)) {
            return;
        }

        if (! empty($result['error'])) {
            echo '<div class="eurocross-vat-note eurocross-vat-note--error" role="status">';
            echo '<p>'.esc_html(sanitize_text_field((string) $result['error'])).'</p>';
            echo '</div>';

            return;
        }

        echo '<div class="eurocross-vat-note" role="status">';

        if (isset($result['chargeVat']) && (bool) $result['chargeVat'] === false) {
            echo '<p><strong>'.esc_html__('Reverse charge applies.', 'eurocross-ai').'</strong></p>';
        } elseif (isset($result['chargeVat']) && (bool) $result['chargeVat'] === true) {
            echo '<p><strong>'.esc_html__('VAT is charged at checkout.', 'eurocross-ai').'</strong></p>';
        }

        if (isset($result['rate']) && $result['rate'] !== null) {
            echo '<p>'.esc_html(
                sprintf(
                    /* translators: %s: VAT rate returned by the API. */
                    __('Rate: %s%%', 'eurocross-ai'),
                    sanitize_text_field((string) $result['rate'])
                )
            ).'</p>';
        }

        if (! empty($result['reason'])) {
            echo '<p>'.esc_html(sanitize_text_field((string) $result['reason'])).'</p>';
        }

        echo '</div>';
    }

    /**
     * Store the VAT treatment on the order.
     *
     * @param  int  $order_id  Order ID.
     * @return void
     */
    public function save_order_meta($order_id)
    {
        $order_id = absint($order_id);
        if (! $order_id) {
            return;
        }

        if (function_exists('WC') && WC()->checkout()) {
            $posted = WC()->checkout()->get_posted_data();
            $vat_number = isset($posted['billing_eurocross_vat_number']) ? eurocross_ai_sanitize_vat_number($posted['billing_eurocross_vat_number']) : '';
            if ($vat_number !== '') {
                update_post_meta($order_id, '_eurocross_vat_number', $vat_number);
            }
        }

        if (function_exists('WC') && WC()->session) {
            $result = WC()->session->get('eurocross_vat_result');
            if (is_array($result) && ! empty($result)) {
                update_post_meta($order_id, '_eurocross_vat_result', eurocross_ai_sanitize_response_data($result));
            }
            WC()->session->set('eurocross_vat_result', null);
        }
    }

    /**
     * Schedule background translation work after a product is created or saved.
     *
     * The stored fingerprint prevents repeated saves of unchanged content from
     * consuming quota. Each fingerprint produces one scheduled event.
     *
     * @param  int  $product_id  Product ID.
     * @return void
     */
    public function schedule_product_translation($product_id)
    {
        $product_id = absint($product_id);
        if ($product_id === 0 || isset(self::$guarded_product_ids[$product_id])) {
            return;
        }

        if (! eurocross_ai_is_enabled('eurocross_auto_translate_on_save')) {
            return;
        }

        $source = eurocross_ai_sanitize_language_code(eurocross_ai_get_option('eurocross_locale'));
        $targets = $this->target_languages();
        if ($source === '' || empty($targets)) {
            return;
        }

        $fingerprint = $this->product_fingerprint($product_id);
        if ($fingerprint === '') {
            return;
        }

        if (get_post_meta($product_id, '_eurocross_translation_fingerprint', true) === $fingerprint) {
            return;
        }

        if (wp_next_scheduled(EUROCROSS_AI_PRODUCT_TRANSLATION_HOOK, [$product_id, $fingerprint]) !== false) {
            return;
        }

        update_post_meta($product_id, '_eurocross_translation_fingerprint', $fingerprint);
        wp_schedule_single_event(time() + MINUTE_IN_SECONDS, EUROCROSS_AI_PRODUCT_TRANSLATION_HOOK, [$product_id, $fingerprint]);
    }

    /**
     * Queue every configured target language for one saved product.
     *
     * @param  int  $product_id  Product ID.
     * @param  string  $fingerprint  Fingerprint captured when the job was scheduled.
     * @return void
     */
    public function run_scheduled_product_translation($product_id, $fingerprint = '')
    {
        $product_id = absint($product_id);
        if ($product_id === 0 || isset(self::$guarded_product_ids[$product_id])) {
            return;
        }

        if (! eurocross_ai_is_enabled('eurocross_auto_translate_on_save')) {
            return;
        }

        if ($fingerprint !== '' && get_post_meta($product_id, '_eurocross_translation_fingerprint', true) !== $fingerprint) {
            return;
        }

        $source = eurocross_ai_sanitize_language_code(eurocross_ai_get_option('eurocross_locale'));
        $targets = $this->target_languages();
        $items = $this->translation_items($product_id);
        if ($source === '' || empty($targets) || empty($items)) {
            return;
        }

        self::$guarded_product_ids[$product_id] = 1;

        try {
            foreach ($targets as $target) {
                $response = eurocross_ai_get_api()->bulk_translations($items, $source, $target);
                if (is_wp_error($response)) {
                    eurocross_ai_log('Scheduled product translation failed', [
                        'product_id' => $product_id,
                        'target' => $target,
                        'error' => $response->get_error_message(),
                    ]);

                    continue;
                }

                foreach ((array) (isset($response['data']) ? $response['data'] : []) as $record) {
                    self::store_translation_record($record);
                }
            }
        } finally {
            unset(self::$guarded_product_ids[$product_id]);
        }
    }

    /**
     * Mirror a SaaS translation record into local product meta.
     *
     * Only product records are mirrored. Translations are keyed by target
     * language and field so a single product can serve every configured locale.
     *
     * @param  array<string,mixed>  $record  Translation record returned by the API.
     * @return void
     */
    public static function store_translation_record($record)
    {
        if (! is_array($record)) {
            return;
        }

        $source_type = sanitize_key((string) (isset($record['source_type']) ? $record['source_type'] : ''));
        if ($source_type !== 'product') {
            return;
        }

        $product_id = absint(isset($record['source_id']) ? $record['source_id'] : 0);
        $field = sanitize_key((string) (isset($record['field']) ? $record['field'] : ''));
        $target = eurocross_ai_sanitize_language_code(isset($record['target_language']) ? $record['target_language'] : '');
        if ($product_id === 0 || $field === '' || $target === '') {
            return;
        }

        $status = sanitize_key((string) (isset($record['status']) ? $record['status'] : 'pending'));
        $text = (string) (isset($record['translated_text']) ? $record['translated_text'] : '');
        if ($status === 'completed' && trim(wp_strip_all_tags($text)) === '') {
            return;
        }

        $stored = get_post_meta($product_id, '_eurocross_translations', true);
        if (! is_array($stored)) {
            $stored = [];
        }

        $stored[$target.'|'.$field] = [
            'id' => absint(isset($record['id']) ? $record['id'] : 0),
            'field' => $field,
            'target_language' => $target,
            'source_language' => eurocross_ai_sanitize_language_code(isset($record['source_language']) ? $record['source_language'] : ''),
            'status' => $status,
            'text' => $status === 'completed' ? $text : '',
            'source_text' => sanitize_text_field((string) (isset($record['source_text']) ? $record['source_text'] : '')),
            'manually_edited' => ! empty($record['manually_edited']) ? 1 : 0,
            'updated_at' => time(),
        ];

        update_post_meta($product_id, '_eurocross_translations', $stored);
    }

    /**
     * Refresh locally stored product translations from the SaaS API.
     *
     * @return int Number of records stored.
     */
    public static function sync_product_translations()
    {
        if (trim((string) eurocross_ai_get_option('eurocross_api_key')) === '') {
            return 0;
        }

        $api = eurocross_ai_get_api();
        $stored = 0;

        for ($page = 1; $page <= 5; $page++) {
            $response = $api->translations([
                'status' => 'completed',
                'source_type' => 'product',
                'per_page' => 100,
                'page' => $page,
            ]);

            if (is_wp_error($response)) {
                eurocross_ai_log('Product translation sync failed', ['error' => $response->get_error_message()]);

                break;
            }

            $records = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
            foreach ($records as $record) {
                self::store_translation_record($record);
                $stored++;
            }

            $meta = isset($response['meta']) && is_array($response['meta']) ? $response['meta'] : [];
            $last_page = absint(isset($meta['last_page']) ? $meta['last_page'] : 1);
            if ($last_page < 1) {
                $last_page = 1;
            }

            if ($page >= min($last_page, 5) || empty($records)) {
                break;
            }
        }

        return $stored;
    }

    /**
     * Return a stored translation for one product field and locale.
     *
     * @param  int  $product_id  Product ID.
     * @param  string  $field  Translation field.
     * @param  string  $target  Target language code.
     * @return string
     */
    public static function get_stored_translation($product_id, $field, $target)
    {
        $product_id = absint($product_id);
        if ($product_id === 0 || $field === '' || $target === '') {
            return '';
        }

        $stored = get_post_meta($product_id, '_eurocross_translations', true);
        $key = $target.'|'.$field;
        if (! is_array($stored) || ! isset($stored[$key]) || ! is_array($stored[$key])) {
            return '';
        }

        $entry = $stored[$key];
        if (! isset($entry['status']) || $entry['status'] !== 'completed' || empty($entry['text'])) {
            return '';
        }

        $text = (string) $entry['text'];

        return in_array($field, ['description', 'short_description'], true) ? wp_kses_post($text) : sanitize_text_field($text);
    }

    /**
     * Serve stored Yoast and Rank Math translations for the active locale.
     *
     * @param  mixed  $value  Original meta value.
     * @param  int  $object_id  Post ID.
     * @param  string  $meta_key  Meta key.
     * @param  bool  $single  Whether a single value was requested.
     * @return mixed
     */
    public function translate_seo_post_meta($value, $object_id, $meta_key, $single)
    {
        $seo_keys = ['_yoast_wpseo_title', 'rank_math_title', '_yoast_wpseo_metadesc', 'rank_math_description'];
        if (is_admin() || ! in_array($meta_key, $seo_keys, true)) {
            return $value;
        }

        $product_id = absint($object_id);
        $target = eurocross_ai_get_current_locale();
        $source = eurocross_ai_sanitize_language_code(eurocross_ai_get_option('eurocross_locale'));
        if ($product_id === 0 || $target === '' || $target === $source) {
            return $value;
        }

        $field = in_array($meta_key, ['_yoast_wpseo_title', 'rank_math_title'], true) ? 'seo_title' : 'seo_description';
        $translated = self::get_stored_translation($product_id, $field, $target);
        if ($translated === '') {
            return $value;
        }

        return $single ? $translated : [$translated];
    }

    /**
     * Replace the displayed product slug with the translation for the active locale.
     *
     * @param  string  $permalink  Original permalink.
     * @param  WP_Post|null  $post  Post object.
     * @param  bool  $leavename  Whether the slug should be left untouched.
     * @return string
     */
    public function translate_product_permalink($permalink, $post = null, $leavename = false)
    {
        if (is_admin() || $leavename || ! is_string($permalink) || $permalink === '') {
            return $permalink;
        }

        $product_id = isset($post->ID) ? absint($post->ID) : 0;
        $original = isset($post->post_name) ? (string) $post->post_name : '';
        $target = eurocross_ai_get_current_locale();
        $source = eurocross_ai_sanitize_language_code(eurocross_ai_get_option('eurocross_locale'));
        if ($product_id === 0 || $original === '' || $target === '' || $target === $source) {
            return $permalink;
        }

        $slug = sanitize_title(self::get_stored_translation($product_id, 'slug', $target));
        if ($slug === '' || $slug === $original) {
            return $permalink;
        }

        $path = wp_parse_url($permalink, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return $permalink;
        }

        $suffix = '/'.$original;
        if (substr($path, -strlen($suffix)) !== $suffix) {
            return $permalink;
        }

        $new_path = substr($path, 0, -strlen($suffix)).'/'.$slug;
        if (substr($path, -1) === '/') {
            $new_path .= '/';
        }

        return str_replace($path, $new_path, $permalink);
    }

    /**
     * Translate the body of an order email.
     *
     * @param  string  $content  Email content.
     * @param  WC_Email  $email  Email object.
     * @return string
     */
    public function translate_order_email($content, $email = null)
    {
        if (! is_string($content) || $content === '' || is_admin()) {
            return $content;
        }

        $target = $this->get_email_locale($email);
        $source = eurocross_ai_get_option('eurocross_locale');
        if ($target === '' || $target === $source || strlen($content) > 12000) {
            return $content;
        }

        $translated = eurocross_ai_translate_text($content, $source, $target, 'order_email');
        if ($translated === $content) {
            return $content;
        }

        return wp_kses_post($translated);
    }

    /**
     * Translate an order email subject.
     *
     * @param  string  $subject  Email subject.
     * @param  WC_Email  $email  Email object.
     * @return string
     */
    public function translate_email_subject($subject, $email = null)
    {
        if (! is_string($subject) || $subject === '') {
            return $subject;
        }

        $target = $this->get_email_locale($email);
        $source = eurocross_ai_get_option('eurocross_locale');
        if ($target === '' || $target === $source) {
            return $subject;
        }

        return sanitize_text_field(eurocross_ai_translate_text($subject, $source, $target, 'order_email_subject'));
    }

    /**
     * Determine the locale for an email recipient.
     *
     * @param  WC_Email|null  $email  Email object.
     * @return string
     */
    private function get_email_locale($email)
    {
        $recipient = '';
        if (is_object($email) && isset($email->recipient)) {
            $recipient = sanitize_email((string) $email->recipient);
        }

        if ($recipient !== '') {
            $user = get_user_by('email', $recipient);
            if ($user) {
                $locale = eurocross_ai_sanitize_language_code(get_user_meta($user->ID, 'eurocross_locale', true));
                if ($locale !== '') {
                    return $locale;
                }
            }
        }

        return eurocross_ai_get_current_locale();
    }

    /**
     * Configured target languages excluding the source language.
     *
     * @return array<int,string>
     */
    private function target_languages()
    {
        $source = eurocross_ai_sanitize_language_code(eurocross_ai_get_option('eurocross_locale'));
        $targets = [];

        foreach ((array) eurocross_ai_get_option('eurocross_target_languages') as $code) {
            $code = eurocross_ai_sanitize_language_code($code);
            if ($code !== '' && $code !== $source && ! in_array($code, $targets, true)) {
                $targets[] = $code;
            }
        }

        return $targets;
    }

    /**
     * Build a stable fingerprint for the translatable fields of a product.
     *
     * @param  int  $product_id  Product ID.
     * @return string
     */
    private function product_fingerprint($product_id)
    {
        $post = get_post($product_id);
        if (! $post || $post->post_type !== 'product') {
            return '';
        }

        $payload = [
            'title' => (string) $post->post_title,
            'short_description' => (string) $post->post_excerpt,
            'description' => (string) $post->post_content,
        ];

        if (eurocross_ai_is_enabled('eurocross_translate_seo')) {
            $payload['seo_title'] = $this->seo_meta_value($product_id, 'title');
            $payload['seo_description'] = $this->seo_meta_value($product_id, 'description');
        }

        $has_content = false;
        foreach ($payload as $value) {
            if (trim(wp_strip_all_tags((string) $value)) !== '') {
                $has_content = true;

                break;
            }
        }

        return $has_content ? md5(wp_json_encode($payload)) : '';
    }

    /**
     * Read the first configured SEO title or description from post meta.
     *
     * @param  int  $product_id  Product ID.
     * @param  string  $kind  Either title or description.
     * @return string
     */
    private function seo_meta_value($product_id, $kind)
    {
        $keys = $kind === 'title'
            ? ['_yoast_wpseo_title', 'rank_math_title']
            : ['_yoast_wpseo_metadesc', 'rank_math_description'];

        foreach ($keys as $key) {
            $value = get_post_meta($product_id, $key, true);
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Build translation items for every non-empty translatable product field.
     *
     * @param  int  $product_id  Product ID.
     * @return array<int,array<string,string>>
     */
    private function translation_items($product_id)
    {
        $post = get_post($product_id);
        if (! $post) {
            return [];
        }

        $items = [
            ['text' => (string) $post->post_title, 'field' => 'title'],
            ['text' => (string) $post->post_excerpt, 'field' => 'short_description'],
            ['text' => (string) $post->post_content, 'field' => 'description'],
        ];

        if (eurocross_ai_is_enabled('eurocross_translate_seo')) {
            $items[] = ['text' => $this->seo_meta_value($product_id, 'title'), 'field' => 'seo_title'];
            $items[] = ['text' => $this->seo_meta_value($product_id, 'description'), 'field' => 'seo_description'];
        }

        if (eurocross_ai_is_enabled('eurocross_translate_slug')) {
            $items[] = ['text' => (string) $post->post_title, 'field' => 'slug'];
        }

        $result = [];
        foreach ($items as $item) {
            if (trim(wp_strip_all_tags($item['text'])) === '') {
                continue;
            }

            $result[] = [
                'text' => $item['text'],
                'source_type' => 'product',
                'source_id' => (string) $product_id,
                'field' => $item['field'],
            ];
        }

        return $result;
    }

    /**
     * Resolve a product ID from the values passed to the render filters.
     *
     * @param  WC_Product|WP_Post|int|null  $product  Product object or ID.
     * @return int
     */
    private function resolve_product_id($product)
    {
        if (is_object($product) && method_exists($product, 'get_id')) {
            return absint($product->get_id());
        }
        if (is_object($product) && isset($product->ID)) {
            return absint($product->ID);
        }
        if (is_numeric($product)) {
            return absint($product);
        }

        return absint(get_the_ID());
    }

    /**
     * Translate a value only when a different target locale is active.
     *
     * Stored translations are used first so completed work is never re-billed.
     *
     * @param  string  $text  Text to translate.
     * @param  string  $context  Translation context.
     * @param  WC_Product|int|null  $product  Product object or ID.
     * @return string
     */
    private function maybe_translate($text, $context, $product = null)
    {
        if (! is_string($text) || $text === '' || is_admin()) {
            return $text;
        }

        if (absint(apply_filters('eurocross_ai_disable_product_translation', 0, $product))) {
            return $text;
        }

        $source = eurocross_ai_get_option('eurocross_locale');
        $target = eurocross_ai_get_current_locale();

        if ($source === '' || $target === '' || $source === $target) {
            return $text;
        }

        $fields = [
            'product_title' => 'title',
            'product_short_description' => 'short_description',
            'product_description' => 'description',
        ];
        if (isset($fields[$context])) {
            $stored = self::get_stored_translation($this->resolve_product_id($product), $fields[$context], $target);
            if ($stored !== '') {
                return $stored;
            }
        }

        return eurocross_ai_translate_text($text, $source, $target, $context);
    }
}
