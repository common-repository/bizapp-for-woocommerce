<?php

trait Bizapp_Woocommerce_Products_List {

    private $api;
    private $secret_key;

    // Initialize API
    private function init_api() {

        // Return false if user is resetting the plugin settings,
        // or resetting API credentials section
        if (
            !empty( $_POST['csf_transient']['reset'] )
            || (
                !empty( $_POST['csf_transient']['reset_section'] )
                && !empty( $_POST['csf_transient']['section'] )
                && $_POST['csf_transient']['section'] == '1'
            )
        ) {
            return false;
        }

        // If $_POST data is passed, update API credentials value
        // For settings page, if secret key is updated, then update their value
        if ( !empty( $_POST[ $this->id ] ) && !empty( $_POST[ $this->id ]['secret_key'] ) ) {
            $this->secret_key  = sanitize_text_field( $_POST[ $this->id ]['secret_key'] );
        } else {
            $this->secret_key = bizapp_woocommerce_get_setting( 'secret_key' );
        }

        // Return false if secret key is empty
        if ( !$this->secret_key ) {
            return false;
        }

        $this->api = new Bizapp_Woocommerce_Api( $this->secret_key );
        return $this->api;

    }

    // Get Bizapp product to list out in Product field
    private function get_bizapp_product_select() {

        if ( !$this->init_api() ) {
            return array();
        }

        // Get Bizapp product
        list( $code, $response ) = $this->api->get_product_list();

        if ( $code == 200 && $response['status'] == "success") {
            // Format Bizapp product for select field
            return array_reduce( $response['productinfo'], function( $result, $item ) {
                // Sanitize first
                $product_sku = !empty( $item['productsku'] ) ? sanitize_text_field( $item['productsku'] ) : null;
                $product_name = !empty( $item['productname'] ) ? sanitize_text_field( $item['productname'] ) : null;

                if ( $product_sku && $product_name ) {
                    $result[ $product_sku ] = $product_name;
                }

                return $result;
            }, array() );
        }

        return array();

    }
	
	private function get_all_product_skus() {
    global $wpdb;

    $results = $wpdb->get_col( "
        SELECT meta_value
        FROM {$wpdb->postmeta}
        WHERE meta_key = '_sku'
    " );

    return array_filter( $results ); 
}

}
