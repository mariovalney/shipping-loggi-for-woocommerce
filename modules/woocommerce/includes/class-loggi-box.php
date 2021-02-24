<?php
/**
 * SLFW_Loggi_Box
 * Create a Loggi Product Box
 *
 * @package         Shipping_Loggi_For_WooCommerce
 * @subpackage      SLFW_Module_Woocommerce
 * @since           1.0.0
 *
 */

// If this file is called directly, call the cops.
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

if ( ! class_exists( 'SLFW_Loggi_Box' ) ) {

    class SLFW_Loggi_Box {

        use SLFW_Has_Data;

        /**
         * Data for this box
         *
         * @var array
         */
        protected $data = array(
            'height' => 0,
            'width'  => 0,
            'length' => 0,
            'weight' => 0,
        );

        /**
         * Items inside box
         *
         * @var array
         */
        protected $items = array();

        /**
         * The constructor
         */
        public function __construct( $data ) {
            $this->set_data( $data );
        }

        /**
         * Add item to items array and set quantity
         *
         * @param string
         */
        public function set_item( $item, $qty = 1 ) {
            if ( empty( $qty ) ) {
                unset( $this->items[ $item ] );
                return;
            }

            $this->items[ $item ] = $qty;
        }

    }

}
