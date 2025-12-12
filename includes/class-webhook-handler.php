<?php
if (!defined('ABSPATH')) exit;

class Webhook_Handler {
    private static $option_name = 'wc_notification_settings';
    private static $webhook_ids_option = 'wc_notification_webhook_ids';

    public static function enable_all() {
        $settings = get_option(self::$option_name, []);
        
        // Check if forwarding is enabled
        if (empty($settings['forward_webhooks'])) {
            return false;
        }
        
        $ids = get_option(self::$webhook_ids_option, []);
        $api_url = $settings['api_url'] ?? '';
        $api_token = $settings['api_token'] ?? '';
        
        if (!$api_url || !$api_token) {
            return false;
        }
        
        $topics = [];
        if (!empty($settings['forward_order_created'])) {
            $topics[] = 'order.created';
        }
        if (!empty($settings['forward_order_cancelled'])) {
            $topics[] = 'order.cancelled';
        }
        
        $success = true;
        foreach ($topics as $topic) {
            $webhook_id = self::create_or_update_webhook($topic, $api_url, $api_token, $ids[$topic] ?? null);
            if ($webhook_id) {
                $ids[$topic] = $webhook_id;
            } else {
                $success = false;
                error_log("Failed to create/update webhook for topic: $topic");
            }
        }
        
        update_option(self::$webhook_ids_option, $ids);
        return $success;
    }

    public static function disable_all() {
        $ids = get_option(self::$webhook_ids_option, []);
        $success = true;
        
        foreach ($ids as $topic => $id) {
            if ($id && !self::delete_webhook($id)) {
                $success = false;
                error_log("Failed to delete webhook for topic: $topic (ID: $id)");
            }
        }
        
        delete_option(self::$webhook_ids_option);
        return $success;
    }

    public static function update_topic($topic, $enabled) {
        $settings = get_option(self::$option_name, []);
        
        // Check if forwarding is globally enabled
        if (empty($settings['forward_webhooks'])) {
            return false;
        }
        
        $ids = get_option(self::$webhook_ids_option, []);
        $api_url = $settings['api_url'] ?? '';
        $api_token = $settings['api_token'] ?? '';
        
        if (!$api_url || !$api_token) {
            return false;
        }
        
        $map = ['order_created' => 'order.created', 'order_cancelled' => 'order.cancelled'];
        if (!isset($map[$topic])) {
            return false;
        }
        
        $wc_topic = $map[$topic];
        
        if ($enabled) {
            $webhook_id = self::create_or_update_webhook($wc_topic, $api_url, $api_token, $ids[$wc_topic] ?? null);
            if ($webhook_id) {
                $ids[$wc_topic] = $webhook_id;
                update_option(self::$webhook_ids_option, $ids);
                return true;
            }
            return false;
        } else {
            if (!empty($ids[$wc_topic])) {
                if (self::delete_webhook($ids[$wc_topic])) {
                    unset($ids[$wc_topic]);
                    update_option(self::$webhook_ids_option, $ids);
                    return true;
                }
                return false;
            }
            return true; // Already disabled
        }
    }

    public static function update_webhook_urls($new_url) {
        if (!filter_var($new_url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        $ids = get_option(self::$webhook_ids_option, []);
        $settings = get_option(self::$option_name, []);
        $token = $settings['api_token'] ?? '';
        
        if (!$token) {
            return false;
        }
        
        $success = true;
        foreach ($ids as $topic => $id) {
            if ($id) {
                $updated_id = self::create_or_update_webhook($topic, $new_url, $token, $id);
                if (!$updated_id) {
                    $success = false;
                    error_log("Failed to update URL for webhook topic: $topic (ID: $id)");
                } else {
                    $ids[$topic] = $updated_id;
                }
            }
        }
        
        if ($success) {
            update_option(self::$webhook_ids_option, $ids);
        }
        
        return $success;
    }

    public static function update_webhook_secrets($new_token) {
        if (empty($new_token) || strlen($new_token) < 3) {
            return false;
        }
        
        $ids = get_option(self::$webhook_ids_option, []);
        $settings = get_option(self::$option_name, []);
        $url = $settings['api_url'] ?? '';
        
        if (!$url) {
            return false;
        }
        
        $success = true;
        foreach ($ids as $topic => $id) {
            if ($id) {
                $updated_id = self::create_or_update_webhook($topic, $url, $new_token, $id);
                if (!$updated_id) {
                    $success = false;
                    error_log("Failed to update secret for webhook topic: $topic (ID: $id)");
                } else {
                    $ids[$topic] = $updated_id;
                }
            }
        }
        
        if ($success) {
            update_option(self::$webhook_ids_option, $ids);
        }
        
        return $success;
    }

    public static function get_actual_webhook_status() {
        $ids = get_option(self::$webhook_ids_option, []);
        $out = ['order_created' => 0, 'order_cancelled' => 0];
        
        foreach ($ids as $topic => $id) {
            if ($id && self::webhook_exists($id)) {
                if ($topic === 'order.created') {
                    $out['order_created'] = 1;
                }
                if ($topic === 'order.cancelled') {
                    $out['order_cancelled'] = 1;
                }
            }
        }
        
        return $out;
    }

    private static function create_or_update_webhook($topic, $url, $secret, $existing_id = null) {
        try {
            // Try to update existing webhook first
            if ($existing_id) {
                $webhook = new WC_Webhook($existing_id);
                if ($webhook->get_id()) {
                    $webhook->set_delivery_url($url);
                    $webhook->set_secret($secret);
                    $webhook->set_status('active');
                    $result = $webhook->save();
                    
                    if ($result) {
                        return $webhook->get_id();
                    }
                }
            }
            
            // Create new webhook
            $webhook = new WC_Webhook();
            $webhook->set_name('Notif ' . $topic);
            $webhook->set_topic($topic);
            $webhook->set_delivery_url($url);
            $webhook->set_secret($secret);
            $webhook->set_status('active');
            $webhook->set_api_version('wp_api_v3');
            $webhook->set_user_id(get_current_user_id());
            
            $result = $webhook->save();
            
            if ($result) {
                return $webhook->get_id();
            }
            
        } catch (Exception $e) {
            error_log("Webhook creation/update failed for topic $topic: " . $e->getMessage());
        }
        
        return false;
    }

    private static function delete_webhook($id) {
        try {
            $webhook = new WC_Webhook($id);
            if ($webhook->get_id()) {
                return $webhook->delete(true); // Use WooCommerce's delete method
            }
        } catch (Exception $e) {
            error_log("Webhook deletion failed for ID $id: " . $e->getMessage());
        }
        
        return false;
    }

    private static function webhook_exists($id) {
        try {
            $webhook = new WC_Webhook($id);
            return (bool) $webhook->get_id();
        } catch (Exception $e) {
            return false;
        }
    }
}