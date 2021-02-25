<?php
/**
 * SLFW_Has_Time
 * Trait to help classes with time
 *
 * @package         Shipping_Loggi_For_WooCommerce
 * @subpackage      SLFW_Module_Woocommerce
 * @since           1.0.0
 *
 */

// If this file is called directly, call the cops.
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

if ( ! trait_exists( 'SLFW_Has_Time' ) ) {

    trait SLFW_Has_Time {

        /**
         * Parse a hour string (H:i) to a very cool object
         *
         * @param  string $time
         * @param  string $default
         * @return object
         *
         * @SuppressWarnings(PHPMD.MissingImport)
         */
        public function parse_time( $time, $default = '00:00' ) {
            if ( empty( $time ) ) {
                $time = $default;
            }

            $time = explode( ':', $time, 2 );

            $hour = (int) ( $time[0] ?? '' );
            $hour = str_pad( $hour, 2, '0', STR_PAD_LEFT );

            $minute = (int) ( $time[1] ?? '' );
            $minute = str_pad( $minute, 2, '0', STR_PAD_LEFT );

            $time = new DateTimeImmutable( $hour . ':' . $minute, wp_timezone() );

            return (object) array(
                'h'        => $hour,
                'i'        => $minute,
                'time'     => $time->format( 'U' ),
                'formated' => $time->format( 'H:i' ),
            );
        }

    }

}
