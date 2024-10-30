<?php
if ( !defined( 'ABSPATH' ) ) exit;

class Bizapp_Woocommerce_Webhook {

    // Handle webhook request received from Bizapp
    public function handle() {

		error_log('Triggered The stock Webhook');
        $authentication_status = false;

        // Get received data
        $raw_data = file_get_contents( 'php://input' );
        $data = json_decode( $raw_data, true );

        // Get plugin settings
        $secret_key = bizapp_woocommerce_get_setting( 'secret_key' );
        $products = bizapp_woocommerce_get_setting( 'products' ) ?: array();

        // Check if request received from Bizapp
        if ( isset( $data['bizappsecretkey'] ) && $data['bizappsecretkey'] == $secret_key ) {
            $authentication_status = true;

            // Only sync selected Bizapp products with WooCommerce
            if (
                !empty( $products )
                && isset( $data['products'] )
                && !empty( $data['products'] )
                && is_array( $data['products'] )
            ) {
                foreach ( $data['products'] as $product_data ) {
                    // Only schedule to sync selected Bizapp products
                    if ( isset( $product_data['productsku'] ) && in_array( $product_data['productsku'], $products ) ) {
                        //do_action( 'bizapp_woocommerce_sync_product', $product_data );
						wp_schedule_single_event( time(), 'sync_bizapp_product', array( $product_data ) );
                    }
                }
            }
        }

        wp_send_json( array(
            'status' => 'ok',
            'authentication_status' => $authentication_status,
        ), 200 );

    }

}
