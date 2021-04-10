<?php
/**
 * SLFW_Has_Session
 * Trait to help classes with WC session
 *
 * @package         Shipping_Loggi_For_WooCommerce
 * @subpackage      SLFW_Module_Woocommerce
 * @since           1.0.0
 *
 */

// If this file is called directly, call the cops.
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

if ( ! trait_exists( 'SLFW_Has_Session' ) ) {

    trait SLFW_Has_Session {

        /**
         * Save data to WC Session.
         *
         * @param  string  $key     WooCommerce session key (we'll make it unique for current class).
         * @param  mixed   $value   Value to be stored on data.
         * @param  integer $seconds Seconds this data will be valid. Default is 300 (5 minutes).
         *
         * @return mixed
         */
        public function to_session( $key, $value, $seconds = 300 ) {
            $key = mb_strtolower( get_class( $this ) ) . '_' . $key;
            $value = array(
                'expires' => time() + (int) $seconds,
                'data'    => $value,
            );

            WC()->session->set( $key, $value );
        }

        /**
         * Retrieve valid data from WC Session.
         *
         * @param  string  $key     WooCommerce session key.
         * @param  mixed   $default Default value if data is not on session or invalid.
         *
         * @return mixed
         */
        public function from_session( $key, $default = null ) {
            $key = mb_strtolower( get_class( $this ) ) . '_' . $key;
            $value = (array) WC()->session->get( $key, array() );

            if ( empty( $value ) || empty( $value['expires'] ) || $value['expires'] < time() ) {
                return $default;
            }

            return $value['data'] ?? $default;
        }

        /**
         * Expire a data from WC Session.
         *
         * @param  string  $key WooCommerce session key.
         *
         * @return mixed
         */
        public function remove_session( $key ) {
            $this->to_session( $key, null, -1 );
        }

    }

}
