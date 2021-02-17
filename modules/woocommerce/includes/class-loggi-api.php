<?php
/**
 * SLFW_Loggi_Api
 * API Client to Loggi GraphQL API
 *
 * @package         Shipping_Loggi_For_WooCommerce
 * @subpackage      SLFW_Module_Woocommerce
 * @since           1.0.0
 *
 */

// If this file is called directly, call the cops.
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

if ( ! class_exists( 'SLFW_Loggi_Api' ) ) {

    class SLFW_Loggi_Api {

        /**
         * The environment
         * @var string
         */
        private $environment;

        /**
         * The api_email
         * @var string
         */
        private $api_email;

        /**
         * The api_key
         * @var string
         */
        private $api_key;

        /**
         * The constructor
         *
         * @param string $environment
         */
        public function __construct( $environment, $api_email = '', $api_key = '' ) {
            $this->environment = $environment;
            $this->api_email = $api_email;
            $this->api_key = $api_key;
        }

        /**
         * Request a API from e-mail and password
         *
         * @param string $email
         * @param string $password
         * @return string
         */
        public function retrieve_api_key( $email, $password ) {
            if ( ! $this->can_make_request( true ) ) {
                return '';
            }

            $params = array(
                'input' => array(
                    'email' => $email,
                    'password' => $password,
                ),
            );

            $selection = array(
                'user' => array(
                    'apiKey',
                ),
            );

            $response = $this->make_request( 'login', $params, $selection );
            $user = $response ? $response['user'] : array();

            return $user['apiKey'] ?? '';
        }

        /**
         * Request a API from all shops
         *
         * @return array
         */
        public function retrieve_all_shops() {
            if ( ! $this->can_make_request() ) {
                return array();
            }

            $selection = array(
                'edges' => array(
                    'node' => array(
                        'name',
                        'pickupInstructions',
                        'pk',
                        'externalId',
                        'address' => array(
                            'pos',
                            'addressSt',
                            'addressData',
                        ),
                        'chargeOptions' => array(
                            'label',
                        ),
                    ),
                ),
            );

            $response = $this->make_request( 'allShops', array(), $selection );
            $edges = $response ? $response['edges'] : array();

            $shops = array();
            foreach ( $edges as $node ) {
                $node = $node['node'] ?? array();
                if ( empty( $node['pk'] ) ) {
                    continue;
                }

                $shops[ $node['pk'] ] = $node;
            }

            return $shops;
        }

        /**
         * Request a API from all shops
         *
         * @return array
         */
        public function retrieve_order_estimation( $shopId, $pickup, $destination, $packages ) {
            if ( ! $this->can_make_request() ) {
                return array();
            }

            $params = array(
                'shopId'  => (int) $shopId,
                'pickups' => array(
                    array(
                        'address' => array(
                            'address'    => $pickup['address'] ?? '',
                            'complement' => $pickup['complement'] ?? '',
                        ),
                    ),
                ),
                'packages' => array(),
            );

            foreach ( $packages as $package ) {
                $params['packages'][] = array(
                    'pickupIndex' => 0,
                    'recipient'   => array(
                        'name'  => 'Cliente A',
                        'phone' => '11999999999',
                    ),
                    'address'     => array(
                        'address'    => $destination['address'] ?? '',
                        'complement' => $destination['complement'] ?? '',
                    ),
                    'dimensions'  => array(
                        'width'  => $package['width'],
                        'height' => $package['height'],
                        'weight' => $package['weight'],
                        'length' => $package['length'],
                    ),
                );
            }

            $selection = array(
                'totalEstimate' => array(
                    'totalCost',
                    'totalEta',
                    'totalDistance',
                ),
                'ordersEstimate' => array(
                    'packages' => array(
                        'isReturn',
                        'cost',
                        'eta',
                        'outOfCoverageArea',
                        'outOfCityCover',
                        'originalIndex',
                        'resolvedAddress',
                    ),
                    'optimized' => array(
                        'cost',
                        'eta',
                        'distance',
                    ),
                ),
                'packagesWithErrors' => array(
                    'originalIndex',
                    'error',
                    'resolvedAddress',
                ),
            );

            return $this->make_request( 'estimateCreateOrder', $params, $selection, 'query' );
        }

        /**
         * Make a request to API
         *
         * @param string $type
         * @return string
         */
        protected function make_request( $name, $params = array(), $selection = array(), $operation = '' ) {
            $args = array(
                'timeout' => 60,
                'blocking' => true,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode(
                    array(
                        'query' => $this->parse_operation( $name, $params, $selection, $operation ),
                    )
                ),
            );

            if ( ! empty( $this->api_email ) && ! empty( $this->api_key ) ) {
                $args['headers']['Authorization'] = sprintf( 'ApiKey %s:%s', $this->api_email, $this->api_key );
            }

            $response = wp_remote_post( $this->get_url(), $args );
            if ( is_wp_error( $response ) ) {
                error_log( $response->get_error_message() );
                return false;
            }

            $response = json_decode( $response['body'] ?? array(), true );

            // Check for errors
            if ( ! empty( $response['errors'] ) ) {
                foreach ( $response['errors'] as $error ) {
                    error_log( $error['message'] ?? '' );
                }

                return false;
            }

            if ( empty( $response['data'] ) ) {
                return false;
            }

            return $response['data'][ $name ] ?? false;
        }

        /**
         * Check all data was provided to API
         *
         * @param boolean $ignore_credentials
         * @return boolean
         */
        private function can_make_request( $ignore_credentials = false ) {
            if ( empty( $this->environment ) ) {
                return false;
            }

            if ( empty( $this->api_email ) || empty( $this->api_key ) ) {
                return $ignore_credentials;
            }

            return true;
        }

        /**
         * Parse a GraphQL operation
         *
         * @param  string $name
         * @param  mixed $params
         * @param  mixed $selection
         * @param  mixed $operation
         * @return string
         */
        private function parse_operation( $name, $params, $selection, $operation ) {
            $params = $this->parse_params( $params );
            $selection = $this->parse_selection( $selection );

            if ( ! empty( $params ) ) {
                return sprintf( '%s {%s(%s) {%s}}', $operation ? $operation : 'mutation', $name, $params, $selection );
            }

            return sprintf( '%s {%s {%s}}', $operation ? $operation : 'query', $name, $selection );
        }

        /**
         * Parse the operation params
         *
         * @param  array $params
         * @return string
         */
        private function parse_params( $params ) {
            $parsed = '';

            foreach ( (array) $params as $key => $values ) {
                $parsed .= sprintf( '%s:%s', $key, $this->graphql_encode( $values, true ) );
            }

            $parsed = preg_replace( '/(^|[^,{]+){({+)/', '$1[$2', $parsed );
            $parsed = preg_replace( '/(}+)}([^,}]+|$)/', '$1]$2', $parsed );

            return $parsed;
        }

        /**
         * Parse the operation selection
         *
         * @param  array $params
         * @return string
         */
        private function parse_selection( $selection ) {
            $parsed = '';

            foreach ( (array) $selection as $key => $values ) {
                $parsed .= sprintf( '%s %s', $key, $this->graphql_encode( $values ) );
            }

            return $parsed;
        }

        /**
         * Encode a array to GraphQL pattern
         *
         * @param  array  $data
         * @param  boolean $is_params
         * @return string
         */
        private function graphql_encode( $data, $is_params = false ) {
            if ( is_scalar( $data ) ) {
                $data = $data;

                if ( $is_params && is_string( $data ) ) {
                    return '"' . $data . '"';
                }

                return $data;
            }

            $parsed = array();
            foreach ( (array) $data as $key => $values ) {
                if ( is_numeric( $key ) ) {
                    $parsed[] = $this->graphql_encode( $values, $is_params );
                    continue;
                }

                $format = $is_params ? '%s: %s' : '%s %s';
                $parsed[] = sprintf( $format, $key, $this->graphql_encode( $values, $is_params ) );
            }

            $parsed = sprintf( '{%s}', implode( ( $is_params ? ',' : ' ' ), $parsed ) );

            return $parsed;
        }

        /**
         * Get API url for requests
         *
         * @return string
         */
        private function get_url() {
            if ( $this->environment === 'production' ) {
                return 'https://www.loggi.com/graphql';
            }

            return 'https://staging.loggi.com/graphql';
        }

    }

}
