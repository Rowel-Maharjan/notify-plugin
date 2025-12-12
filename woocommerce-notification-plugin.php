<?php

/*
Plugin Name: NotifyStore - Order Notifications
Description: Custom plugin to manage store webhooks and forward notifications.
Version: 1.0.0
Author: Nexbil
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'includes/class-webhook-handler.php';
require_once plugin_dir_path(__FILE__) . 'views/class-admin-page.php';

add_action('init', function () {
    if (class_exists('WC_Admin_Page')) {
        new WC_Admin_Page();
    }
});
