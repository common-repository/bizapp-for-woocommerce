<?php
if ( !defined( 'ABSPATH' ) ) exit;

class Bizapp_Woocommerce_Metabox {

    private $id = '_bizapp';

    // Register metabox for WooCommerce product
    public function register( $metaboxes ) {

        $metaboxes[] = array(
            'title'      => esc_html__( 'Bizapp Product Data', 'bizapp-woocommerce' ),
            'id'         => $this->id,
            'post_types' => 'product',
            'context'    => 'normal',
            'priority'   => 'high',
            'fields'     => array(
                array(
                    'type' => 'text',
                    'name' => esc_html__( 'Product SKU', 'bizapp-woocommerce' ),
                    'id'   => $this->id . '_product_sku',
                    'desc' => esc_html__( 'Enter Bizapp product SKU for this product, so the plugin able to sync product details from Bizapp to WooCommerce', 'bizapp-woocommerce' ),
                ),
            ),
        );

        return $metaboxes;

    }

}
