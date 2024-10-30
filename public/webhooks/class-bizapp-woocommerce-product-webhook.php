<?php
if (!defined('ABSPATH')) exit;

class Bizapp_Woocommerce_Product_Webhook implements Bizapp_Woocommerce_Webhook_Interface {

    // Handle webhook request received from Bizapp
    public function handle() {
        $authentication_status = false;
        error_log('Product Webhook Triggered');

        // Call the log function to fetch and log all product SKUs in the database
        $this->log_all_product_skus();
        $this->log_all_product_bizapp_skus();

        // Get received data
        $raw_data = file_get_contents('php://input');
        $data = json_decode($raw_data, true);
        error_log('PARSED Products Webhook Data: ' . print_r($data, true)); 

        // Get plugin settings
        $secret_key = bizapp_woocommerce_get_setting('secret_key');
        $products = bizapp_woocommerce_get_setting('products') ?: array();

        // Check if request received from Bizapp
        if (isset($data['bizappsecretkey']) && $data['bizappsecretkey'] == $secret_key) {
            $authentication_status = true;

            // Only sync selected Bizapp products with WooCommerce
            if (!empty($products) && isset($data['products']) && !empty($data['products']) && is_array($data['products'])) {
                foreach ($data['products'] as $product_data) {
                    // Only sync selected Bizapp products
                    if (isset($product_data['productsku']) && in_array($product_data['productsku'], $products)) {
                        error_log('Updating stock directly for Bizapp SKU: ' . $product_data['productsku']);
                        bizapp_sync_product_stock($product_data);
                    } else {
                        error_log('Skipping product Bizapp SKU: ' . $product_data['productsku'] . ' - not in selected products list');
                    }
                }
            }
        }

        wp_send_json(array(
            'status' => 'ok',
            'authentication_status' => $authentication_status,
        ), 200);
    }

    // Function to log all default WooCommerce SKUs
    private function log_all_product_skus() {
        error_log('Fetching all default WooCommerce SKUs in the database.');
        $args = array(
            'status' => 'publish',
            'limit' => -1,
            'return' => 'ids',
        );
        $products = wc_get_products($args);
        $skus = array();
        foreach ($products as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $default_sku = $product->get_sku();
                $skus[] = $default_sku;
                error_log('Default SKU found: ' . $default_sku);
            } else {
                error_log('Product not found for ID: ' . $product_id);
            }
        }
        error_log('All Default SKUs in Database: ' . print_r($skus, true));
    }

    // Function to log all custom Bizapp SKUs
    private function log_all_product_bizapp_skus() {
        global $wpdb;
        error_log('Fetching all product Bizapp SKUs in the database.');
        $skus = $wpdb->get_col("
            SELECT meta_value 
            FROM $wpdb->postmeta 
            WHERE meta_key = 'bizapp_sku'
        ");
        foreach ($skus as $sku) {
            error_log('Bizapp SKU found: ' . ($sku ? $sku : '(empty)'));
        }
        error_log('All Bizapp SKUs in Database: ' . print_r($skus, true));
    }
}

new Bizapp_Woocommerce_Product_Webhook();

// Function to sync product stock directly
function bizapp_sync_product_stock($product_data) {
    error_log('Syncing product stock for Bizapp SKU: ' . print_r($product_data, true));

    if (isset($product_data['productsku'])) {
        // Try to get product ID by Bizapp SKU first
        $product_id = get_product_id_by_bizapp_sku($product_data['productsku']);
        
        // If not found, try to get product ID by default SKU
        if (!$product_id) {
            $product_id = wc_get_product_id_by_sku($product_data['productsku']);
        }

        error_log('Product ID found for Bizapp SKU ' . $product_data['productsku'] . ': ' . $product_id);

        if ($product_id) {
            $product = wc_get_product($product_id);

            // Update stock quantity and stock status
            if (isset($product_data['stockbalance'])) {
                if ($product_data['stockbalance'] === 'UNLIMITED') {
                    $product->set_manage_stock(false);
                    $product->set_stock_status('instock');
                    error_log('Set product to UNLIMITED stock for product ID: ' . $product_id);
                } else {
                    $stock_quantity = (int)$product_data['stockbalance'];
                    $product->set_manage_stock(true);
                    $product->set_stock_quantity($stock_quantity);
                    $product->set_stock_status($stock_quantity > 0 ? 'instock' : 'outofstock');
                    error_log('Updated product stock quantity to: ' . $stock_quantity . ' for product ID: ' . $product_id);
                }
            }

            // Update other product data if needed
            if (isset($product_data['productname'])) {
                $product->set_name($product_data['productname']);
                error_log('Updated product name to: ' . $product_data['productname']);
            }
            if (isset($product_data['productdesc'])) {
                $product->set_description($product_data['productdesc']);
                error_log('Updated product description');
            }
            if (isset($product_data['price'])) {
                $product->set_price($product_data['price']);
                error_log('Updated product price to: ' . $product_data['price']);
            }
            if (isset($product_data['attachment'])) {
                // Ensure the image attachment logic is correct
                $attachment_id = attachment_url_to_postid($product_data['attachment']);
                if ($attachment_id) {
                    $product->set_image_id($attachment_id);
                    error_log('Updated product image');
                }
            }

            // Save the product and log the status
            $product->save();
            error_log('Updated product stock status to: ' . $product->get_stock_status() . ' for product ID: ' . $product_id);
            error_log('Updated product stock quantity to: ' . $product->get_stock_quantity() . ' for product ID: ' . $product_id);
            error_log('Successfully synced product stock for Bizapp SKU: ' . $product_data['productsku']);
        } else {
            error_log('Product ID not found for Bizapp SKU: ' . $product_data['productsku']);
        }
    } else {
        error_log('Product Bizapp SKU not set in product data');
    }
}

// Function to fetch product ID by Bizapp SKU
function get_product_id_by_bizapp_sku($bizapp_sku) {
    global $wpdb;
    $product_id = $wpdb->get_var($wpdb->prepare("
        SELECT post_id 
        FROM $wpdb->postmeta 
        WHERE meta_key = 'bizapp_sku' 
        AND meta_value = %s 
        LIMIT 1
    ", $bizapp_sku));
    return $product_id;
}
