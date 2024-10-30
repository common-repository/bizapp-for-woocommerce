<?php
if ( !defined( 'ABSPATH' ) ) exit;

class Bizapp_Woocommerce_Order_Webhook implements Bizapp_Woocommerce_Webhook_Interface {

    // Handle webhook request received from Bizapp
    public function handle() {

        $authentication_status = false;

        // Get received data
        $raw_data = file_get_contents( 'php://input' );
        $data = json_decode( $raw_data, true );

        // Get Bizapp secret key from plugin settings
        $secret_key = bizapp_woocommerce_get_setting( 'secret_key' );

        // Check if request received from Bizapp
        if ( isset( $data['bizappsecretkey'] ) && $data['bizappsecretkey'] == $secret_key ) {
            $authentication_status = true;

            if ( isset( $data['orders'] ) && !empty( $data['orders'] ) && is_array( $data['orders'] ) ) {
                foreach ( $data['orders'] as $order_data ) {
                    if ( isset( $order_data['woo_orderid'] ) && !empty( $order_data['woo_orderid'] ) ) {
                        // Get the order
                        $order = wc_get_order( $order_data['woo_orderid'] );

                        // Skip if the order does not exist
                        if ( !$order ) {
                            continue;
                        }

                        // Order details from Bizapp
                        $tracking_no  = isset( $order_data['tracking_no'] ) ? $order_data['tracking_no'] : null;
                        $courier_name = isset( $order_data['courier_name'] ) ? $order_data['courier_name'] : null;
                        $woo_status   = isset( $order_data['woo_status'] ) ? $order_data['woo_status'] : null;

                        // Update tracking number and courier name for the order using WooCommerce CRUD methods
                        if ( $tracking_no ) {
                            $order->update_meta_data( '_bizapp_tracking_no', $tracking_no );
                        }
                        if ( $courier_name ) {
                            $order->update_meta_data( '_bizapp_courier_name', $courier_name );
                        }


                        // Update order status
                        if ( !empty( $woo_status ) ) {
                            $woo_status = strtolower( str_replace( ' ', '-', $woo_status ) );
                            $order->update_status( $woo_status );
                        }
						// Save the order
                        $order->save();
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