<?php
if ( !defined( 'ABSPATH' ) ) exit;

class Bizapp_Woocommerce_Admin {

    // Register hooks
    public function __construct() {

        add_action( 'plugin_action_links_' . BIZAPP_WOOCOMMERCE_BASENAME, array( $this, 'register_settings_link' ) );
        add_action( 'admin_notices', array( $this, 'woocommerce_notice' ) );

    }

    // Register plugin settings link
    public function register_settings_link( $links ) {

        $url = admin_url( 'admin.php?page=bizapp' );
        $label = esc_html__( 'Settings', 'bizapp-woocommerce' );

        $settings_link = sprintf( '<a href="%s">%s</a>', $url, $label );
        array_unshift( $links, $settings_link );

        return $links;

    }

    // Show notice if WooCommerce not installed
    public function woocommerce_notice() {

        if ( !$this->is_woocommerce_activated() ) {
            $plugin = esc_html__( 'Bizapp for WooCommerce', 'bizapp-woocommerce' );
            $message = esc_html__( 'WooCommerce needs to be installed and activated.', 'bizapp-woocommerce' );

            printf( '<div class="notice notice-error"><p><strong>%1$s:</strong> %2$s</p></div>', $plugin, $message );
        }

    }

    // Check if WooCommerce is installed and activated
    private function is_woocommerce_activated() {
        return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
    }

}
new Bizapp_Woocommerce_Admin();
