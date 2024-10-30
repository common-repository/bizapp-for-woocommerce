<?php
if ( !defined( 'ABSPATH' ) ) exit;

class Bizapp_Woocommerce {

    // Load the required dependencies for this plugin
    public function __construct() {

        // Libraries
        require_once( BIZAPP_WOOCOMMERCE_PATH . 'libraries/codestar-framework/codestar-framework.php' );
        require_once( BIZAPP_WOOCOMMERCE_PATH . 'libraries/meta-box/meta-box.php' );

        // Functions
        require_once( BIZAPP_WOOCOMMERCE_PATH . 'includes/functions.php' );

        // API
        require_once( BIZAPP_WOOCOMMERCE_PATH . 'includes/class-bizapp-woocommerce-logger.php' );
        require_once( BIZAPP_WOOCOMMERCE_PATH . 'includes/abstracts/class-bizapp-woocommerce-client.php' );
        require_once( BIZAPP_WOOCOMMERCE_PATH . 'includes/class-bizapp-woocommerce-api.php' );

        require_once( BIZAPP_WOOCOMMERCE_PATH . 'admin/class-bizapp-woocommerce-admin.php' );

        // Settings page
        require_once( BIZAPP_WOOCOMMERCE_PATH . 'admin/traits/trait-bizapp-woocommerce-products-list.php' );
        require_once( BIZAPP_WOOCOMMERCE_PATH . 'admin/class-bizapp-woocommerce-settings.php' );

        // Product data - agent commission
        require_once( BIZAPP_WOOCOMMERCE_PATH . 'admin/class-bizapp-woocommerce-product-data.php' );

        // Order metabox - tracking and courier name
        require_once( BIZAPP_WOOCOMMERCE_PATH . 'admin/interfaces/class-bizapp-woocommerce-metabox-interface.php' );
        require_once( BIZAPP_WOOCOMMERCE_PATH . 'admin/class-bizapp-woocommerce-order-metabox.php' );

        // Product sync
        require_once( BIZAPP_WOOCOMMERCE_PATH . 'admin/class-bizapp-woocommerce-sync.php' );

        // Push order
        require_once( BIZAPP_WOOCOMMERCE_PATH . 'admin/class-bizapp-woocommerce-order.php' );

        // Product and order webhook
        require_once( BIZAPP_WOOCOMMERCE_PATH . 'public/webhooks/interfaces/class-bizapp-woocommerce-webhook-interface.php' );
        require_once( BIZAPP_WOOCOMMERCE_PATH . 'public/webhooks/class-bizapp-woocommerce-webhook-base.php' );
        require_once( BIZAPP_WOOCOMMERCE_PATH . 'public/webhooks/class-bizapp-woocommerce-webhook-product.php' );
        require_once( BIZAPP_WOOCOMMERCE_PATH . 'public/webhooks/class-bizapp-woocommerce-webhook-order.php' );

    }

}
new Bizapp_Woocommerce();
