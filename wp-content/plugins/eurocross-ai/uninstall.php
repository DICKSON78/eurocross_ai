<?php

/**
 * EuroCross AI Uninstall
 *
 * Fired when the plugin is uninstalled via WordPress Admin.
 */
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once plugin_dir_path(__FILE__).'includes/class-eurocross-uninstall.php';

EuroCross_AI_Uninstall::handle();
