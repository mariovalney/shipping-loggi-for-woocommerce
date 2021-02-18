<?php
/**
 * SLFW_Has_Logger
 * Trait to help classes with logger
 *
 * @package         Shipping_Loggi_For_WooCommerce
 * @subpackage      SLFW_Module_Woocommerce
 * @since           1.0.0
 *
 */

// If this file is called directly, call the cops.
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

if ( ! trait_exists( 'SLFW_Has_Logger' ) ) {

    trait SLFW_Has_Logger {

        /**
         * Logger class
         *
         * @var WC_Logger
         */
        protected $logger;

        /**
         * Register a log
         *
         * @return SLFW_Loggi_Api
         *
         * @SuppressWarnings(PHPMD.DevelopmentCodeFragment)
         */
        protected function log( $message, $data = array() ) {
            if ( empty( $this->logger ) ) {
                return;
            }

            $log_message = $message;
            if ( ! empty( $data ) ) {
                $log_message .= "\n" . print_r( $data, true );
            }

            /**
             * Filter: 'slfw_log_message'
             *
             * You can change the rate args before add to cart.
             * Return empty to remove.
             *
             * @param string $log_message
             * @param string $message
             * @param array $data
             *
             * @var string
             */
            $log_message = apply_filters( 'slfw_log_message', $log_message, $message, $data );

            $this->logger->add( SLFW_Shipping_Method::ID, $log_message );
        }

    }

}
