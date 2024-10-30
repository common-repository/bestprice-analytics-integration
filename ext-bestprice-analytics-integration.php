<?php
/**
 * Plugin Name: BestPrice 360º
 * Description: Allows BestPrice 360º tracking code to be inserted into WooCommerce store pages.
 * Author: BestPrice
 * Author URI: https://www.bestprice.gr
 * Version: 1.1.3
 * WC requires at least: 3.8
 * WC tested up to: 8.8.2
 * License: GPLv2 or later
 * Text Domain: ext-bestprice-analytics-integration
 * Domain Path: languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Bestprice_Analytics_Integration' ) ) {

	/**
	 * WooCommerce BestPrice 360º main class.
	 */
	class WC_Bestprice_Analytics_Integration {

		/**
		 * Plugin version.
		 *
		 * @var string
		 */
		const VERSION = '1.1.3';

		/**
		 * Instance of this class.
		 *
		 * @var object
		 */
		protected static $instance = null;

		/**
		 * Initialize the plugin.
		 */
		public function __construct() {
			if ( ! class_exists( 'WooCommerce' ) ) {
				return;
			}

			// Load plugin text domain
			/* add_action( 'init', array( $this, 'show_ba_pro_notices' ) ); */

			load_plugin_textdomain( 'ext-bestprice-analytics-integration', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

			// Checks with WooCommerce is installed.
			if ( class_exists( 'WC_Integration' ) && defined( 'WOOCOMMERCE_VERSION' ) && version_compare( WOOCOMMERCE_VERSION, '2.1-beta-1', '>=' ) ) {
				include_once 'includes/class-wc-bestprice-analytics.php';

				// Register the integration.
				add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
			} else {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			}

			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_links' ) );

			add_action( 'before_woocommerce_init', function() {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
				}
			});
		}

		public function plugin_links( $links ) {
			$settings_url = add_query_arg(
				array(
					'page' => 'wc-settings',
					'tab' => 'integration',
				),
				admin_url( 'admin.php' )
			);

			$plugin_links = array(
				'<a href="' . esc_url( $settings_url ) . '">' . __( 'Settings', 'ext-bestprice-analytics-integration' ) . '</a>',
			);

			return array_merge( $plugin_links, $links );
		}

		/**
		 * Return an instance of this class.
		 *
		 * @return object A single instance of this class.
		 */
		public static function get_instance() {
			// If the single instance hasn't been set, set it now.
			if ( null == self::$instance ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		/**
		 * WooCommerce fallback notice.
		 *
		 * @return string
		 */
		public function woocommerce_missing_notice() {
			echo '<div class="error"><p>' . sprintf( __( 'WooCommerce BestPrice 360º depends on the last version of %s to work!', 'ext-bestprice-analytics-integration' ), '<a href="http://www.woothemes.com/woocommerce/" target="_blank">' . __( 'WooCommerce', 'ext-bestprice-analytics-integration' ) . '</a>' ) . '</p></div>';
		}

		/**
		 * Add a new integration to WooCommerce.
		 *
		 * @param  array $integrations WooCommerce integrations.
		 *
		 * @return array               BestPrice 360º integration.
		 */
		public function add_integration( $integrations ) {
			$integrations[] = 'WC_Bestprice_Analytics';

			return $integrations;
		}

	}

	add_action( 'plugins_loaded', array( 'WC_Bestprice_Analytics_Integration', 'get_instance' ), 0 );

}
