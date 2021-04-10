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
         * The WooCommerce package deliverable items
         * @var array
         */
        protected $items = array();

        /**
         * The WooCommerce package destination
         * @var array
         */
        protected $destination = array();

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
         *
         * @SuppressWarnings(PHPMD.MissingImport)
         */
        public function __construct( $package, $shipping_classes = array(), $merge = false ) {
            $package = (array) $package;

            $this->destination = (array) $package['destination'];

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

                $dimensions = $this->get_valid_product_dimensions( $product );
                if ( empty( $dimensions ) ) {
                    $this->can_be_delivered = false;
                    continue;
                }

                // Add to item list
                $this->items[ $item['key'] ] = $item;

                // Boxes
                $boxes = array();
                for ( $index = 0; $index < $qty; $index++ ) {
                    $box = new SLFW_Loggi_Box( $dimensions );
                    $box->add_item( $item['key'] );

                    $boxes[] = $box;
                }

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
         * Return a unique identifier to this package
         *
         * @return string
         */
        public function unique_identifier() {
            $hashes = array();

            foreach ( $this->items as $key => $value ) {
                $hashes[] = 'item-' . $key . '-' . $value['data_hash'] . '-' . (int) $value['quantity'];
            }

            foreach ( $this->destination as $key => $value ) {
                $hashes[] = 'destination-' . $key . '-' . md5( (string) $value ?? '' );
            }

            natsort( $hashes );

            $hashes = implode( '---', array_values( $hashes ) );

            return md5( $hashes );
        }

        /**
         * Get info about weigh and items from package
         *
         * @param  array $boxes
         * @return array
         */
        public function get_boxes_info( $boxes = array() ) {
            $info = array(
                'weight' => 0,
                'items'  => array(),
            );

            if ( empty( $boxes ) ) {
                $boxes = $this->boxes;
            }

            foreach ( $boxes as $box ) {
                $info['weight'] += $box->weight;

                foreach ( $box->get_items() as $key => $qty ) {
                    $info['items'][ $key ] = ( $info['items'][ $key ] ?? 0 ) + $qty;
                }
            }

            return $info;
        }

        /**
         * Try to merge boxes
         *
         * @return void
         */
        public function try_merge_boxes() {
            $remaining = $this->boxes;
            if ( empty( $remaining ) ) {
                return;
            }

            // Let's check eveything is OK at end
            $integrity = $this->get_boxes_info( $remaining );

            $boxes = array();

            // Get the first box
            $mergeable = array_shift( $remaining );

            // While we have boxes, try to merge
            while ( ! empty( $remaining ) || ! empty( $mergeable ) ) {
                foreach ( (array) $remaining as $key => $box ) {
                    if ( ! $mergeable->merge_box( $box ) ) {
                        continue;
                    }

                    // Remove from list if merged
                    unset( $remaining[ $key ] );
                }

                // After all, add the mergeable to list and get the next
                $boxes[] = $mergeable;
                $mergeable = array_shift( $remaining );
            }

            // Info about merged boxes
            $challenge = $this->get_boxes_info( $boxes );

            if ( $integrity['weight'] !== $challenge['weight'] ) {
                return;
            }

            if ( ! empty( array_diff_assoc( $integrity['items'], $challenge['items'] ) ) ) {
                return;
            }

            $this->boxes = $boxes;
        }

        /**
         * Create a set of valid dimensions to create a box for a product
         *
         * @param  WC_Product $product
         * @return array|null
         *
         * @SuppressWarnings(PHPMD.MissingImport)
         */
        private function get_valid_product_dimensions( $product ) {
            $product = array(
                'height' => ceil( wc_get_dimension( (float) $product->get_height(), 'cm' ) ),
                'width'  => ceil( wc_get_dimension( (float) $product->get_width(), 'cm' ) ),
                'length' => ceil( wc_get_dimension( (float) $product->get_length(), 'cm' ) ),
                'weight' => ceil( wc_get_weight( (float) $product->get_weight(), 'g' ) ),
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
                $data = array(
                    'height' => $product[ $combination[0] ],
                    'width'  => $product[ $combination[1] ],
                    'length' => $product[ $combination[2] ],
                    'weight' => $product['weight'],
                );

                // Let's create a box just to validate
                $box = new SLFW_Loggi_Box( $data );
                if ( $box->has_valid_dimensions() ) {
                    return $box->get_data();
                }
            }

            return null;
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
