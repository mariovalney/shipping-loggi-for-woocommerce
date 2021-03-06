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

        use SLFW_Has_Logger;
        use SLFW_Has_Time;
        use SLFW_Has_Session;

        /**
         * The shipping method ID
         * @var string
         */
        const ID = 'loggi-shipping';

        /**
         * Available Shops
         * @var array
         */
        protected $shops;

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
            );

            // Load the form fields.
            $this->init_form_fields();

            // Options and data
            $this->enabled = $this->get_option( 'enabled' );
            $this->title   = $this->get_option( 'title', 'Loggi' );

            // Log
            if ( $this->get_option( 'debug', 'no' ) === 'yes' ) {
                $this->logger = new WC_Logger();
            }

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
            $api_key_desc_tip = __( 'Require it sending a mail for Loggi Support (contato.api@loggi.com).', 'shipping-loggi-for-woocommerce' );

            $api_key_description = sprintf(
                // translators: Link to open passsword form. Text is "click here".
                __( '%s to use your password.', 'shipping-loggi-for-woocommerce' ),
                '<a class="slfw-request-api-key" href="#">' . __( 'Click here', 'shipping-loggi-for-woocommerce' ) . '</a>',
            );

            $shop_description = sprintf(
                // translators: Link to open passsword form. Text is "click here".
                __( '%s to reload your stores.', 'shipping-loggi-for-woocommerce' ),
                '<a href="#">' . __( 'Click here', 'shipping-loggi-for-woocommerce' ) . '</a>',
            );
            $shop_description = '<span id="slfw-reload-shops-description">' . $shop_description . '</span>';

            $debug_description = sprintf(
                // translators: link to logs page
                __( 'You can check logs in %s.', 'shipping-loggi-for-woocommerce' ),
                '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '" target="_blank">' . __( 'System Status &gt; Logs', 'shipping-loggi-for-woocommerce' ) . '</a>'
            );

            $main_form_fields = array(
                'title'              => array(
                    'type'              => 'text',
                    'title'             => __( 'Title', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => __( 'Shipping method title for customer.', 'shipping-loggi-for-woocommerce' ),
                    'default'           => __( 'Loggi', 'shipping-loggi-for-woocommerce' ),
                    'custom_attributes' => array( 'required' => 'required' ),
                ),
                'shipping_classes'   => array(
                    'type'        => 'multiselect',
                    'class'       => 'wc-enhanced-select',
                    'title'       => __( 'Shipping Classes', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'    => __( 'You can select "No Shipping Class" and/or how much shipping classes you want to use with Loggi.', 'shipping-loggi-for-woocommerce' ),
                    'default'     => '',
                    'options'     => $this->get_shipping_classes_as_options(),
                ),
                'merge_boxes'        => array(
                    'type'        => 'select',
                    'class'       => 'wc-enhanced-select',
                    'title'       => __( 'Try to Merge Boxes', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'    => __( 'Loggi only try to merge two packages in one delivery. If YES we will try to agrupate items by ourselves.', 'shipping-loggi-for-woocommerce' ),
                    'default'     => '0',
                    'options'     => array(
                        '0' => __( 'No', 'shipping-loggi-for-woocommerce' ),
                        '1' => __( 'Yes', 'shipping-loggi-for-woocommerce' ),
                    ),
                ),
                'show_estimation'     => array(
                    'type'        => 'select',
                    'class'       => 'wc-enhanced-select',
                    'title'       => __( 'Delivery Estimation', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'    => __( 'Check Loggi service time for more details.', 'shipping-loggi-for-woocommerce' ),
                    'default'     => '0',
                    'options'     => array(
                        '0' => __( 'No', 'shipping-loggi-for-woocommerce' ),
                        '1' => __( 'Yes', 'shipping-loggi-for-woocommerce' ),
                    ),
                ),
                'additional_time'     => array(
                    'type'              => 'number',
                    'class'             => 'short',
                    'title'             => __( 'Additional Time', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => __( 'How much hours you need to prepare the order to be delivered?', 'shipping-loggi-for-woocommerce' ),
                    'default'           => 0,
                    'custom_attributes' => array(
                        'min'  => 0,
                        'step' => 1,
                    ),
                ),
            );

            $pickup_form_fields = array(
                'pickup_section'    => array(
                    'type'        => 'title',
                    'title'       => __( 'Pickup Address', 'shipping-loggi-for-woocommerce' ),
                    'description' => __( 'From where you are going to send your packages.', 'shipping-loggi-for-woocommerce' ),
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
                'pickup_postcode'   => array(
                    'type'              => 'text',
                    'title'             => __( 'Postcode', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => __( 'Your address postcode or zipcode.', 'shipping-loggi-for-woocommerce' ),
                    'placeholder'       => __( '99999-999', 'shipping-loggi-for-woocommerce' ),
                    'custom_attributes' => array( 'required' => 'required' ),
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
                    'desc_tip'          => __( 'Select your country and state/region, if available.', 'shipping-loggi-for-woocommerce' ),
                    'default'           => get_option( 'woocommerce_default_country' ),
                    'custom_attributes' => array( 'required' => 'required' ),
                ),
            );

            $working_fields = array(
                'working_section'  => array(
                    'type'        => 'title',
                    'title'       => __( 'Working Time', 'shipping-loggi-for-woocommerce' ),
                    'description' => __( 'Select your working time to make Loggi available.', 'shipping-loggi-for-woocommerce' ),
                ),
                'working_day_0'    => array(
                    'type'              => 'select_working_day',
                    'title'             => __( 'Sunday', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => __( 'Mark the checkbox to allow Loggi for orders in this day and select the start and end time.', 'shipping-loggi-for-woocommerce' ),
                    'default'           => '1|06:00|23:59',
                ),
                'working_day_1'    => array(
                    'type'              => 'select_working_day',
                    'title'             => __( 'Monday', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => __( 'Mark the checkbox to allow Loggi for orders in this day and select the start and end time.', 'shipping-loggi-for-woocommerce' ),
                    'default'           => '1|06:00|23:59',
                ),
                'working_day_2'    => array(
                    'type'              => 'select_working_day',
                    'title'             => __( 'Tuesday', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => __( 'Mark the checkbox to allow Loggi for orders in this day and select the start and end time.', 'shipping-loggi-for-woocommerce' ),
                    'default'           => '1|06:00|23:59',
                ),
                'working_day_3'    => array(
                    'type'              => 'select_working_day',
                    'title'             => __( 'Wednesday', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => __( 'Mark the checkbox to allow Loggi for orders in this day and select the start and end time.', 'shipping-loggi-for-woocommerce' ),
                    'default'           => '1|06:00|23:59',
                ),
                'working_day_4'    => array(
                    'type'              => 'select_working_day',
                    'title'             => __( 'Thursday', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => __( 'Mark the checkbox to allow Loggi for orders in this day and select the start and end time.', 'shipping-loggi-for-woocommerce' ),
                    'default'           => '1|06:00|23:59',
                ),
                'working_day_5'    => array(
                    'type'              => 'select_working_day',
                    'title'             => __( 'Friday', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => __( 'Mark the checkbox to allow Loggi for orders in this day and select the start and end time.', 'shipping-loggi-for-woocommerce' ),
                    'default'           => '1|06:00|23:59',
                ),
                'working_day_6'    => array(
                    'type'              => 'select_working_day',
                    'title'             => __( 'Saturday', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => __( 'Mark the checkbox to allow Loggi for orders in this day and select the start and end time.', 'shipping-loggi-for-woocommerce' ),
                    'default'           => '1|06:00|23:59',
                ),
            );

            $api_form_fields = array(
                'api_section'       => array(
                    'type'        => 'title',
                    'title'       => __( 'API Settings', 'shipping-loggi-for-woocommerce' ),
                    'description' => __( 'Connect your store with Loggi.', 'shipping-loggi-for-woocommerce' ),
                ),
                'environment'        => array(
                    'type'              => 'select',
                    'class'             => 'loggi-api-input wc-enhanced-select',
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
                    'class'             => 'loggi-api-input',
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
                    'class'             => 'loggi-api-input',
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
                    'class'             => 'loggi-api-input',
                    'title'             => __( 'API Key', 'shipping-loggi-for-woocommerce' ),
                    'description'       => $api_key_description,
                    'desc_tip'          => __( 'For Staging.', 'shipping-loggi-for-woocommerce' ) . ' ' . $api_key_desc_tip,
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
                    'class'             => 'loggi-api-input',
                    'title'             => __( 'API Key', 'shipping-loggi-for-woocommerce' ),
                    'description'       => $api_key_description,
                    'desc_tip'          => __( 'For Production.', 'shipping-loggi-for-woocommerce' ) . ' ' . $api_key_desc_tip,
                    'default'           => '',
                    'custom_attributes' => array(
                        'required'                    => 'required',
                        'data-slfw-environment-field' => 'production',
                        'data-email-input'            => 'woocommerce_' . $this->id . '_production_api_email',
                        'data-environment'            => 'production',
                        'data-nonce'                  => wp_create_nonce( 'slfw-request-api-key' ),
                    ),
                ),
                'shop'              => array(
                    'type'              => 'select',
                    'class'             => 'wc-enhanced-select',
                    'title'             => __( 'Shop', 'shipping-loggi-for-woocommerce' ),
                    'desc_tip'          => __( 'The shop from Loggi dashboard. Maybe you should ask Loggi Support (contato.api@loggi.com) to create one.', 'shipping-loggi-for-woocommerce' ),
                    'description'       => $shop_description,
                    'default'           => '0',
                    'options'           => array(),
                    'custom_attributes' => array(
                        'required'   => 'required',
                        'data-nonce' => wp_create_nonce( 'slfw-all-shops-as-options' ),
                    ),
                ),
            );

            $advanced_form_fields = array(
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

            /**
             * Filter: 'slfw_form_fields_after_main'
             *
             * @param array $fields
             * @param SLFW_Shipping_Method $shipping_method
             *
             * @var array
             */
            $form_fields_after_main = apply_filters( 'slfw_form_fields_after_main', array(), $this );

            /**
             * Filter: 'slfw_form_fields_after_api'
             *
             * @param array $fields
             * @param SLFW_Shipping_Method $shipping_method
             *
             * @var array
             */
            $form_fields_after_api = apply_filters( 'slfw_form_fields_after_api', array(), $this );

            /**
             * Finally the form fields
             * We are using array_diff_key() to avoid developers removing core fields
             */
            $this->instance_form_fields = array_merge(
                $main_form_fields,
                array_diff_key( (array) $form_fields_after_main, $main_form_fields ),
                $pickup_form_fields,
                $working_fields,
                $api_form_fields,
                array_diff_key( (array) $form_fields_after_api, $main_form_fields, $pickup_form_fields, $working_fields, $api_form_fields ),
                $advanced_form_fields
            );
        }

        /**
         * Return admin options as a html string.
         *
         * @return string
         */
        public function get_admin_options_html() {
            $this->instance_form_fields['shop']['options'] = $this->get_shops_as_options();

            /**
             * Action: 'slfw_before_get_admin_options_html'
             *
             * @param SLFW_Shipping_Method $shipping_method
             */
            do_action( 'slfw_before_get_admin_options_html', $this );

            return parent::get_admin_options_html();
        }

        /**
         * Generate a select to country-state
         *
         * @see WC_Settings_API->generate_settings_html()
         * @see WC_Admin_Settings->output_fields()
         *
         * @param  string $key
         * @param  array $data
         * @return string
         */
        public function generate_single_select_country_html( $key, $data ) {
            $data['id'] = $this->get_field_key( $key );
            $data['value'] = $this->get_option( $key );

            ob_start();
            WC_Admin_Settings::output_fields( array( $data ) );

            return ob_get_clean();
        }

        /**
         * Generate a input to activate a day and choose start and end time
         *
         * @see WC_Settings_API->generate_settings_html()
         *
         * @param  string $key
         * @param  array $data
         * @return string
         */
        public function generate_select_working_day_html( $key, $data ) {
            $defaults  = array(
                'title'    => '',
                'desc_tip' => false,
                'default'  => '1|06:00|23:59',
            );

            $data = wp_parse_args( $data, $defaults );

            $field_key = $this->get_field_key( $key );

            $value = $this->get_option( $key );
            $value = $this->parse_working_input( $value );

            $disabled = disabled( empty( $value->active ), true, false );

            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr( $field_key ); ?>">
                        <?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?>
                    </label>
                </th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>

                        <div class="slfw-working-day-input">
                            <input class="input-active" type="checkbox" value="1" <?php checked( $value->active, '1' ); ?> />

                            <div class="input-timepicker">
                                <input class="input-hour input-start-time" type="text" pattern="[\d]{2}" <?php esc_html_e( $disabled ); ?> value="<?php echo esc_attr( $value->start->h ); ?>">
                                <span>:</span>
                                <input class="input-minute input-start-time" type="text" pattern="[\d]{2}" <?php esc_html_e( $disabled ); ?> value="<?php echo esc_attr( $value->start->i ); ?>">

                                <span class="time-separator"><?php echo esc_attr_x( 'to', 'time a TO time b', 'shipping-loggi-for-woocommerce' ); ?></span>

                                <input class="input-hour input-end-time" type="text" pattern="[\d]{2}" <?php esc_html_e( $disabled ); ?> value="<?php echo esc_attr( $value->end->h ); ?>">
                                <span>:</span>
                                <input class="input-minute input-end-time" type="text" pattern="[\d]{2}" <?php esc_html_e( $disabled ); ?> value="<?php echo esc_attr( $value->end->i ); ?>">
                            </div>
                        </div>

                        <input type="hidden" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" value="<?php echo esc_attr( $this->get_option( $key ) ); ?>"/>
                    </fieldset>
                </td>
            </tr>
            <?php

            return ob_get_clean();
        }

        /**
         * Calculate shipping rates
         *
         * @param mixed $package
         * @return void
         *
         * @SuppressWarnings(PHPMD.MissingImport)
         * @SuppressWarnings(PHPMD.NPathComplexity)
         * @SuppressWarnings(PHPMD.CyclomaticComplexity)
         * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
         */
        public function calculate_shipping( $package = array() ) {
            $now = current_datetime();
            $time = $now->format( 'U' );

            $schedule = $this->get_option( 'working_day_' . $now->format( 'w' ) );
            $schedule = $this->parse_working_input( $schedule );

            if ( ! $schedule->active || $schedule->start->time > $time || $schedule->end->time < $time ) {
                return;
            }

            $destination = $this->format_address( $package['destination'] );
            if ( empty( $destination ) ) {
                $this->log( '[calculate_shipping] Destination is empty.' );
                return;
            }

            $shopId = $this->get_option( 'shop' );
            $pickup = $this->get_pickup_address();

            if ( empty( $shopId ) ) {
                $this->log( '[calculate_shipping] Invalid ShopId: ' . $shopId );
                return;
            }

            if ( empty( $pickup ) ) {
                $this->log( '[calculate_shipping] Invalid pickup address:', $pickup );
                return;
            }

            $shipping_classes = (array) $this->get_option( 'shipping_classes', array() );
            $merge_boxes = $this->get_option( 'merge_boxes', '0' ) === '1';

            $loggi_package = new SLFW_Loggi_Package( $package, $shipping_classes, $merge_boxes );
            $boxes = $loggi_package->get_boxes();

            // Check we can delivery it
            if ( ! $loggi_package->can_be_delivered() ) {
                $data = array(
                    'package' => $package,
                    'loggi'   => $boxes,
                );

                $this->log( '[calculate_shipping] Package cannot be delivered:', $data );
                return;
            }

            // Retrieve the Loggi Estimation

            /**
             * Filter: 'slfw_use_session_on_estimation'
             *
             * We will use a cache for estimation only if is not checkout page.
             * You can change it returning true or false.
             *
             * @param boolean is_checkout()
             * @param SLFW_Loggi_Package $loggi_package
             *
             * @var boolean
             */
            $use_session = apply_filters( 'slfw_use_session_on_estimation', ! is_checkout(), $loggi_package );

            $session_key = 'estimation_' . $loggi_package->unique_identifier();

            $estimation = $this->from_session( $session_key );
            if ( empty( $estimation ) || ! $use_session ) {
                error_log( 'saving' );
                $estimation = $this->api()->retrieve_order_estimation( $shopId, $pickup, $destination, $boxes );
                $this->to_session( $session_key, $estimation );
            }

            if ( empty( $estimation ) || empty( $estimation['cost'] ) || ! empty( $estimation['errors'] ) ) {
                $data = array(
                    'order'      => $package,
                    'estimation' => $estimation,
                );

                $this->log( '[calculate_shipping] Estimation: ', $data );
                return;
            }

            $rate = array(
                'label'     => $this->title,
                'cost'      => (float) $estimation['cost'],
                'taxes'     => false,
                'meta_data' => array(
                    'loggi_estimation' => $estimation,
                    'loggi_eta'        => $this->calculate_estimation_eta( $estimation ),
                ),
            );

            /**
             * Filter: 'slfw_calculate_shipping_rate'
             *
             * You can change the rate args before add to cart.
             * Return empty to remove.
             *
             * @param array $rate
             * @param array $estimation
             * @param array $package
             * @param SLFW_Shipping_Method $shipping_method
             *
             * @var array
             */
            $rate = apply_filters( 'slfw_calculate_shipping_rate', $rate, $estimation, $package, $this );

            if ( empty( $rate ) ) {
                return;
            }

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

            return new SLFW_Loggi_Api( $environment, $api_email, $api_key, $this->logger );
        }

        /**
         * Get the pickup address formated as string
         *
         * @return array
         */
        protected function get_pickup_address() {
            $country = $this->get_option( 'pickup_country' );
            $country = explode( ':', $country, 2 );

            $countries = WC()->countries->get_countries();

            $pickup_country = $countries[ $country[0] ] ?? $country[0];
            $pickup_state = $country[1] ?? '';

            $address = array(
                'address_1'  => $this->get_option( 'pickup_address_1' ),
                'address_2'  => $this->get_option( 'pickup_address_2' ),
                'complement' => $this->get_option( 'pickup_complement' ),
                'postcode'   => $this->get_option( 'pickup_postcode' ),
                'city'       => $this->get_option( 'pickup_city' ),
                'state'      => $pickup_state,
                'country'    => $pickup_country,
            );

            /**
             * Filter: 'slfw_pickup_address'
             *
             * @param array $address
             * @param SLFW_Shipping_Method $shipping_method
             *
             * @var array
             */
            $address = apply_filters( 'slfw_pickup_address', $address, $this );

            return $this->format_address( $address );
        }

        /**
         * Get the pickup address formated as string
         *
         * @param array $address
         * @return string
         */
        protected function format_address( $address ) {
            $default = array(
                'address_1'  => '',
                'address_2'  => '',
                'complement' => '',
                'postcode'   => '',
                'city'       => '',
                'state'      => '',
                'country'    => '',
            );

            $address = wp_parse_args( (array) $address, $default );
            $address['postcode'] = preg_replace( '/\D/', '', (string) $address['postcode'] );

            $line_2 = array( $address['address_2'], $address['city'] );
            $line_2 = implode( ', ', array_filter( $line_2 ) );

            $line_3 = array( $address['state'], $address['country'], $address['postcode'] );
            $line_3 = implode( ', ', array_filter( $line_3 ) );

            $formated = array( $address['address_1'], $line_2, $line_3 );

            $formated = array(
                'address'    => implode( ' - ', array_filter( $formated ) ),
                'complement' => $address['complement'],
            );

            /**
             * Filter: 'slfw_format_address'
             *
             * @param array $formated
             * @param array $address
             * @param SLFW_Shipping_Method $shipping_method
             *
             * @var array
             */
            return apply_filters( 'slfw_format_address', $formated, $address, $this );
        }

        /**
         * Parse a string to get a working_time value
         *
         * @param  string $value
         * @return object
         */
        private function parse_working_input( $value ) {
            $value = explode( '|', $value );

            return (object) array(
                'active' => empty( $value[0] ) ? '0' : '1',
                'start'  => $this->parse_time( $value[1] ?? '' ),
                'end'    => $this->parse_time( $value[2] ?? '' ),
            );
        }

        /**
         * Calculate the ETA from Loggi Estimation
         *
         * @param array $estimation
         *
         * @return int
         */
        protected function calculate_estimation_eta( $estimation ) {
            if ( empty( $this->get_option( 'show_estimation', '0' ) ) ) {
                return 0;
            }

            $eta = (int) ( $estimation['eta'] ?? 0 );

            /**
             * Filter: 'slfw_rate_additional_time'
             *
             * @param int $additional_time
             * @param array $estimation
             * @param WC_Shipping_Method $shipping_method
             *
             * @var int
             */
            $additional_time = (int) apply_filters( 'slfw_rate_additional_time', $this->get_option( 'additional_time', 0 ), $estimation, $this );
            if ( $additional_time ) {
                $eta += $additional_time * HOUR_IN_SECONDS;
            }

            return $eta;
        }

        /**
         * Get all shops from API and create a option array
         *
         * @return array
         */
        protected function get_shops_as_options() {
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


        /**
         * Get shipping classes as options.
         *
         * @return array
         */
        protected function get_shipping_classes_as_options() {
            $shipping_classes = WC()->shipping->get_shipping_classes();

            $options = array( '-1' => __( 'No Shipping Class', 'shipping-loggi-for-woocommerce' ) );

            if ( ! empty( $shipping_classes ) ) {
                $options += wp_list_pluck( $shipping_classes, 'name', 'term_id' );
            }

            return $options;
        }

    }

}
