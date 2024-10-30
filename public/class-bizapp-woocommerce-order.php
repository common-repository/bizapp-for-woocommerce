<?php
if ( !defined( 'ABSPATH' ) ) exit;

class Bizapp_Woocommerce_Order {

    // Create order in Bizapp when new order received in WooCommerce
    public function create_bizapp_order( $order_id ) {

        $order = wc_get_order( $order_id );

        // Return false if order not exist or secret key for API is empty
        if ( !$order || !$this->init_api() ) {
            return false;
        }

        // Send order data to Bizapp
        if ( $order_data = $this->get_order_data( $order ) ) {
            list( $code, $response ) = $this->api->submit_order( $order_data );

            //v2
            if($code == 200 && $response['status'] == "success") {
                if(isset($response['result'][0]['ID']) 
                && !empty($response['result'][0]['ID'])) {
                    return true;
                }
            }

            //v1
            // if ( $code == 200 && $response['status'] == "success") {
            //     return true;
            // }
        }

        return false;

    }

    // Initialize API
    private function init_api() {

        $secret_key = bizapp_woocommerce_get_setting( 'secret_key' );

        // Check if secret key is not empty
        if ( !$secret_key ) {
            return false;
        }

        $this->api = new Bizapp_Woocommerce_Api( $secret_key );
        return $this->api;

    }

    // Get order data for specified order v2
    private function get_order_data( $order ) {

        // Customer name and address
        if ( $order->has_shipping_address() ) {
            $name    = $order->get_formatted_shipping_full_name();
            $country = $order->get_shipping_country();
            $state   = $order->get_shipping_state();

            $address = array(
                $order->get_shipping_address_1(),
                $order->get_shipping_address_2(),
                $order->get_shipping_postcode(),
                $order->get_shipping_city(),
                $this->get_state_name_by_code( $country, $state ),
                $this->get_country_name_by_code( $country ),
            );
        } else {
            $name    = $order->get_formatted_billing_full_name();
            $country = $order->get_billing_country();
            $state   = $order->get_billing_state();

            $address = array(
                $order->get_billing_address_1(),
                $order->get_billing_address_2(),
                $order->get_billing_postcode(),
                $order->get_billing_city(),
                $this->get_state_name_by_code( $country, $state ),
                $this->get_country_name_by_code( $country ),
                // $country_name,
            );
        }

        $order_data = array(
            'name'                  => $name,
            'address'               => implode( ', ', array_filter( $address ) ),
            'hpno'                  => $order->get_billing_phone(),
            'email'                 => $order->get_billing_email(),
            'sellingprice'          => $order->get_total(),
            'postageprice'          => $order->get_shipping_total(),
            'note'                  => $order->get_customer_note(),
            'woo_url'               => get_site_url(),
            'woo_orderid'           => $order->get_id(),
            'woo_paymentgateway'    => $order->get_payment_method_title(),
            'woo_payment_txn'       => $order->get_transaction_id(),
            'woo_paymentgateway_id' => $order->get_payment_method(),
            'woo_shipping_method'   => $order->get_shipping_method(),
        );

        //V2
        $items = [];
        foreach($order->get_items() as $item){
            $product_id = $item->get_product_id();
            $product_variation_id = $item->get_variation_id() ?: $product_id;
            $product_sku = get_post_meta( $product_variation_id, '_bizapp_product_sku', true );
            if ( empty( $product_sku ) ) {
                $product_sku = get_post_meta( $product_id, '_bizapp_product_sku', true );
            }
            if($product_sku){
                $items[] = [
                    'sku' => $product_sku,
                    'quantity' => $item->get_quantity(),
                ];
            }
        }

        if(count($items) > 0){
            $order_data['products_info'] = $items;
            return $order_data;
        }

        return false;
    }

    // Get order data for specified order v1
    // private function get_order_data( $order ) {

    //     // Customer name and address
    //     if ( $order->has_shipping_address() ) {
    //         // Shipping name and address
    //         $name = $order->get_formatted_shipping_full_name();
    //         $address = array(
    //             $order->get_shipping_address_1(),
    //             $order->get_shipping_address_2(),
    //             $order->get_shipping_city(),
    //             $order->get_shipping_state(),
    //             $order->get_shipping_postcode(),
    //             $order->get_shipping_country(),
    //         );
    //     } else {
    //         // Billing name and address
    //         $name = $order->get_formatted_billing_full_name();
    //         $address = array(
    //             $order->get_billing_address_1(),
    //             $order->get_billing_address_2(),
    //             $order->get_billing_city(),
    //             $order->get_billing_state(),
    //             $order->get_billing_postcode(),
    //             $order->get_billing_country(),
    //         );
    //     }

    //     $order_data = array(
    //         'name'               => $name,
    //         'address'            => implode( "\n", array_filter( $address ) ),
    //         'hpno'               => $order->get_billing_phone(),
    //         'email'              => $order->get_billing_email(),
    //         'sellingprice'       => $order->get_total(),
    //         'postageprice'       => $order->get_shipping_total(),
    //         'note'               => $order->get_customer_note(),
    //         'woo_url'            => get_site_url(),
    //         'woo_orderid'        => $order->get_id(),
    //         'woo_paymentgateway' => $order->get_payment_method_title(),
    //         'woo_payment_txn'    => $order->get_transaction_id(),
    //     );

    //     $i = 0;

    //     // Go through each order items
    //     foreach ( $order->get_items() as $item_id => $item ) {
    //         $i++;
    //         $product_id = $item->get_product_id();

    //         // If Bizapp product SKU is not specified
    //         if ( $product_sku = get_post_meta( $product_id, '_bizapp_product_sku', true ) ) {
    //             $order_data[ 'productsku' . $i ] = $product_sku;
    //             $order_data[ 'quantity' . $i ] = $item->get_quantity();
    //         }
    //     }

    //     // Return order data if has Bizapp product SKU and quantity in the order data
    //     if ( isset( $order_data[ 'productsku1' ] ) && isset( $order_data[ 'quantity1' ] ) ) {
    //         return $order_data;
    //     }

    //     return false;

    // }

}
