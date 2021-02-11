<?php

/**
 * SLFW_Module_Woocommerce
 * Class responsible to manage all WooCommerce stuff
 *
 * Depends: dependence
 *
 * @package         Shipping_Loggi_For_WooCommerce
 * @subpackage      SLFW_Module_Woocommerce
 * @since           1.0.0
 *
 */

// If this file is called directly, call the cops.
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

if ( ! class_exists( 'SLFW_Module_Woocommerce' ) ) {

    class SLFW_Module_Woocommerce {

        /**
         * Run
         *
         * @since    1.0.0
         */
        public function run() {
            $module = $this->core->get_module( 'dependence' );

            // Checking Dependences
            $module->add_dependence( 'woocommerce/woocommerce.php', 'WooCommerce', 'woocommerce' );

            if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '4.5', '<' ) ) {
                $notice = __( 'Please update <strong>WooCommerce</strong>. The minimum supported version for <strong>Shipping Loggi for WooCommerce</strong> is 4.5.', 'shipping-loggi-for-woocommerce' );
                $module->add_dependence_notice( $notice );
            }

            $this->includes = array(
            );
        }

        /**
         * Define hooks
         *
         * @since    1.0.0
         * @param    Shipping_Loggi_For_WooCommerce      $core   The Core object
         */
        public function define_hooks() {
        }

    }

}

