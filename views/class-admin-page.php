<?php

if (!defined("ABSPATH")) {
    exit();
}

class WC_Notification_Admin_Page
{
    private static $option_name = "wc_notification_settings";

    public static function init()
    {
        add_action("admin_menu", [__CLASS__, "add_menu"]);
        add_action("admin_enqueue_scripts", [__CLASS__, "enqueue_scripts"]);

        add_action("wp_ajax_wc_notification_toggle_forwarding", [
            __CLASS__,
            "ajax_toggle_forwarding",
        ]);
        add_action("wp_ajax_wc_notification_toggle_topic", [
            __CLASS__,
            "ajax_toggle_topic",
        ]);
        add_action("wp_ajax_wc_notification_save_api_config", [
            __CLASS__,
            "ajax_save_api_config",
        ]);
        add_action("wp_ajax_wc_notification_test_connection", [
            __CLASS__,
            "ajax_test_connection",
        ]);
    }

    public static function add_menu()
    {
        $hook = add_submenu_page(
            "woocommerce",
            "Notification Settings",
            "Notifications",
            "manage_options",
            "wc-notification-plugin",
            [__CLASS__, "render_page"],
        );

        add_action("load-$hook", function () {
            add_action("admin_enqueue_scripts", [__CLASS__, "enqueue_scripts"]);
        });
    }

    public static function enqueue_scripts()
    {
        $assets_url = plugin_dir_url(__FILE__);
        $assets_path = plugin_dir_path(__FILE__);

        $js_rel = "js/admin-page.js";
        $css_rel = "css/admin-page.css";

        $js_ver = file_exists($assets_path . $js_rel)
            ? filemtime($assets_path . $js_rel)
            : "1.0.2";
        $css_ver = file_exists($assets_path . $css_rel)
            ? filemtime($assets_path . $css_rel)
            : "1.0.2";

        wp_enqueue_script(
            "wc-notif-admin",
            $assets_url . $js_rel,
            ["jquery"],
            $js_ver,
            true,
        );

        wp_localize_script("wc-notif-admin", "WCNotif", [
            "ajax_url" => admin_url("admin-ajax.php"),
            "nonce" => wp_create_nonce("wc_notification_nonce"),
            "strings" => [
                "confirm_disable" =>
                    "Are you sure you want to disable webhook forwarding?",
                "unsaved_changes" =>
                    "You have unsaved changes. Are you sure you want to leave?",
                "testing_connection" => "Testing connection...",
                "connection_success" => "Connection test successful!",
                "connection_failed" => "Connection test failed",
            ],
        ]);

        wp_enqueue_style(
            "wc-notif-admin-css",
            $assets_url . $css_rel,
            [],
            $css_ver,
        );
    }

    public static function render_page()
    {
        $defaults = [
            "forward_webhooks" => 0,
            "forward_order_created" => 0,
            "forward_order_cancelled" => 0,
            "api_url" => "",
            "api_token" => "",
            "last_test_date" => "",
            "webhook_count" => 0,
        ];

        $settings = wp_parse_args(
            get_option(self::$option_name, []),
            $defaults,
        );

        $actual_webhook_status = ["order_created" => 0, "order_cancelled" => 0];
        if (class_exists("Webhook_Handler")) {
            $actual_webhook_status = Webhook_Handler::get_actual_webhook_status();
        }

        $api_configured =
            !empty($settings["api_url"]) && !empty($settings["api_token"]);

        if (!class_exists("WooCommerce")) {
            self::render_woocommerce_missing_notice();
            return;
        }
        ?>
        <div class="wrap wc-notification-admin">
            <h1>WooCommerce Notification Plugin</h1>

            <?php if (!$api_configured): ?>
                <div class="notice notice-warning">
                    <p><strong>Setup Required:</strong> Please configure your API URL and Token below to enable webhook forwarding.</p>
                </div>
            <?php endif; ?>

            <div class="card">
                <h2>Master Control</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="enable_forwarding">Enable Forwarding</label>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">Enable webhook forwarding</legend>
                                <label for="enable_forwarding">
                                    <input
                                        type="checkbox"
                                        id="enable_forwarding"
                                        name="enable_forwarding"
                                        value="1"
                                        <?php checked(
                                            1,
                                            $settings["forward_webhooks"],
                                        ); ?>
                                        <?php disabled(!$api_configured); ?>
                                    />
                                    Enable all webhook forwarding
                                </label>
                                <?php if (!$api_configured): ?>
                                    <p class="description error-text">Configure API settings below to enable forwarding</p>
                                <?php else: ?>
                                    <p class="description">Master switch to enable/disable all webhook notifications</p>
                                <?php endif; ?>

                                <?php if ($settings["forward_webhooks"]): ?>
                                    <div class="status-info">
                                        <span class="status-indicator active"></span>
                                        <span>Forwarding is currently <strong>active</strong></span>
                                        <?php if (
                                            $settings["last_test_date"]
                                        ): ?>
                                            <br><small>Last tested: <?php echo esc_html(
                                                date(
                                                    "M j, Y g:i A",
                                                    strtotime(
                                                        $settings[
                                                            "last_test_date"
                                                        ],
                                                    ),
                                                ),
                                            ); ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="card">
                <h2>Webhook Topics</h2>
                <p class="description">Select which events should trigger webhook notifications to your API.</p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="forward_order_created">Order Created</label>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">Forward order created events</legend>
                                <label for="forward_order_created">
                                    <input
                                        type="checkbox"
                                        class="topic-toggle"
                                        id="forward_order_created"
                                        name="forward_order_created"
                                        value="1"
                                        <?php checked(
                                            1,
                                            $actual_webhook_status[
                                                "order_created"
                                            ],
                                        ); ?>
                                        <?php disabled(
                                            !$settings["forward_webhooks"],
                                        ); ?>
                                        data-current-status="<?php echo esc_attr(
                                            $actual_webhook_status[
                                                "order_created"
                                            ],
                                        ); ?>"
                                        data-topic="order_created"
                                    />
                                    Send notifications when new orders are created
                                </label>
                                <p class="description">Triggers when a customer places a new order</p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="forward_order_cancelled">Order Cancelled</label>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">Forward order cancelled events</legend>
                                <label for="forward_order_cancelled">
                                    <input
                                        type="checkbox"
                                        class="topic-toggle"
                                        id="forward_order_cancelled"
                                        name="forward_order_cancelled"
                                        value="1"
                                        <?php checked(
                                            1,
                                            $actual_webhook_status[
                                                "order_cancelled"
                                            ],
                                        ); ?>
                                        <?php disabled(
                                            !$settings["forward_webhooks"],
                                        ); ?>
                                        data-current-status="<?php echo esc_attr(
                                            $actual_webhook_status[
                                                "order_cancelled"
                                            ],
                                        ); ?>"
                                        data-topic="order_cancelled"
                                    />
                                    Send notifications when orders are cancelled
                                </label>
                                <p class="description">Triggers when an order status changes to cancelled</p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="card">
                <h2>API Configuration</h2>
                <p class="description">Configure your external API endpoint that will receive webhook notifications.</p>

                <form id="api-config-form" method="post" action="">
                    <?php wp_nonce_field(
                        "wc_notification_api_config",
                        "api_config_nonce",
                    ); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="api_url">API Endpoint URL</label>
                            </th>
                            <td>
                                <input
                                    type="url"
                                    id="api_url"
                                    name="api_url"
                                    value="<?php echo esc_attr(
                                        $settings["api_url"],
                                    ); ?>"
                                    class="regular-text api-config-field"
                                    placeholder="https://your-api.com/webhook-endpoint"
                                    disabled
                                />
                                <p class="description">The URL where webhook notifications will be sent</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="api_token">Authentication Token</label>
                            </th>
                            <td>
                                <input
                                    type="text"
                                    id="api_token"
                                    name="api_token"
                                    value="<?php echo esc_attr(
                                        $settings["api_token"],
                                    ); ?>"
                                    class="regular-text api-config-field"
                                    placeholder="Enter your API authentication token"
                                    disabled
                                />
                                <p class="description">Token used to authenticate webhook requests (sent in Authorization header)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Actions</th>
                            <td class="api-actions">
                                <button type="button" id="toggle-api-edit" class="button">
                                    <span class="dashicons dashicons-edit"></span> Edit Configuration
                                </button>
                                <button type="button" id="save-api" class="button button-primary" disabled>
                                    <span class="dashicons dashicons-yes"></span> Save Changes
                                </button>
                                <button type="button" id="cancel-api-edit" class="button" disabled style="display:none;">
                                    <span class="dashicons dashicons-no"></span> Cancel
                                </button>
                                <button type="button" id="test-connection" class="button" <?php disabled(
                                    !$api_configured,
                                ); ?>>
                                    <span class="dashicons dashicons-admin-plugins"></span> Test Connection
                                </button>
                            </td>
                        </tr>
                    </table>
                </form>
            </div>

            <?php if ($api_configured): ?>
            <div class="card">
                <h2>Status & Statistics</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Current Status</th>
                        <td>
                            <div class="status-grid">
                                <div class="status-item">
                                    <span class="status-label">API Configuration:</span>
                                    <span class="status-value">
                                        <span class="status-indicator <?php echo $api_configured
                                            ? "active"
                                            : "inactive"; ?>"></span>
                                        <?php echo $api_configured
                                            ? "Configured"
                                            : "Not Configured"; ?>
                                    </span>
                                </div>
                                <div class="status-item">
                                    <span class="status-label">Forwarding Status:</span>
                                    <span class="status-value">
                                        <span class="status-indicator <?php echo $settings[
                                            "forward_webhooks"
                                        ]
                                            ? "active"
                                            : "inactive"; ?>"></span>
                                        <?php echo $settings["forward_webhooks"]
                                            ? "Enabled"
                                            : "Disabled"; ?>
                                    </span>
                                </div>
                                <div class="status-item">
                                    <span class="status-label">Active Webhooks:</span>
                                    <span class="status-value">
                                        <?php echo array_sum(
                                            $actual_webhook_status,
                                        ); ?> of 2 topics
                                    </span>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>

            <div id="wc-notif-toast" class="wc-toast" role="alert" aria-live="polite"></div>
        </div>

        <?php
    }

    public static function ajax_toggle_forwarding()
    {
        if (!check_ajax_referer("wc_notification_nonce", "nonce", false)) {
            wp_send_json_error(["message" => "Security check failed"]);
        }

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => "Insufficient permissions"]);
        }

        $enabled = filter_var(
            $_POST["enabled"] ?? false,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE,
        );
        $enabled = (bool) $enabled;

        $settings = get_option(self::$option_name, []);

        if (
            $enabled &&
            (empty($settings["api_url"]) || empty($settings["api_token"]))
        ) {
            wp_send_json_error([
                "message" => "Please configure API URL and Token first",
            ]);
        }

        $settings["forward_webhooks"] = $enabled ? 1 : 0;
        update_option(self::$option_name, $settings);

        if (class_exists("Webhook_Handler")) {
            if ($enabled) {
                $result = Webhook_Handler::enable_all();
                if (!$result) {
                    $settings["forward_webhooks"] = 0;
                    update_option(self::$option_name, $settings);
                    wp_send_json_error([
                        "message" =>
                            "Failed to enable webhooks. Please check your API configuration.",
                    ]);
                }
                $message = "Webhook forwarding enabled successfully";
            } else {
                $result = Webhook_Handler::disable_all();
                if (!$result) {
                    wp_send_json_error([
                        "message" => "Failed to disable webhooks",
                    ]);
                }
                $message = "Webhook forwarding disabled successfully";
            }
        } else {
            wp_send_json_error(["message" => "Webhook handler not available"]);
        }

        wp_send_json_success([
            "message" => $message,
            "enabled" => $enabled,
        ]);
    }

    public static function ajax_toggle_topic()
    {
        if (!check_ajax_referer("wc_notification_nonce", "nonce", false)) {
            wp_send_json_error(["message" => "Security check failed"]);
        }

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => "Insufficient permissions"]);
        }

        $topic = sanitize_key($_POST["topic"] ?? "");

        $enabled = filter_var(
            $_POST["enabled"] ?? false,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE,
        );
        $enabled = (bool) $enabled;

        $current_status = isset($_POST["current_status"])
            ? (int) $_POST["current_status"]
            : 0;

        if (!in_array($topic, ["order_created", "order_cancelled"], true)) {
            wp_send_json_error(["message" => "Invalid topic specified"]);
        }

        $settings = get_option(self::$option_name, []);

        if (empty($settings["forward_webhooks"])) {
            wp_send_json_error([
                "message" => "Master forwarding must be enabled first",
            ]);
        }

        $setting_key = "forward_" . $topic;
        $settings[$setting_key] = $enabled ? 1 : 0;
        update_option(self::$option_name, $settings);

        if (class_exists("Webhook_Handler")) {
            $result = Webhook_Handler::update_topic($topic, $enabled);
            if (!$result) {
                $settings[$setting_key] = $current_status;
                update_option(self::$option_name, $settings);
                wp_send_json_error(["message" => "Failed to update webhook"]);
            }
        } else {
            wp_send_json_error(["message" => "Webhook handler not available"]);
        }

        $action = $enabled ? "enabled" : "disabled";
        $topic_display = ucwords(str_replace("_", " ", $topic));

        wp_send_json_success([
            "message" => "$topic_display webhook $action successfully",
            "enabled" => $enabled,
        ]);
    }

    public static function ajax_save_api_config()
    {
        if (!check_ajax_referer("wc_notification_nonce", "nonce", false)) {
            wp_send_json_error(["message" => "Security check failed"]);
        }

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => "Insufficient permissions"]);
        }

        $api_url = esc_url_raw($_POST["api_url"] ?? "");
        $api_token = sanitize_text_field($_POST["api_token"] ?? "");

        if (empty($api_url) || !filter_var($api_url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(["message" => "Please enter a valid API URL"]);
        }

        if (empty($api_token) || strlen($api_token) < 3) {
            wp_send_json_error([
                "message" => "API token must be at least 3 characters long",
            ]);
        }

        $settings = get_option(self::$option_name, []);
        $url_changed = ($settings["api_url"] ?? "") !== $api_url;
        $token_changed = ($settings["api_token"] ?? "") !== $api_token;

        $settings["api_url"] = $api_url;
        $settings["api_token"] = $api_token;
        update_option(self::$option_name, $settings);

        $webhook_updated = false;
        if (
            !empty($settings["forward_webhooks"]) &&
            class_exists("Webhook_Handler")
        ) {
            if ($url_changed) {
                $result = Webhook_Handler::update_webhook_urls($api_url);
                if (!$result) {
                    wp_send_json_error([
                        "message" =>
                            "Settings saved but failed to update webhook URLs",
                    ]);
                }
                $webhook_updated = true;
            }

            if ($token_changed) {
                $result = Webhook_Handler::update_webhook_secrets($api_token);
                if (!$result) {
                    wp_send_json_error([
                        "message" =>
                            "Settings saved but failed to update webhook secrets",
                    ]);
                }
                $webhook_updated = true;
            }
        }

        $message = "API configuration saved successfully";
        if ($webhook_updated) {
            $message .= " and existing webhooks updated";
        }

        wp_send_json_success([
            "message" => $message,
            "api_url" => $api_url,
        ]);
    }

    public static function ajax_test_connection()
    {
        if (!check_ajax_referer("wc_notification_nonce", "nonce", false)) {
            wp_send_json_error(["message" => "Security check failed"]);
        }

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => "Insufficient permissions"]);
        }

        $settings = get_option(self::$option_name, []);

        if (empty($settings["api_url"]) || empty($settings["api_token"])) {
            wp_send_json_error([
                "message" => "API configuration is incomplete",
            ]);
        }

        $test_payload = [
            "test" => true,
            "timestamp" => current_time("mysql"),
            "site_url" => get_site_url(),
            "message" =>
                "This is a test webhook from WooCommerce Notification Plugin",
        ];

        $response = wp_remote_post($settings["api_url"], [
            "headers" => [
                "Content-Type" => "application/json",
                "Authorization" => "Bearer " . $settings["api_token"],
                "X-WC-Webhook-Test" => "1",
            ],
            "body" => json_encode($test_payload),
            "timeout" => 30,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error([
                "message" =>
                    "Connection test failed: " . $response->get_error_message(),
            ]);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        $settings["last_test_date"] = current_time("mysql");
        update_option(self::$option_name, $settings);

        if ($response_code >= 200 && $response_code < 300) {
            wp_send_json_success([
                "message" =>
                    "Connection test successful! Response code: " .
                    $response_code,
                "response_code" => $response_code,
                "response_body" => $response_body,
            ]);
        } else {
            wp_send_json_error([
                "message" =>
                    "Connection test failed with response code: " .
                    $response_code,
                "response_code" => $response_code,
                "response_body" => $response_body,
            ]);
        }
    }

    private static function render_woocommerce_missing_notice()
    {
        ?>
        <div class="wrap">
        <h1>WooCommerce Notification Plugin</h1>
        <div class="notice notice-error">
        <p><strong>WooCommerce Required:</strong> This plugin requires WooCommerce to be installed and activated.</p>
        <p><a href="<?php echo admin_url(
            "plugin-install.php?s=woocommerce&tab=search&type=term",
        ); ?>" class="button button-primary">Install WooCommerce</a></p>
        </div>
        </div>
        <?php
    }
}

add_action("plugins_loaded", function () {
    if (is_admin()) {
        WC_Notification_Admin_Page::init();
    }
});
