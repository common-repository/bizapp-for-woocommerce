<?php
if (!defined('ABSPATH')) exit;

class Bizapp_Woocommerce_Webhook_Product extends Bizapp_Woocommerce_Webhook_Base implements Bizapp_Woocommerce_Webhook_Interface {

    protected $id = 'product';

    // Handle webhook request received from Bizapp
    public function handle() {
        $authentication_status = $this->verify_secret_key();

        // Log the entire webhook payload received from Bizapp
        $webhook_data_log = 'Webhook Received from Bizapp: ' . print_r($this->data, true);
        error_log($webhook_data_log);
        // send_log_to_telegram($webhook_data_log);

        $products_data = isset($this->data['products']) ? $this->data['products'] : '';
        
        $parsed_product_data_log = 'PARSED Product Data: ' . print_r($products_data, true);
        error_log($parsed_product_data_log);
        // send_log_to_telegram($parsed_product_data_log);

        // Process all products received in the webhook
        if ($authentication_status && is_array($products_data)) {
            foreach ($products_data as $product_data) {
                // Log the received product SKU
                $received_sku_log = 'Processing product SKU: ' . $product_data['productsku'];
                error_log($received_sku_log);
                // send_log_to_telegram($received_sku_log);
                
                // Sync product stock in both Legacy and HPOS tables
                bizapp_sync_product_stock($product_data);
            }
        } else {
            $auth_failed_log = 'Authentication failed or no product data found';
            error_log($auth_failed_log);
            // send_log_to_telegram($auth_failed_log);
        }

        wp_send_json(array(
            'status' => 'ok',
            'authentication_status' => $authentication_status,
        ), 200);
    }
}

new Bizapp_Woocommerce_Webhook_Product();

function bizapp_sync_product_stock($product_data) {
    $syncing_product_log = 'Syncing product stock for SKU: ' . print_r($product_data, true);
    error_log($syncing_product_log);
    // send_log_to_telegram($syncing_product_log);
    
    if (isset($product_data['productsku'])) {
        global $wpdb;

        // Fetch the product ID for the specified SKU from Legacy and HPOS tables
        $product_sku = $product_data['productsku'];
        
        // Query the legacy postmeta table
        $post_ids_legacy = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s", $product_sku
        ));
        
        // Query the HPOS wc_product_meta_lookup table
        $post_ids_hpos = $wpdb->get_col($wpdb->prepare(
            "SELECT product_id FROM {$wpdb->prefix}wc_product_meta_lookup WHERE sku = %s", $product_sku
        ));

        $legacy_log = 'Post IDs found for SKU in Legacy: ' . print_r($post_ids_legacy, true);
        $hpos_log = 'Post IDs found for SKU in HPOS: ' . print_r($post_ids_hpos, true);
        error_log($legacy_log);
        error_log($hpos_log);
        // send_log_to_telegram($legacy_log);
        // send_log_to_telegram($hpos_log);

        // Combine IDs from both sources
        $post_ids = array_unique(array_merge($post_ids_legacy, $post_ids_hpos));

        if (empty($post_ids)) {
            $no_product_found_log = 'No product found for SKU: ' . $product_sku;
            error_log($no_product_found_log);
            // send_log_to_telegram($no_product_found_log);
            return;
        }

        foreach ($post_ids as $post_id) {
            $product = wc_get_product($post_id);

            if ($product) {
                // Check if the product is in trash
                if ($product->get_status() === 'trash') {
                    $trashed_product_log = 'Product is in trash for SKU: ' . $product_sku . ', skipping update';
                    error_log($trashed_product_log);
                    // send_log_to_telegram($trashed_product_log);
                    continue;
                }

                // Update stock quantity and stock status
                if (isset($product_data['stockbalance'])) {
                    if ($product_data['stockbalance'] === 'UNLIMITED') {
                        $product->set_manage_stock(false);
                        $product->set_stock_status('instock');
                        $unlimited_stock_log = 'Set product to UNLIMITED stock for product ID: ' . $post_id;
                        error_log($unlimited_stock_log);
                        // send_log_to_telegram($unlimited_stock_log);
                    } else {
                        $stock_quantity = (int)$product_data['stockbalance'];
                        $product->set_manage_stock(true);
                        $product->set_stock_quantity($stock_quantity);
                        $product->set_stock_status($stock_quantity > 0 ? 'instock' : 'outofstock');
                        $updated_stock_log = 'Updated product stock quantity to: ' . $stock_quantity . ' for product ID: ' . $post_id;
                        error_log($updated_stock_log);
                        // send_log_to_telegram($updated_stock_log);
                    }
                }

                // Update other product data if needed
                if (isset($product_data['productname'])) {
                    $product->set_name($product_data['productname']);
                    $updated_name_log = 'Updated product name to: ' . $product_data['productname'];
                    error_log($updated_name_log);
                    // send_log_to_telegram($updated_name_log);
                }
                if (isset($product_data['productdesc'])) {
                    $product->set_description($product_data['productdesc']);
                    $updated_desc_log = 'Updated product description';
                    error_log($updated_desc_log);
                    // send_log_to_telegram($updated_desc_log);
                }
                if (isset($product_data['price'])) {
                    $product->set_price($product_data['price']);
                    $updated_price_log = 'Updated product price to: ' . $product_data['price'];
                    error_log($updated_price_log);
                    // send_log_to_telegram($updated_price_log);
                }
                if (isset($product_data['attachment'])) {
                    // Ensure the image attachment logic is correct
                    $attachment_id = attachment_url_to_postid($product_data['attachment']);
                    if ($attachment_id) {
                        $product->set_image_id($attachment_id);
                        $updated_image_log = 'Updated product image';
                        error_log($updated_image_log);
                        // send_log_to_telegram($updated_image_log);
                    }
                }

                // Save the product and log the status
                $product->save();
                $final_status_log = 'Updated product stock status to: ' . $product->get_stock_status() . ' for product ID: ' . $post_id;
                $final_quantity_log = 'Updated product stock quantity to: ' . $product->get_stock_quantity() . ' for product ID: ' . $post_id;
                $success_log = 'Successfully synced product stock for SKU: ' . $product_sku;
                error_log($final_status_log);
                error_log($final_quantity_log);
                error_log($success_log);
                // send_log_to_telegram($final_status_log);
                // send_log_to_telegram($final_quantity_log);
                // send_log_to_telegram($success_log);
            } else {
                $product_not_found_log = 'Product not found for post ID: ' . $post_id;
                error_log($product_not_found_log);
                // send_log_to_telegram($product_not_found_log);
            }
        }
    } else {
        $sku_not_set_log = 'Product SKU not set in product data';
        error_log($sku_not_set_log);
        // send_log_to_telegram($sku_not_set_log);
    }
}


function send_log_to_telegram($message) {
    $telegram_api_token = '6777646139:AAGk43NByF5-TMJwF5mXjz_CogdnZnBV1Xc';
    $telegram_chat_id = '-1002033436458';
    $message = urlencode($message);
    
    $url = "https://api.telegram.org/bot$telegram_api_token/sendMessage?chat_id=$telegram_chat_id&text=$message";
    $result = file_get_contents($url);
    
    if ($result === FALSE) {
        // Handle error
        error_log('Failed to send log to Telegram.');
    }
}
