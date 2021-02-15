<?php

/**
 * Plugin Name:     Shipping Loggi for WooCommerce
 * Plugin URI:      https://github.com/mariovalney/shipping-loggi-for-woocommerce
 * Description:     Shipping via motorcycle courier with Loggi
 * Version:         1.0.0
 * License:         GPLv2 or later
 * Author:          Mário Valney
 * Author URI:      https://mariovalney.com
 * Text Domain:     shipping-loggi-for-woocommerce
 * Domain Path:     /languages
 *
 * WC requires at least: 4.5
 * WC tested up to: 5.0
 *
 * @package         Shipping_Loggi_For_WooCommerce
 * @since           1.0.0
 *
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 */

// If this file is called directly, call the cops.
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

if ( ! class_exists( 'Shipping_Loggi_For_WooCommerce' ) ) {

    class Shipping_Loggi_For_WooCommerce {

        /**
         * The single instance of the class.
         *
         * @var WooCommerce
         * @since 1.0.0
         */
        protected static $instance = null;

        /**
         * The array of actions registered with WordPress.
         *
         * @since    1.0.0
         * @access   protected
         * @var      array    $actions    The actions registered with WordPress to fire when the plugin loads.
         */
        protected $actions = array();

        /**
         * The array of filters registered with WordPress.
         *
         * @since    1.0.0
         * @access   protected
         * @var      array    $filters    The filters registered with WordPress to fire when the plugin loads.
         */
        protected $filters = array();

        /**
         * The array of modules of plugin.
         *
         * @since    1.0.0
         * @access   protected
         * @var      array    $modules    The modules to be used in this plugin.
         */
        protected $modules = array();

        /**
         * Main Instance.
         * You know singleton...
         *
         * @since 1.0.0
         * @return Shipping_Loggi_For_WooCommerce
         */
        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Define the core functionality of the plugin.
         *
         * @since    1.0.0
         */
        public function __construct() {
            $this->define_hooks();
            $this->add_modules();
        }

        /**
         * Define things to run when activate plugin
         *
         * @since    1.0.0
         */
        public function on_activation() {
            do_action( 'slfw_on_core_activation' );

            wp_cache_flush();
            flush_rewrite_rules();
        }

        /**
         * Register the hooks for Core
         *
         * @since    1.0.0
         * @access   private
         */
        private function define_hooks() {
            // Internationalization
            $this->add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );

            // Activation Hook
            register_activation_hook( __FILE__, array( $this, 'on_activation' ) );
        }

        /**
         * Load all the plugins modules.
         *
         * @since    1.0.0
         * @access   private
         */
        private function add_modules() {
            $modules = array();

            $path = plugin_dir_path( __FILE__ ) . 'modules' . DIRECTORY_SEPARATOR;
            if ( ! is_dir( $path ) ) {
                return;
            }

            $results = scandir( $path );

            foreach ( $results as $result ) {
                if ( $result[0] === '.' ) {
                    continue;
                }

                if ( ! is_dir( $path . $result ) ) {
                    continue;
                }

                $classfile = $path . $result . DIRECTORY_SEPARATOR . 'class-module-' . $result . '.php';
                if ( ! file_exists( $classfile ) ) {
                    continue;
                }

                $classname = str_replace( '-', ' ', $result );
                $classname = ucfirst( $classname );
                $classname = str_replace( ' ', '_', $classname );
                $classname = 'SLFW_Module_' . $classname;

                $module_data  = get_file_data( $classfile, array( 'dependencies' => 'Depends' ) );
                $dependencies = $module_data['dependencies'];

                if ( ! empty( $dependencies ) ) {
                    $dependencies = str_replace( ' ', '', $dependencies );
                    $dependencies = explode( ',', $dependencies );
                }

                $modules[ $result ] = array( $classfile, $classname, $dependencies );
            }

            $this->load_modules_by_dependence( $modules );
        }

        /**
         * Load modules using dependencies
         *
         * @since    1.0.0
         * @access   private
         */
        private function load_modules_by_dependence( $modules ) {
            $not_loaded_modules = array();

            foreach ( $modules as $module => $module_data ) {
                if ( ! empty( $module_data[2] ) ) {
                    $loaded = array_keys( $this->modules );

                    if ( ! empty( array_diff( $module_data[2], $loaded ) ) ) {
                        $not_loaded_modules[ $module ] = $module_data;
                        continue;
                    }
                }

                require_once $module_data[0];

                if ( ! class_exists( $module_data[1] ) ) {
                    continue;
                }

                $this->modules[ $module ]       = new $module_data[1]();
                $this->modules[ $module ]->core = $this;
            }

            $loaded = array_keys( $this->modules );

            foreach ( $not_loaded_modules as $module_data ) {
                // Still have dependencies
                if ( ! empty( array_diff( $module_data[2], $loaded ) ) ) {
                    continue;
                }

                // At least, one module has not dependencies: we can retry
                $this->load_modules_by_dependence( $not_loaded_modules );
                break;
            }
        }

        /**
         * A utility function that is used to register the actions and hooks into a single
         * collection.
         *
         * @since    1.0.0
         * @access   private
         * @param    array      $hooks              The collection of hooks that is being registered (that is, actions or filters).
         * @param    string     $hook               The name of the WordPress filter that is being registered.
         * @param    string     $callback           The callback function or a array( $obj, 'method' ) to public method of a class.
         * @param    int        $priority           The priority at which the function should be fired.
         * @param    int        $accepted_args      The number of arguments that should be passed to the $callback.
         * @return   array                          The collection of actions and filters registered with WordPress.
         */
        private function add_hook( $hooks, $hook, $callback, $priority, $accepted_args ) {
            $hooks[] = array(
                'hook'          => $hook,
                'callback'      => $callback,
                'priority'      => $priority,
                'accepted_args' => $accepted_args,
            );

            return $hooks;
        }

        /**
         * Keep module objects
         *
         * @since    1.0.0
         * @access   public
         */
        public function get_module( $module_name ) {
            if ( empty( $this->modules[ $module_name ] ) ) {
                return false;
            }

            return $this->modules[ $module_name ];
        }

        /**
         * Add a new action to the collection to be registered with WordPress.
         *
         * @since    1.0.0
         * @param    string     $hook             The name of the WordPress action that is being registered.
         * @param    string     $callback         The callback function or a array( $obj, 'method' ) to public method of a class.
         * @param    int        $priority         Optional. he priority at which the function should be fired. Default is 10.
         * @param    int        $accepted_args    Optional. The number of arguments that should be passed to the $callback. Default is 1.
         */
        public function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
            $this->actions = $this->add_hook( $this->actions, $hook, $callback, $priority, $accepted_args );
        }

        /**
         * Add a new filter to the collection to be registered with WordPress.
         *
         * @since    1.0.0
         * @param    string     $hook             The name of the WordPress filter that is being registered.
         * @param    string     $callback         The callback function or a array( $obj, 'method' ) to public method of a class.
         * @param    int        $priority         Optional. he priority at which the function should be fired. Default is 10.
         * @param    int        $accepted_args    Optional. The number of arguments that should be passed to the $callback. Default is 1
         */
        public function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
            $this->filters = $this->add_hook( $this->filters, $hook, $callback, $priority, $accepted_args );
        }

        /**
         * Define the locale for this plugin for internationalization.
         *
         *
         * @since    1.0.0
         * @access   private
         */
        public function load_plugin_textdomain() {
            load_plugin_textdomain( 'shipping-loggi-for-woocommerce', false, basename( dirname( __FILE__ ) ) . '/languages/' );
        }

        /**
         * Run the plugin.
         *
         * @since    1.0.0
         */
        public function run() {
            // Definitions to plugin
            define( 'SLFW_VERSION', '1.0.0' );
            define( 'SLFW_PLUGIN_FILE', __FILE__ );
            define( 'SLFW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
            define( 'SLFW_PLUGIN_PATH', WP_PLUGIN_DIR . '/' . dirname( SLFW_PLUGIN_BASENAME ) );
            define( 'SLFW_PLUGIN_DIR', dirname( SLFW_PLUGIN_BASENAME ) );
            define( 'SLFW_PLUGIN_URL', plugins_url( '', __FILE__ ) );

            // Running Modules
            foreach ( $this->modules as $module_slug => $module ) {
                // Run Module: before anything
                if ( method_exists( $module, 'run' ) ) {
                    $module->run();
                }

                // Include Files if Configured
                if ( property_exists( $module, 'includes' ) ) {
                    foreach ( (array) $module->includes as $class ) {
                        $file = SLFW_PLUGIN_PATH . '/modules/' . $module_slug . '/includes/' . $class . '.php';
                        if ( file_exists( $file ) ) {
                            require_once $file;
                        }
                    }
                }
            }

            // After Run for everyone
            foreach ( $this->modules as $module_slug => $module ) {
                if ( method_exists( $module, 'after_run' ) ) {
                    $module->after_run();
                }
            }

            // Define Hooks for everyone
            foreach ( $this->modules as $module_slug => $module ) {
                if ( method_exists( $module, 'define_hooks' ) ) {
                    $module->define_hooks();
                }
            }

            // Running Filters
            foreach ( $this->filters as $hook ) {
                add_filter( $hook['hook'], $hook['callback'], $hook['priority'], $hook['accepted_args'] );
            }

            // Running Actions
            foreach ( $this->actions as $hook ) {
                add_action( $hook['hook'], $hook['callback'], $hook['priority'], $hook['accepted_args'] );
            }
        }
    }
}

/**
 * Making things happening
 */
$core = Shipping_Loggi_For_WooCommerce::instance();
add_action( 'plugins_loaded', array( $core, 'run' ), 99 );

/**
 * A global function like WC()
 */
function Shipping_Loggi_For_WooCommerce() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
    $modules = Shipping_Loggi_For_WooCommerce::instance()->get_modules();
    if ( empty( $modules ) ) {
        error_log( 'You cannot call before run method' );
    }

    return $modules;
}
