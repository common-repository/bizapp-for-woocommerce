<?php
if ( !defined( 'ABSPATH' ) ) exit;

class Bizapp_Woocommerce_Order {

    private $api;

    // Register hooks
    public function __construct() {

        add_action( 'woocommerce_payment_complete', array( $this, 'create_bizapp_order' ) );
        add_action( 'woocommerce_order_status_processing', array( $this, 'create_bizapp_order' ) );

        // Action button and bulk action
        add_action( 'woocommerce_admin_order_actions', array( $this, 'register_order_actions' ), 10, 2 );
        
		add_action( 'wp_ajax_bizapp_woocommerce_create_order', array( $this, 'handle_manual_create_bizapp_order' ) );
		
		// Register Bulk Actions
        add_action( 'bulk_actions-edit-shop_order', array( $this, 'register_bulk_actions' ), 30 );	
		// HPOS Register Bulk Actions
		add_action( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'register_bulk_actions' ), 30 );
		// Handle Bulk Actions
        add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handle_bulk_create_bizapp_order' ), 10, 3 );
		// HPOS Handle Bulk Actions
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'hpos_handle_bulk_create_bizapp_order' ), 10, 3 );
		
        add_action( 'admin_notices', array( $this, 'print_create_bizapp_order_notice' ) );

        // Columns
        add_filter( 'manage_edit-shop_order_columns', array( $this, 'register_columns' ) );
		// HPOS Columns
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'register_columns' ) );
		// Populte 
        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'populate_columns' ), 10, 2 );
		// HPOS Populte
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'hpos_populate_columns' ), 10, 2 );
        // Tracking details in order details table
        add_action( 'woocommerce_get_order_item_totals', array( $this, 'display_tracking_order_details' ), 10, 2 );

    }

    // Create order in Bizapp when new order received in WooCommerce
    public function create_bizapp_order( $order_id ) {
        // Check if auto push order is enabled
        if ( bizapp_woocommerce_get_setting( 'auto_push_order' ) !== '1' ) {
            return;
        }

        $order = wc_get_order( $order_id );

        // Return false if order not exist or secret key for API is empty
        if ( !$order || !$this->init_api() ) {
            return false;
        }

        // Send order data to Bizapp
        if ( $order_data = $this->hpos_get_order_data( $order ) ) {
            list( $code, $response ) = $this->api->submit_order( $order_data );
            
            //v2
            if(isset($response['result'][0]['ID']) && !empty($response['result'][0]['ID'])){
                $this->hpos_update_order_metadata( $order_id );
                return true;
            }

            //v1
            // if ( isset( $response[0]['STATUS'] ) && $response[0]['STATUS'] == '1' ) {
            //     $this->update_order_metadata( $order_id );
            //     return true;
            // }
        } else {

        }

        return false;

    }

    // Update order meta data
    private function update_order_metadata( $order_id ) {
        update_post_meta( $order_id, '_bizapp_order_created', current_time( 'timestamp' ) );
    }

    // HPOS Update order meta data
    private function hpos_update_order_metadata( $order_id ) {
		error_log('Updating meta data : '.$order_id);
        $order = wc_get_order( $order_id );

        if ( $order ) {
			error_log('Order found : '.$order_id);
            $order->update_meta_data( '_bizapp_order_created', current_time( 'timestamp' ) );
            $order->save();
			$created_date = $order->get_meta( '_bizapp_order_created' );
			error_log('Meta data found : '.$created_date);			
        }
    }	
    // Initialize API
    private function init_api() {

        $secret_key = bizapp_woocommerce_get_setting( 'secret_key' );

        // Check if secret key is not empty
        if ( !$secret_key ) {
            return false;
        }

        $this->api = new Bizapp_Woocommerce_Api( $secret_key );
        return $this->api;

    }
	
private function hpos_get_order_data( $order ) {

    // Customer name and address
    if ( $order->has_shipping_address() ) {
        $name    = $order->get_formatted_shipping_full_name();
        $country = $order->get_shipping_country();
        $state   = $order->get_shipping_state();

        $address = array(
            $order->get_shipping_address_1(),
            $order->get_shipping_address_2(),
            $order->get_shipping_postcode(),
            $order->get_shipping_city(),
            $this->get_state_name_by_code( $country, $state ),
            $this->get_country_name_by_code( $country ),
        );
    } else {
        $name    = $order->get_formatted_billing_full_name();
        $country = $order->get_billing_country();
        $state   = $order->get_billing_state();

        $address = array(
            $order->get_billing_address_1(),
            $order->get_billing_address_2(),
            $order->get_billing_postcode(),
            $order->get_billing_city(),
            $this->get_state_name_by_code( $country, $state ),
            $this->get_country_name_by_code( $country ),
        );
    }

    $order_data = array(
        'name'                  => $name,
        'address'               => implode( ', ', array_filter( $address ) ),
        'hpno'                  => $order->get_billing_phone(),
        'email'                 => $order->get_billing_email(),
        'sellingprice'          => $order->get_total(),
        'postageprice'          => $order->get_shipping_total(),
        'note'                  => $order->get_customer_note(),
        'woo_url'               => get_site_url(),
        'woo_orderid'           => $order->get_id(),
        'woo_paymentgateway'    => $order->get_payment_method_title(),
        'woo_payment_txn'       => $order->get_transaction_id(),
        'woo_paymentgateway_id' => $order->get_payment_method(),
        'woo_shipping_method'   => $order->get_shipping_method(),
    );

    //V2
    $items = [];
    foreach($order->get_items() as $item){
        $product_id = $item->get_product_id();
        $product_variation_id = $item->get_variation_id() ?: $product_id;
        $product = wc_get_product($product_variation_id);
        $product_sku = $product ? $product->get_meta( '_bizapp_product_sku' ) : '';

        if ( empty( $product_sku ) ) {
            $product = wc_get_product($product_id);
            $product_sku = $product ? $product->get_meta( '_bizapp_product_sku' ) : '';
        }

        if($product_sku){
            $items[] = [
                'sku' => $product_sku,
                'quantity' => $item->get_quantity(),
            ];
        }
    }

    if(count($items) > 0){
        $order_data['products_info'] = $items;
        return $order_data;
    }

    return false;
}
    // Get order data for specified order
    private function get_order_data( $order ) {

        // Customer name and address
        if ( $order->has_shipping_address() ) {
            $name    = $order->get_formatted_shipping_full_name();
            $country = $order->get_shipping_country();
            $state   = $order->get_shipping_state();

            $address = array(
                $order->get_shipping_address_1(),
                $order->get_shipping_address_2(),
                $order->get_shipping_postcode(),
                $order->get_shipping_city(),
                $this->get_state_name_by_code( $country, $state ),
                $this->get_country_name_by_code( $country ),
            );
        } else {
            $name    = $order->get_formatted_billing_full_name();
            $country = $order->get_billing_country();
            $state   = $order->get_billing_state();

            $address = array(
                $order->get_billing_address_1(),
                $order->get_billing_address_2(),
                $order->get_billing_postcode(),
                $order->get_billing_city(),
                $this->get_state_name_by_code( $country, $state ),
                $this->get_country_name_by_code( $country ),
                // $country_name,
            );
        }

        $order_data = array(
            'name'                  => $name,
            'address'               => implode( ', ', array_filter( $address ) ),
            'hpno'                  => $order->get_billing_phone(),
            'email'                 => $order->get_billing_email(),
            'sellingprice'          => $order->get_total(),
            'postageprice'          => $order->get_shipping_total(),
            'note'                  => $order->get_customer_note(),
            'woo_url'               => get_site_url(),
            'woo_orderid'           => $order->get_id(),
            'woo_paymentgateway'    => $order->get_payment_method_title(),
            'woo_payment_txn'       => $order->get_transaction_id(),
            'woo_paymentgateway_id' => $order->get_payment_method(),
            'woo_shipping_method'   => $order->get_shipping_method(),
        );

        //V2
        $items = [];
        foreach($order->get_items() as $item){
            $product_id = $item->get_product_id();
            $product_variation_id = $item->get_variation_id() ?: $product_id;
            $product_sku = get_post_meta( $product_variation_id, '_bizapp_product_sku', true );
            if ( empty( $product_sku ) ) {
                $product_sku = get_post_meta( $product_id, '_bizapp_product_sku', true );
            }
            if($product_sku){
                $items[] = [
                    'sku' => $product_sku,
                    'quantity' => $item->get_quantity(),
                ];
            }
        }


        if(count($items) > 0){
            $order_data['products_info'] = $items;
            return $order_data;
        }

        return false;

        // $i = 0;

        // Go through each order items
        // foreach ( $order->get_items() as $item_id => $item ) {
        //     // $i++;

        //     $product_id = $item->get_product_id();
        //     $product_variation_id = $item->get_variation_id() ?: $product_id;

        //     $product_sku = get_post_meta( $product_variation_id, '_bizapp_product_sku', true );

        //     // If it is variation, sometimes admin set Bizapp SKU on the product, not on the variation
        //     // So, we need to check if there have SKU set for the product if the SKU on the variation is empty.
        //     if ( empty( $product_sku ) ) {
        //         $product_sku = get_post_meta( $product_id, '_bizapp_product_sku', true );
        //     }

        //     // If Bizapp product SKU is not specified
        //     if ( $product_sku ) {
        //         $items[] = [
        //             'sku' => $product_sku,
        //             'quantity' => $item->get_quantity(),
        //         ];
        //         // $order_data[ 'productsku' . $i ] = $product_sku;
        //         // $order_data[ 'quantity' . $i ] = $item->get_quantity();
        //     }
        // }

        // Return order data if has Bizapp product SKU and quantity in the order data
        // if ( !empty( $order_data['productsku1'] ) && !empty( $order_data['quantity1'] ) ) {
        //     return $order_data;
        // }

        return false;

    }

    // Get state name by code
    private function get_state_name_by_code( $country_code, $state_code ) {

        $states = WC()->countries->get_states( $country_code );

        return isset( $states[ $state_code ] ) ? $states[ $state_code ] : $state_code;

    }

    // Get country name by code
    private function get_country_name_by_code( $country_code ) {

        $countries = WC()->countries->countries;

        return isset( $countries[ $country_code ] ) ? $countries[ $country_code ] : $country_code;

    }

// Register Send to Bizapp action button in order list page
public function register_order_actions( $actions, $order ) {
    $secret_key = bizapp_woocommerce_get_setting( 'secret_key' );

    // Show Send to Bizapp button only if:
    // - the secret key is not empty
    // - the order status is not failed or cancelled
    if ( $secret_key && ! in_array( $order->get_status(), array( 'failed', 'cancelled' ), true ) ) {
        $order_id = $order->get_id(); // Ensure we're using the CRUD method to get the order ID
        $actions['bizapp_woocommerce_create_order'] = array(
            'url'    => wp_nonce_url( admin_url( 'admin-ajax.php?action=bizapp_woocommerce_create_order&order_id=' . $order_id ), 'bizapp_woocommerce_create_order_nonce' ),
            'name'   => __( 'Send to Bizapp', 'bizapp-woocommerce' ),
            'action' => 'bizapp-woocommerce-create-order',
        );
    }

    return $actions;
}


	


    // Handle manual Bizapp order creation
    public function handle_manual_create_bizapp_order() {

        // Verify nonce
        if ( !wp_verify_nonce( $_REQUEST['_wpnonce'], 'bizapp_woocommerce_create_order_nonce' ) ) {
            wp_die( __( 'Invalid nonce specified.', 'bizapp-woocommerce' ) );
        }

        // Get the order
        $order_id = !empty( $_REQUEST['order_id'] ) ? $_REQUEST['order_id'] : null;
        $order = wc_get_order( $order_id );

        //////////////////////////////////////////////////////////

        // If the order not exist
        if ( !$order ) {
            wp_die( __( 'Order not exist.', 'bizapp-woocommerce' ) );
        }

        // If secret key for API is empty
        if ( !$this->init_api() ) {
            wp_die( __( 'Bizapp secret key is empty.', 'bizapp-woocommerce' ) );
        }

        // If the order is failed or cancelled
        if ( $order->has_status( array( 'failed', 'cancelled' ) ) ) {
            wp_die( __( 'Cannot send failed or cancelled order to Bizapp.', 'bizapp-woocommerce' ) );
        }

        //////////////////////////////////////////////////////////

        $redirect = admin_url( 'edit.php?post_type=shop_order' );
        $action_result = 'error';

        // Send order data to Bizapp
        if ( $order_data = $this->get_order_data( $order ) ) {
            list( $code, $response ) = $this->api->submit_order( $order_data );

            //v2
            if($response['status'] == "success"){
                if(isset($response['result'][0]['ID']) && !empty($response['result'][0]['ID'])){
                    $action_result = 'success';
                    $this->update_order_metadata( $order_id );
                } else {
                    $redirect = add_query_arg( 'error', $response['error_message'] ?? "Failed send order to Bizapp", $redirect );
                }
            } else {
                $redirect = add_query_arg( 'error', $response['error_message'] ?? "Failed to connect Bizapp", $redirect );
            }

            //v1
            // if ( isset( $response[0]['STATUS'] ) && $response[0]['STATUS'] == '1' ) {
            //     $action_result = 'success';
            //     $this->update_order_metadata( $order_id );
            // }
            // // Format error message if have, and add to redirect query args
            // elseif ( $error = $this->format_error_message( $response ) ) {
            //     $redirect = add_query_arg( 'error', $error, $redirect );
            // }
        }

        //////////////////////////////////////////////////////////

        $redirect = add_query_arg( array(
            'bizapp_woocommerce_create_order' => $action_result,
            'order_id' => $order_id,
        ), $redirect );

        wp_redirect( $redirect );
        exit;

    }

    // Register Send to Bizapp bulk action
    public function register_bulk_actions( $bulk_actions ) {

        // Show Send to Bizapp bulk action only if the secret key is specified
        if ( bizapp_woocommerce_get_setting( 'secret_key' ) ) {
            $bulk_actions['bizapp_woocommerce_create_order'] = __( 'Send to Bizapp', 'bizapp-woocommerce' );
        }

        return $bulk_actions;

    }
	

    // Handle bulk Bizapp order creation
    public function handle_bulk_create_bizapp_order( $redirect, $do_action, $order_ids ) {

        // Remove existing parameter from redirect URL
        $redirect = remove_query_arg( array(
            'bizapp_woocommerce_create_order',
            'order_id',
            'order_ids',
            'failed_order_ids',
        ), $redirect );

        // Redirect back if wrong action, or secret key is not specified
        if ( $do_action !== 'bizapp_woocommerce_create_order' || !$this->init_api() ) {
            return $redirect;
        }

        sort( $order_ids );
        $failed_order_ids = array();
        $message_failed_order_ids = array();

        foreach ( $order_ids as $order_id ) {
            // Get the order
            $order = wc_get_order( $order_id );
			

            // Skip the process when one of the conditions matched:
            // - the order not found
            // - the order is failed or cancelled
            if ( !$order || $order->has_status( array( 'failed', 'cancelled' ) ) ) {
                continue;
            }

            // Send order data to Bizapp V2
            if ( $order_data = $this->get_order_data( $order ) ) {
                list( $code, $response ) = $this->api->submit_order( $order_data );

                if($response['status'] == "success"){
                    if(isset($response['result'][0]['ID']) && !empty($response['result'][0]['ID'])){
                        $this->update_order_metadata( $order_id );
                    } else {
                        $failed_order_ids[] = $order_id;
                        $message_failed_order_ids[] = $response['error_message'] ?? "Error 1";
                    }
                } else {
                    $failed_order_ids[] = $order_id;
                    $message_failed_order_ids[] = $response['error_message'] ?? "Error 2";
                }
            }

            // Send order data to Bizapp
            // if ( $order_data = $this->get_order_data( $order ) ) {
            //     list( $code, $response ) = $this->api->submit_order( $order_data );

            //     if ( isset( $response[0]['STATUS'] ) && $response[0]['STATUS'] == '1' ) {
            //         $this->update_order_metadata( $order_id );
            //     } else {
            //         $failed_order_ids[] = $order_id;
            //     }
            // }
        }

        // Always return as success
        $redirect = add_query_arg( 'bizapp_woocommerce_create_order', 'success', $redirect );

        if ( !empty( $failed_order_ids ) ) {
            $redirect = add_query_arg( 'failed_order_ids', implode( '|', $failed_order_ids ), $redirect );
            $redirect = add_query_arg( 'message_fails', implode('|', $message_failed_order_ids), $redirect);
        }

        return $redirect;

    }

// Handle HPOS bulk Bizapp order creation
public function hpos_handle_bulk_create_bizapp_order( $redirect, $do_action, $order_ids ) {

    // Remove existing parameters from the redirect URL
    $redirect = remove_query_arg( array(
        'bizapp_woocommerce_create_order',
        'order_id',
        'order_ids',
        'failed_order_ids',
        'message_fails',
    ), $redirect );

    // Redirect back if the wrong action, or secret key is not specified
    if ( $do_action !== 'bizapp_woocommerce_create_order' || !$this->init_api() ) {
        return $redirect;
    }

    sort( $order_ids );
    $failed_order_ids = array();
    $message_failed_order_ids = array();

    foreach ( $order_ids as $order_id ) {
		error_log('Handeling order ID'.$order_id);
        // Get the order
        $order = wc_get_order( $order_id );

        // Skip the process when one of the conditions matched:
        // - the order not found
        // - the order is failed or cancelled
        if ( !$order || $order->has_status( array( 'failed', 'cancelled' ) ) ) {
			error_log('Status wrong'.$order_id);
            continue;
        }
		error_log('Status Okay'.$order_id);
        // Send order data to Bizapp V2
        if ( $order_data = $this->hpos_get_order_data( $order ) ) {
            list( $code, $response ) = $this->api->submit_order( $order_data );
			//error_log('Submitted to API'.$response);
			error_log('Submitted to API Response: ' . print_r($response, true)); 

            if ($response['status'] == "success") {
				error_log("successfully Synced");
                if (isset($response['result'][0]['ID']) && !empty($response['result'][0]['ID'])) {
                    $this->hpos_update_order_metadata( $order_id );
                } else {
                    $failed_order_ids[] = $order_id;
                    $message_failed_order_ids[] = $response['error_message'] ?? "Error 1";
                }
            } else {
				error_log("Sync Failed");
                $failed_order_ids[] = $order_id;
                $message_failed_order_ids[] = $response['error_message'] ?? "Error 2";
            }
        }
    }

    // Always return as success
    $redirect = add_query_arg( 'bizapp_woocommerce_create_order', 'success', $redirect );

    if ( !empty( $failed_order_ids ) ) {
        $redirect = add_query_arg( 'failed_order_ids', implode( '|', $failed_order_ids ), $redirect );
        $redirect = add_query_arg( 'message_fails', implode('|', $message_failed_order_ids), $redirect );
    }

    return $redirect;
}
	
    // Display notice based on action result (success/error)
    public function print_create_bizapp_order_notice() {

        if ( empty( $_REQUEST['bizapp_woocommerce_create_order'] ) ) {
            return;
        }

        $plugin  = esc_html__( 'Bizapp for WooCommerce', 'bizapp-woocommerce' );
        $class   = null;
        $message = null;

        // Success/error message
        if ( $_REQUEST['bizapp_woocommerce_create_order'] == 'success' ) {
            $class = 'notice notice-success';

            if ( !empty( $_REQUEST['order_id'] ) ) {
                $message = sprintf( esc_html__( 'Order #%d has been successfully sent to Bizapp.', 'bizapp-woocommerce' ), esc_html( $_REQUEST['order_id'] ) );
            } else {
                $message = sprintf( esc_html__( 'Selected order(s) has been successfully sent to Bizapp.', 'bizapp-woocommerce' ), esc_html( $_REQUEST['order_id'] ) );
            }
        } elseif ( $_REQUEST['bizapp_woocommerce_create_order'] == 'error' ) {
            $class = 'notice notice-error';

            if ( !empty( $_REQUEST['order_id'] ) ) {
                $message = sprintf( esc_html__( 'Order #%d failed to send to Bizapp.', 'bizapp-woocommerce' ), esc_html( $_REQUEST['order_id'] ) );
            } else {
                $message = sprintf( esc_html__( 'Selected order(s) failed to send to Bizapp.', 'bizapp-woocommerce' ), esc_html( $_REQUEST['order_id'] ) );
            }
        }

        // Specific error message from Bizapp API response
        if ( !empty( $_REQUEST['error'] ) ) {
            $message .= esc_html__( ' Error: ', 'bizapp-woocommerce' );
            $message .= '<strong>' . $_REQUEST['error'] . '</strong>';
        }

        // Error mesage including failed order IDs
        if ( !empty( $_REQUEST['failed_order_ids'] ) ) {
            $message .= '<br><br>' . esc_html__( 'List of failed order(s): ' );
            $message .= '<ol><li>#' . str_replace( '|', '</li><li>#', esc_html( $_REQUEST['failed_order_ids'] ) ) . '</li></ol>';
        }

        if(!empty($_REQUEST['message_fails'])){
            $message .= '<br><br>' . esc_html__( 'List of messages failed order(s): ' );
            $message .= '<ol><li>#' . str_replace( '|', '</li><li>#', esc_html( $_REQUEST['message_fails'] ) ) . '</li></ol>';
        }
 
        if ( $class && $message ) {
            printf( '<div class="%1$s"><p><strong>%2$s:</strong> %3$s</p></div>', esc_attr( $class ), esc_html( $plugin ), $message );
        }

    }

    // Format error message based on Bizapp API response
    private function format_error_message( $response ) {

        $status = isset( $response[0]['STATUS'] ) ? $response[0]['STATUS'] : null;
        $doublecheck = isset( $response[0]['DOUBLECHECK'] ) ? $response[0]['DOUBLECHECK'] : null;

        if ( $status && $doublecheck ) {
            return esc_html( $doublecheck );
        } elseif ( $status ) {
            return esc_html( $status );
        }

        return;

    }

    // Register additional column - tracking details
    public function register_columns( $columns ) {

        $new_columns = array();

        foreach ( $columns as $name => $value ) {
            $new_columns[ $name ] = $value;

            // Register tracking details column after shipping address column
            if ( $name == 'shipping_address' ) {
                $new_columns['bizapp_woocommerce_tracking'] = __( 'Tracking Details', 'bizapp-woocommerce' );
                $new_columns['bizapp_woocommerce_created'] = __( 'Sent to Bizapp', 'bizapp-woocommerce' );
            }
        }

        return $new_columns;

    }

    // Populate additional column - tracking details
    public function populate_columns( $column, $order_id ) {

        global $the_order;

        switch ( $column ) {

            case 'bizapp_woocommerce_tracking':

                $tracking = get_post_meta( $order_id, '_bizapp_tracking_no', true );
                $courier_name = get_post_meta( $order_id, '_bizapp_courier_name', true );

                if ( $tracking && $courier_name ) {
                    $tracking .= ' (' . $courier_name . ')';
                }

                echo $tracking ?: '–';
                break;

            case 'bizapp_woocommerce_created':
                $created_date = get_post_meta( $order_id, '_bizapp_order_created', true );
                echo $created_date ? date( "Y/m/d \\a\\t h:i a", $created_date ) : '–' ;
                break;
        }

    }
	
	// HPOS Populate additional column - tracking details
    public function hpos_populate_columns( $column, $post_id ) {
        if ( 'bizapp_woocommerce_tracking' === $column ) {
            $order = wc_get_order( $post_id );
            if ( $order ) {
                $tracking = $order->get_meta( '_bizapp_tracking_no' );
                $courier_name = $order->get_meta( '_bizapp_courier_name' );

                if ( $tracking && $courier_name ) {
                    $tracking .= ' (' . $courier_name . ')';
                }

                echo $tracking ? esc_html( $tracking ) : '–';
            }
        }

        if ( 'bizapp_woocommerce_created' === $column ) {
            $order = wc_get_order( $post_id );
            if ( $order ) {
                $created_date = $order->get_meta( '_bizapp_order_created' );
                echo $created_date ? esc_html( date( "Y/m/d \\a\\t h:i a", $created_date ) ) : '–';
            }
        }
    }	

    // Display tracking info in order details table
    public function display_tracking_order_details( $total_rows, WC_Order $order ) {

        $tracking = get_post_meta( $order->get_id(), '_bizapp_tracking_no', true );
        $courier_name = get_post_meta( $order->get_id(), '_bizapp_courier_name', true );

        if ( $tracking && $courier_name ) {
            $tracking .= ' (' . $courier_name . ')';
        }

        if ( !empty( $tracking ) ) {
            $total_rows['tracking_info'] = array(
               'label' => __( 'Tracking Details:', 'bizapp-woocommerce' ),
               'value' => $tracking,
            );
        }

        return $total_rows;

    }

}
new Bizapp_Woocommerce_Order();
