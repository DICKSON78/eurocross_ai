<?php

/**
 * Uninstall cleanup for EuroCross AI.
 */
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Removes every piece of data created by the plugin.
 */
class EuroCross_AI_Uninstall
{
    /**
     * Run the cleanup.
     *
     * @return void
     */
    public static function handle()
    {
        global $wpdb;

        if (! defined('WP_UNINSTALL_PLUGIN') || ! WP_UNINSTALL_PLUGIN) {
            return;
        }

        self::delete_options($wpdb);
        self::delete_transients($wpdb);
        self::drop_custom_tables($wpdb);

        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook('eurocross_ai_daily_sync');
        }

        if (function_exists('flush_rewrite_rules')) {
            flush_rewrite_rules();
        }
    }

    /**
     * Delete all plugin options, including options created by future versions.
     *
     * @param  wpdb  $wpdb  Database abstraction object.
     * @return void
     */
    private static function delete_options($wpdb)
    {
        $option_names = [
            'eurocross_api_url',
            'eurocross_api_key',
            'eurocross_site_secret',
            'eurocross_locale',
            'eurocross_plan',
            'eurocross_plan_limits',
            'eurocross_last_plan_sync',
            'eurocross_target_languages',
            'eurocross_use_oss',
            'eurocross_vat_number',
            'eurocross_default_country',
            'eurocross_chatbot_enabled',
            'eurocross_translate_product_titles',
            'eurocross_translate_product_descriptions',
            'eurocross_debug',
            'eurocross_trial_data',
            'eurocross_connection_status',
            'eurocross_last_sync',
        ];

        $prefix = $wpdb->esc_like('eurocross_').'%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $found = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $prefix));

        if (is_array($found)) {
            $option_names = array_unique(array_merge($option_names, $found));
        }

        foreach ($option_names as $option_name) {
            delete_option($option_name);
            if (is_multisite()) {
                delete_site_option($option_name);
            }
        }

        delete_user_meta_by_key('eurocross_locale');
        delete_user_meta_by_key('eurocross_notice_dismissed');
    }

    /**
     * Delete cached languages, translations, VAT results and status transients.
     *
     * @param  wpdb  $wpdb  Database abstraction object.
     * @return void
     */
    private static function delete_transients($wpdb)
    {
        $known_transients = [
            'eurocross_languages',
            'eurocross_language_payload',
            'eurocross_dashboard_data',
            'eurocross_connection_status',
            'eurocross_chatbot_status',
        ];

        foreach ($known_transients as $transient) {
            delete_transient($transient);
            if (is_multisite()) {
                delete_site_transient($transient);
            }
        }

        $patterns = [
            $wpdb->esc_like('_transient_eurocross_').'%',
            $wpdb->esc_like('_transient_timeout_eurocross_').'%',
        ];

        if (is_multisite()) {
            $patterns[] = $wpdb->esc_like('_site_transient_eurocross_').'%';
            $patterns[] = $wpdb->esc_like('_site_transient_timeout_eurocross_').'%';
        }

        foreach ($patterns as $pattern) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $rows = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern));
            if (! is_array($rows)) {
                continue;
            }
            foreach ($rows as $option_name) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s", $option_name));
            }
        }

        wp_cache_flush();
    }

    /**
     * Drop plugin tables if a future version created any.
     *
     * @param  wpdb  $wpdb  Database abstraction object.
     * @return void
     */
    private static function drop_custom_tables($wpdb)
    {
        $tables = [
            $wpdb->prefix.'eurocross_translations',
            $wpdb->prefix.'eurocross_cache',
        ];

        foreach ($tables as $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
            if ($found === $table) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
                $wpdb->query("DROP TABLE IF EXISTS {$table}");
            }
        }
    }
}
