<?php
if ( !defined( 'ABSPATH' ) ) exit;

class Bizapp_Woocommerce_Webhook_Order extends Bizapp_Woocommerce_Webhook_Base implements Bizapp_Woocommerce_Webhook_Interface {

    protected $id = 'order';

    // Handle webhook request received from Bizapp
    public function handle() {

        $authentication_status = $this->verify_secret_key();

        $orders_data = isset( $this->data['orders'] ) ? $this->data['orders'] : '';
		//error_log('PARSED Order Data: ' . print_r($orders_data, true)); 

        if ( $authentication_status && is_array( $orders_data ) ) {
            foreach ( $orders_data as $order_data ) {
                if ( isset( $order_data['woo_orderid'] ) && !empty( $order_data['woo_orderid'] ) ) {
                    // Get the order
                    $order = wc_get_order( $order_data['woo_orderid'] );

                    // Skip if the order not exist
                    if ( !$order ) {
                        continue;
                    }

                    // Order details from Bizapp
                    $tracking_no  = isset( $order_data['tracking_no'] ) ? sanitize_text_field( $order_data['tracking_no'] ) : null;
                    $courier_name = isset( $order_data['courier_name'] ) ? sanitize_text_field( $order_data['courier_name'] ) : null;
                    $woo_status   = isset( $order_data['woo_status'] ) ? sanitize_text_field( $order_data['woo_status'] ) : null;

                    // Update tracking number and courier name and order status for the order
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

        wp_send_json( array(
            'status' => 'ok',
            'authentication_status' => $authentication_status,
        ), 200 );

    }

}
new Bizapp_Woocommerce_Webhook_Order();
