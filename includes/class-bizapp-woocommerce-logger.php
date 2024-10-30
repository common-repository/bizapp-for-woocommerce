<?php
if ( !defined( 'ABSPATH' ) ) exit;

class Bizapp_Woocommerce_Logger {

    private $id = 'bizapp';

    // Errors logging
    public function log( $message ) {

        if ( class_exists( 'WC_Logger' ) ) {
            $logger = new WC_Logger();
            $logger->add( $this->id, $message );
        }

    }

}
