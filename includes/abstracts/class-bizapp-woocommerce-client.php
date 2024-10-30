<?php
if ( !defined( 'ABSPATH' ) ) exit;

abstract class Bizapp_Woocommerce_Client {

    const API_URL = 'https://woo.bizapp.my/';

    protected $secret_key;

    private $logger;

    // Send GET request to Bizapp
    protected function get( $route, $params = array() ) {
        return $this->request( $route, $params, 'GET' );
    }

    // Send POST request to Bizapp
    protected function post( $route, $params = array() ) {
        return $this->request( $route, $params );
    }

    // Send request to Bizapp
    protected function request( $route, $params = array(), $method = 'POST' ) {

        // Get API URL
        $url = self::API_URL . $route . '/' . $this->secret_key;
        $this->log( 'URL: ' . $url );

        // Get request headers (for GET request)
        if ( $method == 'GET' ) {
            $args['headers'] = array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            );

            $this->log( 'Headers: ' . wp_json_encode( $args['headers'] ) );
        }

        // Request parameters
        if ( $params ) {
            $args['body'] = $params;
            $this->log( 'Body: ' . wp_json_encode( $params ) );
        }

        // Set timeout to 30 seconds
        $args['timeout'] = 30;

        // Send request based on specified method
        switch ( $method ) {
            case 'GET':
                $response = wp_remote_get( $url, $args );
                break;

            case 'POST':
                $response = wp_remote_post( $url, $args );
                break;

            default:
                $args['method'] = $method;
                $response = wp_remote_request( $url, $args );
        }

        if ( is_wp_error( $response ) ) {
            // Follow Bizapp error response
            return array(
                $response->get_error_code(),
                array(
                    array(
                        'STATUS' => $response->get_error_message(),
                    ),
                ),
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        $this->log( 'Response code: ' . $code );
        $this->log( 'Response: ' . wp_json_encode( $body ) );

        // if($route == 'v2/submitorder'){
        // }

        // if($body['status'] == 'fail'){
        //     $message_fail = $body['error_message'];
        //     $this->send_log_to_telegram("BIZAPP API ERROR \n\nPath: {$route} \n\nError: {$message_fail}");
        //     $this->send_log_to_telegram("PARAMS : " . wp_json_encode($params));
        // }
        
        return array( $code, $body );

    }


    // Errors logging
    private function log( $message ) {
        if ( !$this->logger ) {
            $this->logger = new Bizapp_Woocommerce_Logger();
        }

        if ( $this->logger ) {
            $this->logger->log( $message );
        }

    }
}
