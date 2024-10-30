<?php
if ( !defined( 'ABSPATH' ) ) exit;

class Bizapp_Woocommerce_Product_Data {

    // Register hooks
    public function __construct() {

        add_action( 'woocommerce_product_options_sku', array( $this, 'register_product_options' ), 9 );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_options' ), 10, 2 );
        add_action( 'woocommerce_variation_options', array( $this, 'register_variation_options' ), 9, 3 );
        add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_options' ), 10, 2 );

    }

    // Register product data for simple product - Bizapp SKU
    public function register_product_options() {

        woocommerce_wp_text_input(
            array(
                'id'          => '_bizapp_product_sku',
                'value'       => get_post_meta( get_the_ID(), '_bizapp_product_sku', true ),
                'label'       => '<abbr title="' . esc_attr__( 'Stock Keeping Unit', 'bizapp-woocommerce' ) . '">' . esc_html__( 'Bizapp SKU', 'bizapp-woocommerce' ) . '</abbr>',
                'desc_tip'    => true,
                'description' => __( 'Enter Bizapp product SKU for this product, so the plugin able to sync product details from Bizapp to WooCommerce.', 'bizapp-woocommerce' ),
            )
        );

    }

    // Save product data for simple product - Bizapp SKU
    public function save_product_options( $id, $product ) {
        update_post_meta( $id, '_bizapp_product_sku', sanitize_text_field( $_POST['_bizapp_product_sku'] ) );
    }

    // Register product data for variable product - Bizapp SKU
    public function register_variation_options( $loop, $variation_data, $variation ) {

        woocommerce_wp_text_input(
            array(
                'id'            => "_bizapp_product_sku[$loop]",
                'wrapper_class' => 'form-row form-row-full',
                'value'         => get_post_meta( $variation->ID, '_bizapp_product_sku', true ),
                'label'         => '<abbr title="' . esc_attr__( 'Stock Keeping Unit', 'bizapp-woocommerce' ) . '">' . esc_html__( 'Bizapp SKU', 'bizapp-woocommerce' ) . '</abbr>',
                'desc_tip'      => true,
                'description'   => __( 'Enter Bizapp product SKU for this product, so the plugin able to sync product details from Bizapp to WooCommerce.', 'bizapp-woocommerce' ),
            )
        );

    }

    // Save product data for variable product - Bizapp SKU
    public function save_variation_options( $variation_id, $i ) {
        update_post_meta( $variation_id, '_bizapp_product_sku', sanitize_text_field( $_POST['_bizapp_product_sku'][ $i ] ) );
    }

}
new Bizapp_Woocommerce_Product_Data();
