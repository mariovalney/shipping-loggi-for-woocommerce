<?php
/**
 * SLFW_Loggi_Api
 * API Client to Loggi GraphQL API
 *
 * @package         Shipping_Loggi_For_WooCommerce
 * @subpackage      SLFW_Module_Woocommerce
 * @since           1.0.0
 *
 */

// If this file is called directly, call the cops.
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

if ( ! class_exists( 'SLFW_Loggi_Api' ) ) {

    class SLFW_Loggi_Api {

        /**
         * Shipping Method
         * @var SLFW_Shipping_Method
         */
        private $method;

        /**
         * The constructor
         *
         * @param SLFW_Shipping_Method $method
         */
        public function __construct( SLFW_Shipping_Method $method ) {
            $this->method = $method;
        }

        /**
         * Get API url for requests
         *
         * @return string
         */
        private function get_url() {
            if ( $this->method->environment === 'production' ) {
                return 'https://www.loggi.com/graphql';
            }

            return 'https://staging.loggi.com/graphql';
        }

    }

}
