<?php
/**
 * SLFW_Loggi_Package
 * Create a Loggi Package
 *
 * @package         Shipping_Loggi_For_WooCommerce
 * @subpackage      SLFW_Module_Woocommerce
 * @since           1.0.0
 *
 */

// If this file is called directly, call the cops.
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

if ( ! class_exists( 'SLFW_Loggi_Package' ) ) {

    class SLFW_Loggi_Package {

        /**
         * The max height for a package in cm
         *
         * @var int
         */
        const MAX_HEIGHT = 55;

        /**
         * The max width for a package in cm
         *
         * @var int
         */
        const MAX_WIDTH = 55;

        /**
         * The max length for a package in cm
         *
         * @var int
         */
        const MAX_LENGTH = 55;

        /**
         * The max weight for a package in g
         *
         * @var int
         */
        const MAX_WEIGHT = 20000;

        /**
         * The boxes to be delivered
         * @var array { SLFW_Loggi_Box }
         */
        protected $boxes = array();

        /**
         * Package can be delivered
         * @var boolean
         */
        protected $can_be_delivered = true;

        /**
         * The constructor
         *
         * @param string $environment
         */
        public function __construct( $package, $shipping_classes = array(), $merge = false ) {
            $package = (array) $package;

            foreach ( $package['contents'] as $item ) {
                $product = $item['data'];
                $qty = (int) $item['quantity'];

                if ( empty( $qty ) || ! $product->needs_shipping() ) {
                    continue;
                }

                // Check all shipping classes are allowed
                if ( ! empty( $shipping_classes ) && ! $this->product_has_shipping_class( $product, $shipping_classes ) ) {
                    $this->can_be_delivered = false;
                    continue;
                }

                $box = $this->create_box_for_product( $product );
                if ( empty( $box ) ) {
                    $this->can_be_delivered = false;
                    continue;
                }

                $box->set_item( $item['key'] );

                $boxes = $this->merge_product_boxes( $box, $qty );

                $this->boxes = array_merge( $this->boxes, $boxes );
            }

            if ( $merge ) {
                $this->try_merge_boxes();
            }
        }

        /**
         * Return true if package can be delivered
         *
         * @return boolean
         */
        public function can_be_delivered() {
            return $this->can_be_delivered && ! empty( $this->boxes );
        }

        /**
         * Get all boxes to be delivered
         *
         * @return array
         */
        public function get_boxes() {
            return $this->boxes;
        }

        /**
         * Try to merge boxes
         *
         * @return void
         */
        public function try_merge_boxes() {}

        /**
         * Create a box from product
         *
         * @param  WC_Product $product
         * @return SLFW_Loggi_Box|null
         *
         * @SuppressWarnings(PHPMD.MissingImport)
         */
        private function create_box_for_product( $product ) {
            $product = array(
                'height' => ceil( wc_get_dimension( (float) $product->get_height(), 'cm' ) ),
                'width'  => ceil( wc_get_dimension( (float) $product->get_width(), 'cm' ) ),
                'length' => ceil( wc_get_dimension( (float) $product->get_length(), 'cm' ) ),
                'weight' => ceil( wc_get_weight( (float) $product->get_weight(), 'g', 'kg' ) ),
            );

            // Let's check every possibility
            $box_combinations = array(
                array( 'height', 'width', 'length' ),
                array( 'height', 'length', 'width' ),
                array( 'length', 'width', 'height' ),
                array( 'length', 'height', 'width' ),
                array( 'width', 'height', 'length' ),
                array( 'width', 'length', 'height' ),
            );

            foreach ( $box_combinations as $combination ) {
                $box = array(
                    'height' => $product[ $combination[0] ],
                    'width'  => $product[ $combination[1] ],
                    'length' => $product[ $combination[2] ],
                    'weight' => $product['weight'],
                );

                // Is valid
                if ( $this->box_has_valid_volume( $box ) ) {
                    return new SLFW_Loggi_Box( $box );
                }
            }

            return null;
        }

        /**
         * Merge boxes from the same products
         *
         * @param  array $box
         * @return array
         */
        private function merge_product_boxes( $box, $qty ) {
            $qty = (int) $qty;

            $boxes = array();
            for ( $index = 0; $index < $qty; $index++ ) {
                $boxes[] = $box;
            }

            return $boxes;
        }

        /**
         * Check a box is valid to delivery
         *
         * @param  array $box
         * @return boolean
         */
        private function box_has_valid_volume( $box ) {
            if ( $box['height'] > self::MAX_HEIGHT || $box['width'] > self::MAX_WIDTH || $box['length'] > self::MAX_LENGTH ) {
                return false;
            }

            if ( $box['weight'] > self::MAX_WEIGHT ) {
                return false;
            }

            return true;
        }

        /**
         * Check a product match with shipping classes IDs
         *
         * @param  WC_Product $product
         * @param  array $shipping_classes
         * @return boolean
         */
        private function product_has_shipping_class( $product, $shipping_classes ) {
            if ( in_array( '-1', $shipping_classes, true ) && empty( $product->get_shipping_class_id() ) ) {
                return true;
            }

            return in_array( (string) $product->get_shipping_class_id(), $shipping_classes, true );
        }

    }

}
