<?php

// Get setting value by key
function bizapp_woocommerce_get_setting( $key ) {
    return !empty( get_option( 'bizapp' )[ $key ] ) ? get_option( 'bizapp' )[ $key ] : null;
}
