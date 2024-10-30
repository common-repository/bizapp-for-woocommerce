<?php
if ( !defined( 'ABSPATH' ) ) exit;

class Bizapp_Woocommerce_Settings {

    use Bizapp_Woocommerce_Products_List;

    public $id = 'bizapp';

    // Register hooks
    public function __construct() {

        $this->register();

        add_action( 'csf_' . $this->id . '_save_before', array( $this, 'schedule_sync_bizapp_products' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

    }

    // Register settings page
    public function register() {

        if ( !class_exists( 'CSF' ) ) {
            return;
        }

        CSF::createOptions( $this->id, $this->args() );

        // Sections
        foreach ( $this->sections() as $section ) {
            CSF::createSection( $this->id, $section );
        }

    }

    // Settings page configuration
    private function args() {

        $title = __( 'Bizapp for WooCommerce', 'bizapp-woocommerce' );

        $logo_img = BIZAPP_WOOCOMMERCE_URL . 'assets/images/bizapp.png';
        $logo = sprintf( '<img class="bizapp-logo" src="%s" alt="%s">', $logo_img, $title );

        return array(
            'framework_title' => $logo . $title,
            'framework_class' => 'bizapp-woocommerce-settings',
            'menu_title'      => __( 'Bizapp', 'bizapp-woocommerce' ),
            'menu_slug'       => $this->id,
            'menu_icon'       => BIZAPP_WOOCOMMERCE_URL . 'assets/images/bizapp_icon.png',
            'menu_position'   => 58,
            'show_bar_menu'   => false,
            'ajax_save'       => false,
        );

    }

    // Settings sections
    private function sections() {

        $products = null;

        // Get Bizapp products only if we are on settings page
        if ( is_admin() && !empty( $_GET['page'] ) && $_GET['page'] == $this->id ) {
            $products = $this->get_bizapp_product_select();
        }

        return array(
            array(
                'title'       => __( 'API Credentials', 'bizapp-woocommerce' ),
                'description' => __( 'You can obtain the API credentials from Bizapp dashboard.', 'bizapp-woocommerce' ),
                'fields'      => array(
                    array(
                        'id'    => 'secret_key',
                        'type'  => 'text',
                        'title' => __( 'Secret Key', 'bizapp-woocommerce' ),
                    ),
                ),
            ),
            array(
                'title'       => __( 'Orders Creation', 'bizapp-woocommerce' ),
                'fields'      => array(
                    array(
                        'id'      => 'auto_push_order',
                        'type'    => 'switcher',
                        'title'   => __( 'Auto Push', 'bizapp-woocommerce' ),
                        'desc'    => __( 'Turn ON to automatically push new WooCommerce order to Bizapp.', 'bizapp-woocommerce' ),
                        'default' => false,
                    ),
                ),
            ),
            array(
                'title'       => __( 'Products Sync', 'bizapp-woocommerce' ),
                'description' => __( 'Select Bizapp product to sync or import. Product will be synced based on Bizapp SKU specified on the product.', 'bizapp-woocommerce' ),
                'fields'      => array(
                    array(
                        'id'      => 'auto_import_product',
                        'type'    => 'switcher',
                        'title'   => __( 'Auto Import', 'bizapp-woocommerce' ),
                        'desc'    => __( 'Turn ON to automatically import selected Bizapp products below to WooCommerce.', 'bizapp-woocommerce' ),
                        'default' => false,
                    ),
                    array(
                        'id'      => 'auto_sync_product',
                        'type'    => 'checkbox',
                        'title'   => __( 'Auto Sync', 'bizapp-woocommerce' ),
                        'desc'    => __( 'Select which Bizapp product details will be automatically sync to WooCommerce for selected Bizapp products below.', 'bizapp-woocommerce' ),
                        'options' => array(
                            'name'           => __( 'Product Name', 'bizapp-woocommerce' ),
                            'desc'           => __( 'Product Description', 'bizapp-woocommerce' ),
                            'price'          => __( 'Product Price', 'bizapp-woocommerce' ),
                            'stock'          => __( 'Total Stock/Quantity', 'bizapp-woocommerce' ),
                            'featured_image' => __( 'Featured Image', 'bizapp-woocommerce' ),
                            'gallery_images' => __( 'Product Gallery Images (if have)', 'bizapp-woocommerce' ),
                        ),
                        'default' => array( 'name', 'price', 'stock' ),
                    ),
                    array(
                        'id'      => 'products',
                        'type'    => 'checkbox',
                        'title'   => __( 'Products to be Sync ', 'bizapp-woocommerce' ),
                        'options' => $products,
                    ),
                ),
            ),
        );

    }

    // Schedule to sync selected Bizapp products
    public function schedule_sync_bizapp_products( $data ) {

        // Old selected Bizapp products to be imported/synced
           //$old_products = bizapp_woocommerce_get_setting( 'products' ) ?: array();
			if ( $data['auto_import_product'] !== '1' ) {
            	return false;
        	}		

        // Go through selected Bizapp products to be imported/synced
        if ( !empty( $data['products'] ) && is_array( $data['products'] ) ) {
           /* foreach ( $data['products'] as $key => $new_product ) {
                // Only import/sync new selected Bizapp product
                if ( in_array( $new_product, $old_products ) ) {
                    unset( $data['products'][ $key ] );
                }
            }*/

            // Import/sync selected Bizapp products with WooCommerce
        if ( !empty( $data['products'] ) ) {
            if ( !$this->init_api() ) {
                return false;
            }

            // Get Bizapp products list
            list( $code, $response ) = $this->api->get_product_list();

            if ( $code == 200 && $response['status'] == "success") {
                $product_count = count( $response['productinfo'] );
                error_log( 'Number of products received from API: ' . $product_count );

                $selected_products_count = 0;

                foreach ( $response['productinfo'] as $product_data ) {
                    if ( !empty( $product_data['productsku'] ) && in_array( $product_data['productsku'], $data['products'] ) ) {
                        wp_schedule_single_event( time(), 'sync_bizapp_product', array( $product_data ) );
                        $selected_products_count++;
                    }
                }

                error_log( 'Number of selected products to import: ' . $selected_products_count );
            }
        }
      }

    }

    // Enqueue styles and scripts
public function enqueue_scripts() {
    wp_enqueue_style( 'bizapp-settings', BIZAPP_WOOCOMMERCE_URL . 'assets/css/admin.css', array(), BIZAPP_WOOCOMMERCE_VERSION );
    wp_enqueue_script( 'bizapp-settings', BIZAPP_WOOCOMMERCE_URL . 'assets/js/admin.js', array( 'jquery' ), BIZAPP_WOOCOMMERCE_VERSION );

    // Get the current product SKUs
    $synced_products = $this->get_all_product_skus();

    // Pass data
    wp_localize_script( 'bizapp-settings', 'bizappSettings', array(
        'syncedProducts' => $synced_products,
    ));
}

}
new Bizapp_Woocommerce_Settings();
