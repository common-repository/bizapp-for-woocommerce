<?php
if ( !defined( 'ABSPATH' ) ) exit;

class Bizapp_Woocommerce_Api extends Bizapp_Woocommerce_Client {

    public function __construct( $secret_key ) {
        $this->secret_key = $secret_key;
    }

    // Get product list
    public function get_product_list() {
        return $this->get( 'v2/getproductlist' );
    }

    // Submit order
    public function submit_order( array $params ) {
        return $this->post( 'v2/submitorder', $params );
    }

}
