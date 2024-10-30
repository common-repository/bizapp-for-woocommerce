<?php
if ( !defined( 'ABSPATH' ) ) exit;

class Bizapp_Woocommerce_Order_Metabox implements Bizapp_Woocommerce_Metabox_Interface {

    private $id = '_bizapp';

    // Register hooks
    public function __construct() {
        add_filter( 'rwmb_meta_boxes', array( $this, 'register' ) );
    }

    // Register metabox for WooCommerce product
    public function register( $metaboxes ) {

        $metaboxes[] = array(
            'title'      => esc_html__( 'Bizapp Order Data', 'bizapp-woocommerce' ),
            'id'         => $this->id,
            'post_types' => 'shop_order',
            'context'    => 'side',
            'priority'   => 'high',
            'fields'     => array(
                array(
                    'type' => 'text',
                    'name' => esc_html__( 'Tracking Number', 'bizapp-woocommerce' ),
                    'id'   => $this->id . '_tracking_no',
                ),
                array(
                    'type' => 'text',
                    'name' => esc_html__( 'Courier', 'bizapp-woocommerce' ),
                    'id'   => $this->id . '_courier_name',
                ),
            ),
        );

        return $metaboxes;

    }

}
new Bizapp_Woocommerce_Order_Metabox();
