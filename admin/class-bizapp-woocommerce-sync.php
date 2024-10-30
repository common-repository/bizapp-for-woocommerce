<?php
if ( !defined( 'ABSPATH' ) ) exit;

class Bizapp_Woocommerce_Sync {

    // Register hooks
    public function __construct() {
        add_action( 'bizapp_woocommerce_sync_product', array( $this, 'sync_bizapp_product' ) );
    }

    // Import/sync selected Bizapp products with WooCommerce
    public function sync_bizapp_product( $product_data ) {

        // Sanitize product data
        $product_data = $this->get_product_data( $product_data );

        if ( !$product_data['sku'] ) {
            return;
        }

        // If product with specified Bizapp SKU is exist,
        // we just sync it instead of import,
        // If not exist, we will import it first
        if ( $this->maybe_sync( $product_data ) ) {
            return true;
        } elseif ( $this->maybe_import( $product_data ) ) {
            return true;
        } else {
            return false;
        }

    }

    // Sanitize Bizapp product data
    private function get_product_data( $product_data ) {

        return array(
            'sku'             => !empty( $product_data['productsku'] ) ? sanitize_text_field( $product_data['productsku'] ) : null,
            'name'            => !empty( $product_data['productname'] ) ? sanitize_text_field( $product_data['productname'] ) : null,
            'desc'            => !empty( $product_data['productdesc'] ) ? sanitize_text_field( $product_data['productdesc'] ) : null,
            'price'           => !empty( $product_data['price'] ) ? sanitize_text_field( $product_data['price'] ) : null,
            'stock'           => !empty( $product_data['stockbalance'] ) ? (int) sanitize_text_field( $product_data['stockbalance'] ) : null,
            'featured_image'  => !empty( $product_data['attachment'] ) ? sanitize_text_field( $product_data['attachment'] ) : null,
            'gallery_image_1' => !empty( $product_data['attachmentweb1'] ) ? sanitize_text_field( $product_data['attachmentweb1'] ) : null,
            'gallery_image_2' => !empty( $product_data['attachmentweb2'] ) ? sanitize_text_field( $product_data['attachmentweb2'] ) : null,
            'gallery_image_3' => !empty( $product_data['attachmentweb3'] ) ? sanitize_text_field( $product_data['attachmentweb3'] ) : null,
        );

    }

    // If product with specified Bizapp SKU is exist, we just sync it instead of import
    private function maybe_sync( $product_data ) {

        // Check if same product is exist by checking Bizapp product SKU
        $product = get_posts( array(
            'numberposts' => 1,
            'post_type'   => array( 'product', 'product_variation' ),
            'post_status' => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit' ), // All status except trash
            'meta_key'    => '_bizapp_product_sku',
            'meta_value'  => $product_data['sku'],
            'fields'      => 'ids',
        ) );

        $product_id = !empty( $product[0] ) ? $product[0] : null;

        // Sync the product details based on user selected options in plugin settings
        if ( $product_id ) {
            $auto_sync = bizapp_woocommerce_get_setting( 'auto_sync_product' );

            $updated_product_data = array( 'ID' => $product_id );

            if ( !empty( $product_data['name'] ) && in_array( 'name', $auto_sync ) ) {
                $updated_product_data['post_title'] = $product_data['name'];
            }
            if ( !empty( $product_data['desc'] ) && in_array( 'desc', $auto_sync ) ) {
                $updated_product_data['post_content'] = $product_data['desc'];
            }
            if ( !empty( $product_data['price'] ) && in_array( 'price', $auto_sync ) ) {
                $updated_product_data['meta_input']['_price'] = $product_data['price'];
            }

            // If product stock is checked for auto sync, update it
            if ( in_array( 'stock', $auto_sync ) ) {
                $updated_product_data['meta_input']['_manage_stock'] = $product_data['stock'] > 0 ? 'yes' : 'no';
                $updated_product_data['meta_input']['_stock_status'] = $product_data['stock'] > 0 ? 'instock' : 'outofstock';
                $updated_product_data['meta_input']['_stock'] = $product_data['stock'];
            }

            $current_featured_image_url = get_the_post_thumbnail_url( $product_id, 'full' );

            // If current featured image file name is not same with featured image file name received from Bizapp request
            // And if product featured image is checked for auto sync, update it
            if (
                !empty( $product_data['featured_image'] )
                && basename( $current_featured_image_url ) !== basename( $product_data['featured_image'] )
                && in_array( 'featured_image', $auto_sync )
            ) {
                $featured_image_id = $this->upload_image( $product_data['featured_image'] );
                $updated_product_data['meta_input']['_thumbnail_id'] = $featured_image_id;
            }

            // If product gallery images is checked for auto sync, update it
            if ( in_array( 'gallery_images', $auto_sync ) ) {
                // Current product gallery images
                $current_gallery_images = explode( ',', get_post_meta( $product_id, '_product_image_gallery', true ) );

                // Upload product gallery images
                $gallery_images = array();
                for ( $i=1; $i <= 3; $i++ ) {
                    // Current product gallery image URL
                    $current_gallery_image_url = !empty( $current_gallery_images[ $i-1 ] )
                                                    ? wp_get_attachment_url( $current_gallery_images[ $i-1 ] )
                                                    : null;

                    // Product gallery image URL received from Bizapp
                    $gallery_image_url = $product_data[ 'gallery_image_' . $i ];

                    // Check if current image file name is same with image file name received from Bizapp request
                    // If same, then skip uploading and updating product gallery images
                    if ( basename( $current_gallery_image_url ) == basename( $gallery_image_url ) ) {
                        continue;
                    }

                    if ( $gallery_attachment_id = $this->upload_image( $gallery_image_url ) ) {
                        $gallery_images[] = $gallery_attachment_id;
                    }
                }

                if ( !empty( $gallery_images ) ) {
                    $updated_product_data['meta_input']['_product_image_gallery'] = implode( ',', $gallery_images );
                }
            }

            wp_update_post( $updated_product_data );
            return true;
        }

        return false;

    }

    // If product with specified Bizapp SKU is not exist, we will import it first
    private function maybe_import( $product_data ) {

        // Check if auto import is enabled and the product data is not empty
        if ( bizapp_woocommerce_get_setting( 'auto_import_product' ) !== '1'|| !$product_data['name'] ) {
            return false;
        }

        // Upload featured image
        $featured_image = $this->upload_image( $product_data['featured_image'] );

        // Upload product gallery images
        $gallery_images = array();
        for ( $i=1; $i <= 3; $i++ ) { 
            if ( $gallery_attachment_id = $this->upload_image( $product_data[ 'gallery_image_' . $i ] ) ) {
                $gallery_images[] = $gallery_attachment_id;
            }
        }

        // Create WooCommerce product
        $product_id = wp_insert_post( array(
            'post_title'   => $product_data['name'],
            'post_content' => $product_data['desc'],
            'post_type'    => 'product',
            'meta_input'   => array(
                '_bizapp_product_sku'    => $product_data['sku'],
                '_sku'                   => $product_data['sku'],
                '_price'                 => $product_data['price'],
                '_manage_stock'          => $product_data['stock'] > 0 ? 'yes' : 'no',
                '_stock'                 => $product_data['stock'],
                '_thumbnail_id'          => $featured_image,
                '_product_image_gallery' => implode( ',', $gallery_images ),
            ),
        ) );

        if ( !$product_id ) {
            return false;
        }

        // Set product type as simple
        wp_set_object_terms( $product_id, 'simple', 'product_type' );

    }

    // Upload image to WP media
    private function upload_image( $url ) {

        if ( !$url ) {
            return false;
        }

        // All required dependencies
        require_once( ABSPATH . 'wp-includes/pluggable.php' );
        require_once( ABSPATH . 'wp-includes/class-wp-rewrite.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        $GLOBALS['wp_rewrite'] = new WP_Rewrite();

        $tmp = download_url( $url );

        $file_array = array(
            'name' => basename( $url ),
            'tmp_name' => $tmp,
        );

        // Check for download errors
        if ( is_wp_error( $tmp ) ) {
            @unlink( $file_array['tmp_name'] );
            return false;
        }

        // Upload to WP media
        $attachment_id = media_handle_sideload( $file_array, 10 );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $file_array['tmp_name'] );
            return false;
        }

        // Return attachment ID if everything is okay
        return $attachment_id;

    }

}
new Bizapp_Woocommerce_Sync();
