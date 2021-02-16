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
         * Available Shops
         * @var array
         */
        protected $shops;

        /**
         * The constructor
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
            );

            // Load the form fields.
            $this->init_form_fields();

            // Options and data
            $this->enabled     = $this->get_option( 'enabled' );
            $this->title       = $this->get_option( 'title', 'Loggi' );
            $this->debug       = $this->get_option( 'debug' );

            // Save admin options
            add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        /**
         * Initialise Gateway Settings Form Fields.
         *
         * @return void
         *
         * @SuppressWarnings(PHPMD.LongVariable)
         * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
         */
        public function init_form_fields() {
            $api_key_debug_desc_tip = __( 'Require it sending a mail for Loggi Support (contato.api@loggi.com).', 'shipping-loggi-for-woocommerce' );

            $api_key_debug_description = sprintf(
                // translators: Link to open passsword form. Text is "click here".
                __( '%s to use your password.', 'shipping-loggi-for-woocommerce' ),
                '<a class="slfw-request-api-key" href="#">' . __( 'Click here', 'shipping-loggi-for-woocommerce' ) . '</a>',
            );

            $debug_description = sprintf(
                // translators: link to logs page
                __( 'You can check logs in %s.', 'shipping-loggi-for-woocommerce' ),
                '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '" target="_blank">' . __( 'System Status &gt; Logs', WCB_TEXTDOMAIN ) . '</a>'
            );

            $this->instance_form_fields = array(
                'title'              => array(
                    'type'              => 'text',
                    'title'             => __( 'Title', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => __( 'Shipping method title for customer.', 'shipping-loggi-for-woocommerce' ),
                    'default'           => __( 'Loggi', 'shipping-loggi-for-woocommerce' ),
                    'custom_attributes' => array( 'required' => 'required' ),
                ),
                'shop'              => array(
                    'type'              => 'select',
                    'title'             => __( 'Shop', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => __( 'The shop from Loggi dashboard. Maybe you should ask Loggi Support (contato.api@loggi.com) to create one.', 'shipping-loggi-for-woocommerce' ),
                    'default'           => '0',
                    'options'           => array(),
                    'custom_attributes' => array( 'required' => 'required' ),
                ),
                'origin_section'    => array(
                    'type'        => 'title',
                    'title'       => __( 'Pickup Address', 'shipping-loggi-for-woocommerce' ),
                ),
                'pickup_address_1'  => array(
                    'type'              => 'text',
                    'title'             => __( 'Address 1', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => __( 'Complete address line 1: address type, name and number.', 'shipping-loggi-for-woocommerce' ),
                    'placeholder'       => __( 'Street Example with Number, 999', 'shipping-loggi-for-woocommerce' ),
                    'custom_attributes' => array( 'required' => 'required' ),
                ),
                'pickup_address_2'  => array(
                    'type'              => 'text',
                    'title'             => __( 'Address 2', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => __( 'Complete address line 2: neighborhood', 'shipping-loggi-for-woocommerce' ),
                    'placeholder'       => __( 'My neighborhood', 'shipping-loggi-for-woocommerce' ),
                    'custom_attributes' => array( 'required' => 'required' ),
                ),
                'pickup_complement' => array(
                    'type'              => 'text',
                    'title'             => __( 'Complement', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => __( 'Complement is not required but you should provide details to make easy the pickup.', 'shipping-loggi-for-woocommerce' ),
                ),
                'pickup_city'       => array(
                    'type'              => 'text',
                    'title'             => __( 'City', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => __( 'The address city name.', 'shipping-loggi-for-woocommerce' ),
                    'custom_attributes' => array( 'required' => 'required' ),
                ),
                'pickup_country'    => array(
                    'type'              => 'single_select_country',
                    'title'             => __( 'State and Country', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => __( 'The address city name.', 'shipping-loggi-for-woocommerce' ),
                    'default'           => get_option( 'woocommerce_default_country' ),
                    'custom_attributes' => array( 'required' => 'required' ),
                ),
                'api_section'       => array(
                    'type'        => 'title',
                    'title'       => __( 'API Settings', 'shipping-loggi-for-woocommerce' ),
                ),
                'environment'        => array(
                    'type'              => 'select',
                    'title'             => __( 'Environment', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => __( 'Loggi API environment. Use Staging for tests and Production for your running shop.', 'shipping-loggi-for-woocommerce' ),
                    'default'           => 'staging',
                    'options'           => array(
                        'staging'    => __( 'Staging', 'shipping-loggi-for-woocommerce' ),
                        'production' => __( 'Production', 'shipping-loggi-for-woocommerce' ),
                    ),
                    'custom_attributes' => array( 'required' => 'required' ),
                ),
                'staging_api_email'    => array(
                    'type'              => 'email',
                    'title'             => __( 'E-mail', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => __( 'The e-mail you use on Loggi Staging dashboard.', 'shipping-loggi-for-woocommerce' ),
                    'default'           => '',
                    'custom_attributes' => array(
                        'required' => 'required',
                        'data-slfw-environment-field' => 'staging',
                    ),
                ),
                'production_api_email' => array(
                    'type'              => 'email',
                    'title'             => __( 'E-mail', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => __( 'The e-mail you use on Loggi Production dashboard.', 'shipping-loggi-for-woocommerce' ),
                    'default'           => '',
                    'custom_attributes' => array(
                        'required' => 'required',
                        'data-slfw-environment-field' => 'production',
                    ),
                ),
                'staging_api_key'    => array(
                    'type'              => 'text',
                    'title'             => __( 'API Key', 'shipping-loggi-for-woocommerce' ),
                    'description'       => $api_key_debug_description,
                    'desc_tip'          => __( 'For Staging.', 'shipping-loggi-for-woocommerce' ) . ' ' . $api_key_debug_desc_tip,
                    'default'           => '',
                    'custom_attributes' => array(
                        'required'                    => 'required',
                        'data-slfw-environment-field' => 'staging',
                        'data-email-input'            => 'woocommerce_' . $this->id . '_staging_api_email',
                        'data-environment'            => 'staging',
                        'data-nonce'                  => wp_create_nonce( 'slfw-request-api-key' ),
                    ),
                ),
                'production_api_key' => array(
                    'type'              => 'text',
                    'title'             => __( 'API Key', 'shipping-loggi-for-woocommerce' ),
                    'description'       => $api_key_debug_description,
                    'desc_tip'          => __( 'For Production.', 'shipping-loggi-for-woocommerce' ) . ' ' . $api_key_debug_desc_tip,
                    'default'           => '',
                    'custom_attributes' => array(
                        'required'                    => 'required',
                        'data-slfw-environment-field' => 'production',
                        'data-email-input'            => 'woocommerce_' . $this->id . '_production_api_email',
                        'data-environment'            => 'production',
                        'data-nonce'                  => wp_create_nonce( 'slfw-request-api-key' ),
                    ),
                ),
                'advanced_section'   => array(
                    'type'  => 'title',
                    'title' => __( 'Advanced Settings', 'shipping-loggi-for-woocommerce' ),
                ),
                'debug'              => array(
                    'type'        => 'checkbox',
                    'title'       => __( 'Debug Log', 'shipping-loggi-for-woocommerce' ),
                    'label'       => __( 'Enable logging', 'shipping-loggi-for-woocommerce' ),
                    'description' => $debug_description,
                    'default'     => 'no',
                ),
            );
        }

        /**
         * Return admin options as a html string.
         *
         * @return string
         */
        public function get_admin_options_html() {
            $environment = $this->get_option( 'environment' );

            $this->instance_form_fields['api_section']['description'] = '<span class="slfw-api-section-description ' . esc_attr( $environment ) . '">' . __( 'Save your settings before continue.' ) . '</span>';

            $this->instance_form_fields['shop']['custom_attributes']['data-slfw-environment-field'] = $environment;
            $this->instance_form_fields['shop']['options'] = $this->get_shops_as_options();

            return parent::get_admin_options_html();
        }

        public function generate_single_select_country_html( $key, $data ) {
            $data['id'] = $this->get_field_key( $key );
            $data['value'] = $this->get_option( $key );

            ob_start();
            WC_Admin_Settings::output_fields( array( $data ) );

            return ob_get_clean();
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
         * Create a instance to API
         *
         * @return SLFW_Loggi_Api
         *
         * @SuppressWarnings(PHPMD.MissingImport)
         */
        protected function api() {
            $environment = $this->get_option( 'environment' );
            $api_email = $this->get_option( $environment . '_api_email' );
            $api_key = $this->get_option( $environment . '_api_key' );

            return new SLFW_Loggi_Api( $environment, $api_email, $api_key );
        }

        /**
         * Get all shops from API and create a option array
         *
         * @return array
         */
        private function get_shops_as_options() {
            if ( ! is_null( $this->shops ) ) {
                return $this->shops;
            }

            $shops = array();
            $all_shops = $this->api()->retrieve_all_shops();

            foreach ( $all_shops as $id => $shop ) {
                $shops[ $id ] = sprintf( '(%s) %s', $id, ( $shop['name'] ?? '' ) );
            }

            if ( empty( $shops ) ) {
                return array( '0' => __( 'You have any available shop.', 'shipping-loggi-for-woocommerce' ) );
            }

            return $shops;
        }

    }

}
