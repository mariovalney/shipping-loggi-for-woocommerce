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

        use SLFW_Has_Logger;

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
        public function __construct( $environment, $api_email = '', $api_key = '', $logger = null ) {
            $this->environment = $environment;
            $this->api_email = $api_email;
            $this->api_key = $api_key;
            $this->logger = $logger;
        }

        /**
         * Request a API KEY from e-mail and password
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

            /**
             * Filter: 'slfw_api_retrieve_api_key'
             *
             * @param array $apiKey
             * @param SLFW_Loggi_Api $api
             *
             * @var array
             */
            return apply_filters( 'slfw_api_retrieve_api_key', ( $user['apiKey'] ?? '' ), $this );
        }

        /**
         * Request a list of all shops to API
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

            /**
             * Filter: 'slfw_api_retrieve_all_shops'
             *
             * @param array $shops
             * @param SLFW_Loggi_Api $api
             *
             * @var array
             */
            return apply_filters( 'slfw_api_retrieve_all_shops', $shops, $this );
        }

        /**
         * Request a order estimation to API
         * Already considering the limit of 2 packages by requisition.
         *
         * @param string $shopId
         * @param array $pickup
         * @param array $destination
         * @param array $packages
         * @return array
         */
        public function retrieve_order_estimation( $shopId, $pickup, $destination, $packages ) {
            if ( ! $this->can_make_request() || empty( $packages ) ) {
                return array();
            }

            $estimation = array(
                'cost'        => 0,
                'eta'         => 0,
                'packages'    => array(),
                'errors'      => array(),
                'estimations' => array(),
            );

            foreach ( array_chunk( $packages, 2 ) as $group ) {
                $response = $this->request_order_estimation( $shopId, $pickup, $destination, $group );

                if ( empty( $response ) || empty( $response['totalEstimate'] ) ) {
                    return array();
                }

                $estimation['cost'] += (float) $response['totalEstimate']['totalCost'];
                $estimation['eta'] = max( (int) $response['totalEstimate']['totalEta'], $estimation['eta'] );
                $estimation['packages'] = array_merge( $estimation['packages'], $response['ordersEstimate'] );
                $estimation['errors'] = array_merge( $estimation['errors'], $response['packagesWithErrors'] );
                $estimation['estimations'][] = $response;
            }

            /**
             * Filter: 'slfw_api_retrieve_order_estimation'
             *
             * @param array $estimation
             * @param int $shopId
             * @param array $pickup
             * @param array $destination
             * @param array $packages
             * @param SLFW_Loggi_Api $api
             *
             * @var array {
             *   'cost'        => int   $cost,
             *   'packages'    => array $packages,
             *   'errors'      => array $errors,
             *   'estimations' => array $estimations,
             * }
             */
            return apply_filters( 'slfw_api_retrieve_order_estimation', $estimation, $shopId, $pickup, $destination, $packages, $this );
        }

        /**
         * Request a order estimation to API
         * Will not consider the limitation of packages.
         *
         * @see retrieve_order_estimation()
         *
         * @param string $shopId
         * @param array $pickup
         * @param array $destination
         * @param array $packages
         * @return array
         */
        protected function request_order_estimation( $shopId, $pickup, $destination, $packages ) {
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
                        'name'  => 'Customer',
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
         * @param string $name
         * @param array $params
         * @param array $selection
         * @param string $operation
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

            /**
             * Filter: 'slfw_api_request_args'
             *
             * You can change all requests to api.
             *
             * @param array $args
             * @param string $name
             * @param array $params
             * @param array $selection
             * @param string $operation
             * @param SLFW_Loggi_Api $api
             *
             * @var array
             */
            $args = apply_filters( 'slfw_api_request_args', $args, $name, $params, $selection, $operation, $this );

            $response = wp_remote_post( $this->get_url(), $args );

            if ( is_wp_error( $response ) ) {
                $data = array(
                    'request'  => $args,
                    'response' => $response,
                );

                $this->log( '[Loggi Api] "' . $name . '": ' . $response->get_error_message(), $data );
                return false;
            }

            $response = json_decode( $response['body'] ?? array(), true );

            // Check for errors
            if ( ! empty( $response['errors'] ) ) {
                $data = array(
                    'request'  => $args,
                    'response' => $response,
                );

                $this->log( '[Loggi Api] Error: "' . $name . '"', $data );

                return false;
            }

            if ( empty( $response['data'] ) ) {
                $data = array(
                    'request'  => $args,
                    'response' => $response,
                );

                $this->log( '[Loggi Api] Empty data: "' . $name . '"', $data );
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
         * @param  string $operation
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
         * @param  array $selection
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
