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
         * Add item to box
         *
         * If empty will remove and if item is already
         * placed we'll sum the quantities
         *
         * @param string
         */
        public function add_item( $item, $qty = 1 ) {
            if ( empty( $qty ) ) {
                unset( $this->items[ $item ] );
                return;
            }

            $this->items[ $item ] = ( $this->items[ $item ] ?? 0 ) + $qty;
        }

        /**
         * Get box items
         *
         * @param string
         */
        public function get_items() {
            return $this->items;
        }

        /**
         * Try to join two boxes
         *
         * @param SLFW_Loggi_Box $box
         *
         * @return SLFW_Loggi_Box
         *
         * @SuppressWarnings(PHPMD.MissingImport)
         */
        public function merge_box( $box ) {
            if ( ! $this->has_valid_dimensions() ) {
                return false;
            }

            if ( ! $box->has_valid_dimensions() ) {
                return false;
            }

            if ( ( $this->weight + $box->weight ) > self::MAX_WEIGHT ) {
                return false;
            }

            /**
             * OK... It's a bit cofusing here
             *
             * We'll fix the Box A and place Box B by side.
             * After that we got the size of new volume: the front side is summed and we consider the max size of another sides.
             * This way: (H1 + H2) x (W1 > W2) x (L1 > L2)
             *
             * If not valid, we rotate the Box B changing the not summed sides.
             * This way: (H1 + H2) x (W1 > L2) x (L1 > W2)
             *
             * If not valid, we change the side of the Box B that is in front with Box A. Never touch Box A.
             * This way: (H1 + W2) x (W1 > H2) x (L1 > L2)
             * And: (H1 + W2) x (W1 > L2) x (L1 > H2)
             *
             * We tested all possibilities of the Box B to be at side of this side of Box A (6 possibilities).
             * Now we'll change the Box A side that is in front of us and repeat all movements of Box B.
             * If any possibility is a valid volume we give up.
             *
             * Note: the sum of weight is checked before to avoid unecessary calculation.
             */

            $dimensions = array( 'height', 'width', 'length' );

            foreach ( $dimensions as $base_dimension ) {
                foreach ( $dimensions as $sum_dimension ) {
                    $rotative_dimentions = array_values( array_diff( $dimensions, array( $base_dimension ) ) );

                    $rotative_check = array_values( array_diff( $dimensions, array( $sum_dimension ) ) );
                    $rotative_check = array(
                        $rotative_check,
                        array_reverse( $rotative_check ),
                    );

                    foreach ( $rotative_check as $check ) {
                        $data = array(
                            'height' => $this->{$base_dimension} + $box->{$sum_dimension},
                            'width'  => max( $this->{$rotative_dimentions[0]}, $box->{$check[0]} ),
                            'length' => max( $this->{$rotative_dimentions[1]}, $box->{$check[1]} ),
                            'weight' => $this->weight + $box->weight,
                        );

                        $merged = new SLFW_Loggi_Box( $data );
                        if ( ! $merged->has_valid_dimensions() ) {
                            continue;
                        }

                        // We found a valid box
                        $this->height = $merged->height;
                        $this->width = $merged->width;
                        $this->length = $merged->length;
                        $this->weight = $merged->weight;

                        foreach ( $box->get_items() as $item => $qty ) {
                            $this->add_item( $item, $qty );
                        }

                        return true;
                    }
                }
            }

            return false;
        }

        /**
         * Check the box has a valid volume
         *
         * @return boolean
         */
        public function has_valid_dimensions() {
            if ( $this->height > self::MAX_HEIGHT || $this->width > self::MAX_WIDTH || $this->length > self::MAX_LENGTH ) {
                return false;
            }

            if ( $this->weight > self::MAX_WEIGHT ) {
                return false;
            }

            if ( empty( $this->height ) || empty( $this->width ) || empty( $this->length ) || empty( $this->weight ) ) {
                return false;
            }

            return true;
        }

    }

}
