<?php
if ( !defined( 'ABSPATH' ) ) exit;

class Bizapp_Woocommerce_Webhook_Base {

    protected $id;
    protected $data;

    // Register hooks
    public function __construct() {

        $this->set_data();

        add_action( 'wp_ajax_nopriv_bizapp_woocommerce_' . $this->id . '_webhook', array( $this, 'handle' ) );

    }

    // Get received data
    protected function set_data() {

        $raw_data = file_get_contents( 'php://input' );
        $this->data = json_decode( stripslashes( htmlspecialchars_decode( $raw_data ) ), true );

        return $this->data;

    }

    // Verify if request received from Bizapp
    protected function verify_secret_key() {

        $is_valid = false;

        $secret_key = bizapp_woocommerce_get_setting( 'secret_key' );

        if ( isset( $this->data['bizappsecretkey'] ) && trim( $this->data['bizappsecretkey'] ) == trim( $secret_key ) ) {
            $is_valid = true;
        }

        return $is_valid;

    }

}
