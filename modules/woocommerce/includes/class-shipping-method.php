<?php
/**
 * SLFW_Shipping_Method
 * Shipping class to WooCommerce
 *
 * @package         Shipping_Loggi_For_WooCommerce
 * @subpackage      SLFW_Module_Woocommerce
 * @since           1.0.0
 *
 */

// If this file is called directly, call the cops.
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

if ( ! class_exists( 'SLFW_Shipping_Method' ) && class_exists( 'WC_Shipping_Method' ) ) {

    class SLFW_Shipping_Method extends WC_Shipping_Method {

        const ID = 'loggi-shipping';

        /**
         * The constructor
         *
         * @SuppressWarnings(PHPMD.MissingImport)
         */
        public function __construct( $instance_id = 0 ) {
            $this->instance_id = absint( $instance_id );

            // Main Fields
            $this->id                 = self::ID;
            $this->method_title       = __( 'Loggi', 'shipping-loggi-for-woocommerce' );
            $this->method_description = __( 'Shipping via motorcycle courier with Loggi.', 'shipping-loggi-for-woocommerce' );
            $this->tax_status         = false;

            // Register Support
            $this->supports = array(
                'shipping-zones',
                'instance-settings',
                'instance-settings-modal',
            );

            // Load the form fields.
            $this->init_form_fields();

            // Options
            $this->enabled     = $this->get_option( 'enabled' );
            $this->title       = $this->get_option( 'title', 'Loggi' );
            $this->environment = $this->get_option( 'environment' );
            $this->debug       = $this->get_option( 'debug' );

            // API
            $this->api = new SLFW_Loggi_Api( $this );

            // Hooks
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

            // Save admin options
            add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        /**
         * Initialise Gateway Settings Form Fields.
         *
         * @return void
         *
         * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
         */
        public function init_form_fields() {
            $debug_description = sprintf(
                // translators: link to logs page
                __( 'You can check logs in %s.', 'shipping-loggi-for-woocommerce' ),
                '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '" target="_blank">' . __( 'System Status &gt; Logs', WCB_TEXTDOMAIN ) . '</a>'
            );

            $this->instance_form_fields = array(
                'title'       => array(
                    'type'              => 'text',
                    'title'             => __( 'Title', 'shipping-loggi-for-woocommerce' ),
                    'description'       => __( 'Shipping method title for customer.', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => true,
                    'default'           => __( 'Loggi', 'shipping-loggi-for-woocommerce' ),
                    'custom_attributes' => array( 'required' => 'required' ),
                ),
                'api_section' => array(
                    'type'  => 'title',
                    'title' => __( 'API Settings', 'shipping-loggi-for-woocommerce' ),
                ),
                'environment' => array(
                    'type'              => 'select',
                    'title'             => __( 'Environment', 'shipping-loggi-for-woocommerce' ),
                    'description'       => __( 'Loggi API environment. Use Staging for tests and Production for your running shop.', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => true,
                    'default'           => 'staging',
                    'options'           => array(
                        'staging'    => __( 'Staging', 'shipping-loggi-for-woocommerce' ),
                        'production' => __( 'Production', 'shipping-loggi-for-woocommerce' ),
                    ),
                    'custom_attributes' => array( 'required' => 'required' ),
                ),
                'advanced_section' => array(
                    'type'  => 'title',
                    'title' => __( 'Advanced Settings', 'shipping-loggi-for-woocommerce' ),
                ),
                'debug'       => array(
                    'type'        => 'checkbox',
                    'title'       => __( 'Debug Log', 'shipping-loggi-for-woocommerce' ),
                    'label'       => __( 'Enable logging', 'shipping-loggi-for-woocommerce' ),
                    'description' => $debug_description,
                    'default'     => 'no',
                ),
            );
        }

        /**
         * Calculate shipping rates
         *
         * @param mixed $package
         * @return void
         */
        public function calculate_shipping( $package = array() ) {
            $rate = array(
                'label'     => $this->title,
                'cost'      => 20.00,
                'taxes'     => false,
                'meta_data' => array(),
            );

            // Register the rate
            $this->add_rate( $rate );
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
        public function admin_enqueue_scripts() {
            $version = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? uniqid() : SLFW_VERSION;

            $file_url  = SLFW_PLUGIN_URL . '/modules/woocommerce/assets/build/js/loggi-shipping.min.js';
            wp_enqueue_script( $this->id . '-loggi-shipping-script', $file_url, array( 'jquery' ), $version, true );

            $file_url  = SLFW_PLUGIN_URL . '/modules/woocommerce/assets/build/css/loggi-shipping.min.css';
            wp_enqueue_style( $this->id . '-loggi-shipping-style', $file_url, array(), $version );
        }

    }

}
