<?php
/**
 * Plugin Name: WooCommerce Individually Calculated Shipping Zones
 * Plugin URI: https://github.com/BigTonni
 * Description: WooCommerce Individually Calculated Shipping Zones.
 * Version: 1.0.2
 * Author: Anton Shulga
 * Author URI: https://github.com/BigTonni
 * Text Domain: tsc
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

register_uninstall_hook(__FILE__, array('WooCommerce_Individually_Shipping_Zone', 'uninstall'));

/**
 * Class WooCommerce_Individually_Shipping_Zone.
 *
 * Main WAFS class, add filters and handling all other files.
 *
 * @class       WooCommerce_Individually_Shipping_Zone
 * @version     1.0.0
 * @author      Anton Shulga
 */
class WooCommerce_Individually_Shipping_Zone {


	/**
	 * Version.
	 *
	 * @since 1.0.0
	 * @var string $version Plugin version number.
	 */
	public $version = '1.0.2';


	/**
	 * File.
	 *
	 * @since 1.0.0
	 * @var string $file Main plugin file path.
	 */
	public $file = __FILE__;
        
        /**
	 * Plugin Name.
	 *
	 * @since 1.0.1
	 * @var string $plugin_name.
	 */
	public $plugin_name = 'WooCommerce Individually Calculated Shipping Zones';


	/**
	 * Instance of WooCommerce_Individually_Shipping_Zone.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var object $instance The instance of WISZ.
	 */
	private static $instance = null;


	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		}

		// Check if WooCommerce is active
		if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) :
			if ( ! is_plugin_active_for_network( 'woocommerce/woocommerce.php' ) ) :
				return;
			endif;
		endif;

	}


	/**
	 * Instance.
	 *
	 * An global instance of the class. Used to retrieve the instance
	 * to use on other files/plugins/themes.
	 *
	 * @since 1.0.0
	 *
	 * @return  object  Instance of the class.
	 */
	public static function instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;

	}
        
        /**
	 * Init.
	 *
	 * Initialize plugin parts.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		if ( version_compare( PHP_VERSION, '5.3', 'lt' ) ) {
			return add_action( 'admin_notices', array( $this, 'php_version_notice' ) );
		}
                
                register_activation_hook(__FILE__, array($this, 'activate'));
                
                // Enqueue scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// Add hooks/filters
		$this->hooks();                
                       
                register_deactivation_hook(__FILE__, array($this, 'deactivate'));

		// Load textdomain
		$this->load_textdomain();

		require_once plugin_dir_path( __FILE__ ) . '/includes/helpers.php';
	}
        
        /**
        * Do things on plugin activation.
        */
        public function activate() {
            return true;
        }

        /**
        * Called when the plugin is deactivated.
        *
        * @since 1.0
         */
        public function deactivate() {
            flush_rewrite_rules();
        }

        
        /**
	 * Enqueue scripts.
	 *
	 * Enqueue javascript and stylesheets to the admin area.
	 *
	 * @since 1.0.0
	 */
	public function admin_enqueue_scripts() {
            wp_enqueue_script( 'wisz_admin_js', plugins_url( 'assets/js/admin.js', $this->file ), array('jquery'), $this->version );
        }
        
        /**
	 * Hooks.
	 *
	 * Initialize all class hooks.
	 *
	 * @since 1.0.0
	 */
	public function hooks() {
            
            if(is_admin() ){
                add_filter( 'woocommerce_shipping_settings', array( $this, 'wisz_shipping_settings') );
            }
            
            // Update shipping packages if option "Separate rows" checked in adminpanel
            add_filter('woocommerce_shipping_packages', array( $this, 'wisz_shipping_packages') );
	}
        
        /**
	 * Textdomain.
	 *
	 * Load the textdomain based on WP language.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		// Load textdomain
		load_plugin_textdomain( 'tsc', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}
        
        //Settings
        public function wisz_shipping_settings($args){
            $new_args = array( 
                array(
                    'desc'          => __("Charge shipping for each shipping zone individually", 'tsc'),
                    'id'            => "woocommerce_shipping_individually_zone",
                    'default'       => "no",
                    'type'          => "checkbox",
                    'checkboxgroup' => "start",
                    'autoload'      => false
                )
            );    
            array_splice($args, 3, 0, $new_args);

            return $args;
        }
        
        /**
        * Allow packages to be reorganized after calculating the shipping.
        *
        * @since 1.0.2
        *
        * @param array $packages The array of packages after shipping costs are calculated.
        */
        public function wisz_shipping_packages( $packages ){

                if( get_option( 'woocommerce_shipping_individually_zone' ) == 'yes' ){

                        $shipping_methods = array();

                        $debug_mode = 'yes' === get_option( 'woocommerce_shipping_debug_mode', 'no' );

                        foreach ($packages as $key => $package) {                 

                                // Check if we need to recalculate shipping for this package
                                $package_to_hash = $package;
                                // Remove data objects so hashes are consistent
                                foreach ( $package_to_hash['contents'] as $item_id => $item ) {
                                        unset( $package_to_hash['contents'][ $item_id ]['data'] );
                                }

                                $package_hash = 'wc_ship_' . md5( json_encode( $package_to_hash ) . WC_Cache_Helper::get_transient_version( 'shipping' ) );

                                $session_key  = 'shipping_for_package_' . $key;
                                $stored_rates = WC()->session->get( $session_key );

                                if( ! is_array( $stored_rates ) || $package_hash == $stored_rates['package_hash'] ){

                                        $new_package['rates'] = array();

                                        $shipping_zone_ids = tsc_get_zone_ids_from_packages( $package );

                                        if( !empty($shipping_zone_ids) ){
                                                foreach ($shipping_zone_ids as $k_zone => $matching_zone_id) {
                                                        $zone_id = $matching_zone_id['zone_id'] ? $matching_zone_id['zone_id'] : 0;
                                                        $shipping_zone = new WC_Shipping_Zone( $zone_id );
                                                        $shipping_methods[$zone_id][] = $shipping_zone->get_shipping_methods( true );

                                                        if ( $debug_mode && ! wc_has_notice( 'Customer matched zone "' . $shipping_zone->get_zone_name() . '"' ) && is_cart() ) {
                                                                wc_add_notice( 'Customer matched zone "' . $shipping_zone->get_zone_name() . '"' );
                                                        }
                                                }
                                                foreach ($shipping_methods as $k_out => $arr_shipping_methods) {
                                                        foreach ($arr_shipping_methods[0] as $k_in => $shipping_method) {
                                                                if ( ! $shipping_method->supports( 'shipping-zones' ) || $shipping_method->get_instance_id() ) {                        
                                                                        $new_package['rates'] = $new_package['rates'] + $shipping_method->get_rates_for_package( $package );
                                                                }
                                                        }
                                                }
                                        }

                                        $package['rates'] = !empty($new_package['rates']) ? $new_package['rates'] : $package['rates'];


                                        WC()->session->set( $session_key, array(
                                                'package_hash' => $package_hash,
                                                'rates'        => $package['rates'],
                                        ) );
                                } else {
                                        $package['rates'] = $stored_rates['rates'];
                                }

                                $packages[$key]['rates'] = $package['rates'];
                        }
                }else{
                    //default code... 
                }

                return $packages;
        }
        
        /**
	 * Display PHP 5.3 required notice.
	 *
	 * Display a notice when the required PHP version is not met.
	 *
	 * @since 1.0.0
	 */
	public function php_version_notice() {
		?><div class='updated'>
		<p><?php echo sprintf( __( '%s requires PHP 5.3 or higher and your current PHP version is %s. Please (contact your host to) update your PHP version.', 'tsc' ), $this->plugin_name, PHP_VERSION ); ?></p>
		</div><?php
	}
        
        /**
        * Do things on plugin uninstall.
        */
        public function uninstall() {
            if (!current_user_can('activate_plugins'))
                return;
            check_admin_referer('bulk-plugins');

            if (__FILE__ != WP_UNINSTALL_PLUGIN)
                return;
        }

}

/**
 * The main function responsible for returning the WooCommerce_Individually_Shipping_Zone object.
 *
 * Use this function like you would a global variable, except without needing to declare the global.
 *
 * @since 1.0.0
 *
 * @return  object  WooCommerce_Individually_Shipping_Zone class object.
 */
if ( ! function_exists( 'WISZ' ) ) :

	function WISZ() {

		return WooCommerce_Individually_Shipping_Zone::instance();

	}


endif;

WISZ()->init();