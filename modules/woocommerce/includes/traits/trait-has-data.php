<?php
/**
 * SLFW_Has_Data
 * Trait to help classes with data
 *
 * @package         Shipping_Loggi_For_WooCommerce
 * @subpackage      SLFW_Module_Woocommerce
 * @since           1.0.0
 *
 */

// If this file is called directly, call the cops.
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

if ( ! trait_exists( 'SLFW_Has_Data' ) ) {

    trait SLFW_Has_Data {

        /**
         * Parse a array of data to $data itself
         *
         * @param  array $data
         * @return void
         */
        public function set_data( $data = array() ) {
            foreach ( $data as $key => $value ) {
                if ( ! isset( $this->data[ $key ] ) ) {
                    continue;
                }

                $this->data[ $key ] = $value;
            }
        }

        /**
         * Return the data array
         *
         * @return array
         */
        public function get_data() {
            return $this->data;
        }

        /**
         * Magic methods to support direct access to props.
         *
         * @param string $key
         *
         * @return bool
         */
        public function __isset( $key ) {
            return isset( $this->data[ $key ] );
        }

        /**
         * Magic methods to support direct access to props.
         *
         * @param string $key
         *
         * @return mixed|null
         */
        public function __get( $key ) {
            return $this->data[ $key ] ?? null;
        }

        /**
         * Magic methods to support direct access to props.
         *
         * @param string $key
         * @param mixed  $value Value.
         *
         * @return void
         */
        public function __set( $key, $value ) {
            $this->data[ $key ] = $value;
        }

    }

}
