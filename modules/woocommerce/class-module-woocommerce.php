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
                'traits/trait-has-data',
                'traits/trait-has-logger',
                'traits/trait-has-session',
                'traits/trait-has-time',
                'class-loggi-api',
                'class-loggi-box',
                'class-loggi-package',
                'class-shipping-method',
            );
        }

        /**
         * Define hooks
         *
         * @since    1.0.0
         * @param    Shipping_Loggi_For_WooCommerce      $core   The Core object
         */
        public function define_hooks() {
            $this->core->add_filter( 'woocommerce_shipping_methods', array( $this, 'woocommerce_shipping_methods' ), 99 );

            $this->core->add_action( 'woocommerce_after_shipping_rate', array( $this, 'woocommerce_after_shipping_rate' ), 99 );
            $this->core->add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 99 );

            $this->core->add_action( 'wp_ajax_slfw_request_api_key', array( $this, 'wp_ajax_slfw_request_api_key' ), 99 );
            $this->core->add_action( 'wp_ajax_slfw_all_shops_as_options', array( $this, 'wp_ajax_slfw_all_shops_as_options' ), 99 );
        }

        /**
         * Filter: 'woocommerce_shipping_methods'
         * Add shipping method to WooCommerce.
         *
         * @param array $methods
         * @return void
         */
        public function woocommerce_shipping_methods( $methods ) {
            if ( class_exists( 'SLFW_Shipping_Method' ) ) {
                $methods = array_merge( array( SLFW_Shipping_Method::ID => 'SLFW_Shipping_Method' ), $methods );
            }

            return $methods;
        }

        /**
         * Action: 'wp_ajax_slfw_request_api_key'
         * Request the API Key using password data.
         *
         * @return void
         *
         * @SuppressWarnings(PHPMD.MissingImport)
         */
        public function wp_ajax_slfw_request_api_key() {
            check_ajax_referer( 'slfw-request-api-key', 'nonce' );

            $email = sanitize_text_field( $_POST['email'] ?? '' );
            $password = sanitize_text_field( $_POST['password'] ?? '' );
            $environment = sanitize_text_field( $_POST['environment'] ?? '' );

            if ( empty( $email ) ) {
                wp_send_json_error( __( 'You should insert your Loggi e-mail in "E-mail" field.', 'shipping-loggi-for-woocommerce' ) );
            }

            if ( empty( $password ) ) {
                wp_send_json_error( __( 'You should insert your Loggi password in "API Key" field.', 'shipping-loggi-for-woocommerce' ) );
            }

            if ( empty( $environment ) ) {
                wp_send_json_error();
            }

            $api = new SLFW_Loggi_Api( $environment );
            $api_key = $api->retrieve_api_key( $email, $password );

            if ( empty( $api_key ) ) {
                wp_send_json_error( __( 'Invalid e-mail or password. Check your credentials and try again.', 'shipping-loggi-for-woocommerce' ) );
            }

            wp_send_json_success( $api_key );
        }

        /**
         * Action: 'wp_ajax_slfw_all_shops_as_options'
         * List all available shops to create a select.
         *
         * @return void
         *
         * @SuppressWarnings(PHPMD.MissingImport)
         */
        public function wp_ajax_slfw_all_shops_as_options() {
            check_ajax_referer( 'slfw-all-shops-as-options', 'nonce' );

            $api_email = sanitize_text_field( $_POST['api_email'] ?? '' );
            $api_key = sanitize_text_field( $_POST['api_key'] ?? '' );
            $environment = sanitize_text_field( $_POST['environment'] ?? '' );

            if ( empty( $api_email ) || empty( $api_key ) || empty( $environment ) ) {
                wp_send_json_error();
            }

            $shops = array();

            $api = new SLFW_Loggi_Api( $environment, $api_email, $api_key );
            $all_shops = $api->retrieve_all_shops();

            foreach ( $all_shops as $id => $shop ) {
                $shops[] = array(
                    'value' => $id,
                    'label' => sprintf( '(%s) %s', $id, ( $shop['name'] ?? '' ) ),
                );
            }

            if ( empty( $shops ) ) {
                wp_send_json_error();
            }

            wp_send_json_success( $shops );
        }

        /**
         * Action: 'woocommerce_after_shipping_rate'
         * Show a delivery estimation for Loggi.
         *
         * @param WC_Shipping_Rate $rate
         *
         * @return void
         */
        public function woocommerce_after_shipping_rate( $rate ) {
            $eta = $rate->get_meta_data();
            $eta = $eta['loggi_eta'] ?? 0;
            $eta = ceil( (int) $eta / HOUR_IN_SECONDS );

            if ( empty( $eta ) ) {
                return;
            }

            // translators: %d: hours to delivery
            $text = sprintf( _n( 'Delivery within %d hour', 'Delivery within %d hours', $eta, 'shipping-loggi-for-woocommerce' ), $eta );

            /**
             * Filter: 'slfw_rate_delivery_time_text'
             *
             * You can change the delivery estimation text.
             * Return empty to not show.
             *
             * @param text $estimation_text  Default estimation text
             * @param int $eta               Estimation time in hours
             * @param WC_Shipping_Rate $rate The shipping rate
             *
             * @var string
             */
            $text = apply_filters( 'slfw_rate_delivery_time_text', $text, (int) $eta, $rate );

            if ( empty( $text ) ) {
                return;
            }

            echo '<p><small>' . esc_html( $text ) . '</small></p>';
        }

        /**
         * Action: 'admin_enqueue_scripts'
         * Enqueue scripts or styles for gateway settings page.
         *
         * We do not validate page into actions because WooCommerce says:
         * "Gateways are only loaded when needed, such as during checkout and on the settings page in admin"
         *
         * @link https://docs.woocommerce.com/document/payment-gateway-api/#section-8
         *
         * @return void
         */
        public function admin_enqueue_scripts( $hook ) {
            if ( $hook !== 'woocommerce_page_wc-settings' || ( $_GET['page'] ?? '' ) !== 'wc-settings' || ( $_GET['tab'] ?? '' ) !== 'shipping' ) {
                return;
            }

            $version = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? uniqid() : SLFW_VERSION;

            $file_url  = SLFW_PLUGIN_URL . '/modules/woocommerce/assets/build/js/loggi-shipping.min.js';
            wp_enqueue_script( 'slfw-loggi-shipping-script', $file_url, array( 'jquery', 'wp-i18n' ), $version, true );
            wp_set_script_translations( 'slfw-loggi-shipping-script', 'shipping-loggi-for-woocommerce' );

            $file_url  = SLFW_PLUGIN_URL . '/modules/woocommerce/assets/build/css/loggi-shipping.min.css';
            wp_enqueue_style( 'slfw-loggi-shipping-style', $file_url, array(), $version );
        }

    }

}

