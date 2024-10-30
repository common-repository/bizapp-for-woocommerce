<?php
/**
 * Plugin Name: Bizapp for WooCommerce V2.0.8
 * Plugin URI:  mailto:yiedpozi@gmail.com
 * Description: Bizapp integration for WooCommerce.
 * Version:     2.0.8
 * Author:      Bizapp Ventures Sdn. Bhd.
 * Author URI:  https://bizapp.my/
 */

if ( !defined( 'ABSPATH' ) ) exit;

define( 'BIZAPP_WOOCOMMERCE_FILE', __FILE__ );
define( 'BIZAPP_WOOCOMMERCE_URL', plugin_dir_url( BIZAPP_WOOCOMMERCE_FILE ) );
define( 'BIZAPP_WOOCOMMERCE_PATH', plugin_dir_path( BIZAPP_WOOCOMMERCE_FILE ) );
define( 'BIZAPP_WOOCOMMERCE_BASENAME', plugin_basename( BIZAPP_WOOCOMMERCE_FILE ) );
define( 'BIZAPP_WOOCOMMERCE_VERSION', '2.0.8' );

require( BIZAPP_WOOCOMMERCE_PATH . 'includes/class-bizapp-woocommerce.php' );


add_action( 'sync_bizapp_product', 'sync_bizapp_product_callback' );


function sync_bizapp_product_callback( $product_data ) {
 
    $created_product_id = create_woocommerce_product( $product_data );
    if ( $created_product_id ) {
        error_log( 'Created WooCommerce product with ID: ' . $created_product_id );
    } else {
        error_log( 'Failed to create WooCommerce product' );
    }
}

// function create_woocommerce_product( $product_data ) {

//     $product_id = wc_get_product_id_by_sku( $product_data['productsku'] );
//     if ( $product_id ) {
// 	$product = wc_get_product($product_id);

//     }

//     if ( $product ) {
//         // Product SKU already exists, update the product
//         $product->set_name( $product_data['productname'] );
//         $product->set_description( $product_data['productdesc'] );
//         $product->set_regular_price( $product_data['price'] );
//         $product->set_stock_quantity( $product_data['stockbalance'] );
//         $product->save();

//         // Set product images
//         if ( !empty( $product_data['attachment'] ) ) {
//             $image_id = download_and_attach_image( $product_data['attachment'], $product->get_id() );
//             $product->set_image_id( $image_id );
//         }

//         $gallery_image_ids = array();
//         for ( $i = 1; $i <= 3; $i++ ) {
//             if ( !empty( $product_data[ 'attachmentweb' . $i ] ) ) {
//                 $gallery_image_id = download_and_attach_image( $product_data[ 'attachmentweb' . $i ], $product->get_id() );
//                 $gallery_image_ids[] = $gallery_image_id;
//             }
//         }
//         if ( !empty( $gallery_image_ids ) ) {
//             $product->set_gallery_image_ids( $gallery_image_ids );
//         }

//         // Save the product with images
//         $product->save();

//         error_log( 'Updated product ID: ' . $product->get_id() );

//     } else {
//     $product = new WC_Product();

//     // Set product data
//     $product->set_sku( $product_data['productsku'] );
//     $product->set_name( $product_data['productname'] );
//     $product->set_description( $product_data['productdesc'] );
//     $product->set_regular_price( $product_data['price'] );
//     $product->set_stock_quantity( $product_data['stockbalance'] );
//     $product->set_manage_stock( true );
//     // Add data for _sku
//     $product->update_meta_data( '_bizapp_product_sku', $product_data['productsku'] );		
//     $product->set_status( 'publish' ); 
//     // Save the product
//     $product_id = $product->save();

//     // Set product images
//     if ( !empty( $product_data['attachment'] ) ) {
//         $image_id = download_and_attach_image( $product_data['attachment'], $product_id );
//         $product->set_image_id( $image_id );
//     }

//     $gallery_image_ids = array();
//     for ( $i = 1; $i <= 3; $i++ ) {
//         if ( !empty( $product_data[ 'attachmentweb' . $i ] ) ) {
//             $gallery_image_id = download_and_attach_image( $product_data[ 'attachmentweb' . $i ], $product_id );
//             $gallery_image_ids[] = $gallery_image_id;
//         }
//     }
//     if ( !empty( $gallery_image_ids ) ) {
//         $product->set_gallery_image_ids( $gallery_image_ids );
//     }

//     // Save the product with images
//     $product->save();	
//     error_log( 'Created product ID: ' . $product_id );

//     return $product_id;
//     }

//     return $product_id;
// }

//v2 checking for unlimited

function create_woocommerce_product( $product_data ) {
    // Ensure product_data is an array
    if (!is_array($product_data)) {
        error_log('Product data is not an array');
        return false;
    }

    // Ensure all required keys are present
    $required_keys = ['productsku', 'productname', 'productdesc', 'price', 'stockbalance'];
    foreach ($required_keys as $key) {
        if (!array_key_exists($key, $product_data)) {
            error_log("Missing key in product data: $key");
            return false;
        }
    }

    error_log('Product data received: ' . print_r($product_data, true));

    $product_id = wc_get_product_id_by_sku( $product_data['productsku'] );
    $product = $product_id ? wc_get_product( $product_id ) : new WC_Product();

    if ($product_id) {
        error_log('Existing product found with ID: ' . $product_id);
    } else {
        error_log('No existing product found, creating new product');
    }

    // Set product data
    $product->set_sku( $product_data['productsku'] );
    $product->set_name( $product_data['productname'] );
    $product->set_description( $product_data['productdesc'] );
    $product->set_regular_price( $product_data['price'] );

    // Handle stock balance
    if ($product_data['stockbalance'] === 'UNLIMITED') {
        $product->set_manage_stock( false );
        $product->set_stock_status( 'instock' );
        error_log('Setting product stock to UNLIMITED');
    } else {
        $product->set_manage_stock( true );
        $product->set_stock_quantity( $product_data['stockbalance'] );
        $product->set_stock_status( 'instock' );
        error_log('Setting product stock quantity to: ' . $product_data['stockbalance']);
    }

    $product->set_status( 'publish' );

    // Add or update meta data for _sku
    $product->update_meta_data( '_bizapp_product_sku', $product_data['productsku'] );

    // Save the product
    $product_id = $product->save();

    // Set product images
    if ( ! empty( $product_data['attachment'] ) ) {
        $image_id = download_and_attach_image( $product_data['attachment'], $product_id );
        $product->set_image_id( $image_id );
    }

    // Set product gallery images
    $gallery_image_ids = [];
    for ( $i = 1; $i <= 3; $i++ ) {
        if ( ! empty( $product_data[ 'attachmentweb' . $i ] ) ) {
            $gallery_image_id = download_and_attach_image( $product_data[ 'attachmentweb' . $i ], $product_id );
            $gallery_image_ids[] = $gallery_image_id;
        }
    }
    if ( ! empty( $gallery_image_ids ) ) {
        $product->set_gallery_image_ids( $gallery_image_ids );
    }

    // Save the product with images
    $product->save();

    // Ensure stock status is updated for existing products
    if ($product_id) {
        $product->set_stock_status($product_data['stockbalance'] > 0 ? 'instock' : 'outofstock');
        $product->save();
    }

    return $product_id;
}



function download_and_attach_image( $image_url, $product_id ) {
    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents( $image_url );
    $filename = basename( $image_url );

    if ( wp_mkdir_p( $upload_dir['path'] ) ) {
        $file = $upload_dir['path'] . '/' . $filename;
    } else {
        $file = $upload_dir['basedir'] . '/' . $filename;
    }

    file_put_contents( $file, $image_data );

    $wp_filetype = wp_check_filetype( $filename, null );
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title'     => sanitize_file_name( $filename ),
        'post_content'   => '',
        'post_status'    => 'inherit',
    );

    $attach_id = wp_insert_attachment( $attachment, $file, $product_id );

    require_once( ABSPATH . 'wp-admin/includes/image.php' );
    $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
    wp_update_attachment_metadata( $attach_id,$attach_data );
	return $attach_id;
}

