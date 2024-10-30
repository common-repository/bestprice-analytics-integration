<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * BestPrice 360º
 *
 * Allows tracking code to be inserted into store pages.
 *
 * @class   WC_Bestprice_Analytics
 * @extends WC_Integration
 */
class WC_Bestprice_Analytics extends WC_Integration
{

	/**
	 * Init and hook in the integration.
	 *
	 * @return void
	 */

	public $dismissed_info_banner;
	public $ba_id;
	public $ba_feed_id;
	public $ba_badge_status;
	public $ba_merch_id;

	public function __construct()
	{
		$this->id                    = 'bestprice_analytics';
		$this->method_title          = __('BestPrice 360º', 'ext-bestprice-analytics-integration');
		$this->method_description    = __('BestPrice 360º is a service offered by BestPrice that generates statistics about the visitors to a website.', 'ext-bestprice-analytics-integration');
		$this->dismissed_info_banner = get_option('woocommerce_dismissed_info_banner');

		// Load the settings
		$this->init_form_fields();
		$this->init_settings();
		$constructor = $this->init_options();

		// Display an info banner on how to configure WooCommerce
		if (is_admin()) {
			include_once('class-wc-bestprice-analytics-info-banner.php');
			WC_Bestprice_Analytics_Info_Banner::get_instance($this->dismissed_info_banner, $this->ba_id);
		}

		// Admin Options
		add_action('woocommerce_update_options_integration_bestprice_analytics', array($this, 'process_admin_options'));
		add_action('woocommerce_update_options_integration_bestprice_analytics', array($this, 'show_options_info'));
		add_action('admin_enqueue_scripts', array($this, 'load_admin_assets'));

		// Tracking code
		add_action('wp_head', array($this, 'tracking_code_display'), 999999);

		// Product Badge Above Add to Cart
		add_action('woocommerce_single_product_summary', array($this, 'bestprice_show_product_badge'), 20);
	}

	/**
	 * Loads all of our options for this plugin
	 * @return array An array of options that can be passed to other classes
	 */
	public function init_options()
	{
		$options = array(
			'ba_id',
			'ba_feed_id',
			'ba_badge_status',
			'ba_merch_id'
		);

		$constructor = array();
		foreach ($options as $option) {
			$constructor[$option] = $this->$option = $this->get_option($option);
		}

		return $constructor;
	}

	/**
	 * Tells WooCommerce which settings to display under the "integration" tab
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'ba_id' => array(
				'title'       => __('BestPrice 360º ID', 'ext-bestprice-analytics-integration'),
				'description' => __(
					'Enter your BestPrice 360º ID',
					'ext-bestprice-analytics-integration'
				),
				'type'        => 'text',
				'default'     => get_option('woocommerce_ba_id') // Backwards compat
			),
			'ba_feed_id' => array(
				'title'       => __('Feed Product ID', 'ext-bestprice-analytics-integration'),
				'type'        => 'select',
				'description' => __(
					'Select the feed ID that should be sent to analytics.',
					'ext-bestprice-analytics-integration'
				),
				'options'     => array('product_id' => 'Product ID', 'product_sku' => 'Product SKU'),
				'default'     => 'product_id',
			),
			'ba_badge_status' => array(
				'title'       => __('BestPrice Product Badge', 'wc-bestprice-analytics-badge'),
				'type'        => 'select',
				'description' => __(
					'Enable BestPrice Product Badge',
					'ext-bestprice-analytics-integration'
				),
				'options'     => array('enable' => __('Enable', 'ext-bestprice-analytics-integration'), 'disable' => __('Disable', 'ext-bestprice-analytics-integration')),
				'default'     => 'disable',
			),
			'ba_merch_id' => array(
				'title'       => __('BestPrice Merchant ID', 'ext-bestprice-analytics-integration-merchant'),
				'description' => __(
					'Enter your BestPrice Merchant ID',
					'ext-bestprice-analytics-integration'
				),
				'type'        => 'text',
				'default'     => get_option('woocommerce_bmerch_id') // Backwards compat
			),
		);
	}

	/**
	 * Shows some additional help text after saving the BestPrice 360º settings
	 */
	function show_options_info()
	{
		$this->method_description .= "<div class='notice notice-info'><p>" . __('Please allow BestPrice 360º 24 hours to start displaying results.', 'ext-bestprice-analytics-integration') . "</p></div>";

		if (isset($_REQUEST['woocommerce_bestprice_analytics_ba_ecommerce_tracking_enabled']) && true === (bool) $_REQUEST['woocommerce_bestprice_analytics_ba_ecommerce_tracking_enabled']) {
			$this->method_description .= "<div class='notice notice-info'><p>" . __('Please note, for transaction tracking to work properly, you will need to use a payment gateway that redirects the customer back to a WooCommerce order received/thank you page.', 'ext-bestprice-analytics-integration') . "</div>";
		}
	}

	/**
	 *
	 */
	function load_admin_assets()
	{
		$screen = get_current_screen();
		if ('woocommerce_page_wc-settings' !== $screen->id) {
			return;
		}

		if (empty($_GET['tab'])) {
			return;
		}

		if ('integration' !== $_GET['tab']) {
			return;
		}

		wp_enqueue_script('wc-bestprice-analytics-admin-enhanced-settings', plugins_url('/assets/js/admin-enhanced-settings.js', dirname(__FILE__)));
	}

	/**
	 * Display the tracking codes
	 * Acts as a controller to figure out which code to display
	 */
	public function tracking_code_display()
	{
		global $wp;
		$display_ecommerce_tracking = false;

		// Check if is order received page and stop when the products and not tracked
		if (is_order_received_page()) {
			$order_id = isset($wp->query_vars['order-received']) ? $wp->query_vars['order-received'] : 0;
			if (0 < $order_id) {
				$display_ecommerce_tracking = true;
				echo $this->get_standard_tracking_code_cart_page();
				echo $this->get_ecommerce_tracking_code($order_id);
			}
		}

		if (is_woocommerce() || is_cart() || (is_checkout() && !$display_ecommerce_tracking)) {
			$display_ecommerce_tracking = true;
			echo $this->get_standard_tracking_code();
		}

		if (!$display_ecommerce_tracking) {
			echo $this->get_standard_tracking_code();
		}

		// Check if product page and get product id
		// if ( is_product() && ($this->ba_badge_status == 'enable') ) {
		// 	$product = wc_get_product();
		// 	$product_id = $product->get_id();

		// 	echo $this->get_bestprice_product_badge( $product_id );
		// }
	}

	/**
	 * BestPrice Product Badge
	 * 
	 * @param int $product_id
	 * 
	 */
	protected function get_bestprice_product_badge($product_id)
	{
		return "<!-- BestPrice Product Badge start -->
		
		<script data-mid='" . $this->ba_merch_id . "' data-pid='" . $product_id . "' src='https://scripts.bestprice.gr/pbadge.js' async='true'></script>
		
		<noscript><a href='https://www.bestprice.gr'>BestPrice.gr</a></noscript>
		<!-- BestPrice Product Badge end -->
		";
	}

	public function bestprice_show_product_badge()
	{

		// Check if product page and get product id
		if (is_product() && ($this->ba_badge_status == 'enable')) {
			$product = wc_get_product();
			$product_id = $product->get_id();

			echo $this->get_bestprice_product_badge($product_id);
		}
	}

	/**
	 * Standard BestPrice 360º tracking
	 */
	protected function get_standard_tracking_code()
	{
		return "<!-- BestPrice 360º WooCommerce start (" . WC_Bestprice_Analytics_Integration::VERSION .") -->
		<script type='text/javascript'>
			(function (a, b, c, d, s) {a.__bp360 = c;a[c] = a[c] || function (){(a[c].q = a[c].q || []).push(arguments);};
			s = b.createElement('script'); s.async = true; s.src = d; (b.body || b.head).appendChild(s);})
			(window, document, 'bp', 'https://360.bestprice.gr/360.js');
			
			bp('connect', '" . $this->ba_id . "');
			bp('native', true);
		</script>
		<!-- BestPrice 360º WooCommerce end -->
		";
	}

	/**
	 * Standard BestPrice 360º tracking
	 */
	protected function get_standard_tracking_code_cart_page()
	{
		return "<!-- BestPrice 360º WooCommerce start (" . WC_Bestprice_Analytics_Integration::VERSION .") -->
		<script type='text/javascript' data-cart>
			(function (a, b, c, d, s) {a.__bp360 = c;a[c] = a[c] || function (){(a[c].q = a[c].q || []).push(arguments);};
			s = b.createElement('script'); s.async = true; s.src = d; (b.body || b.head).appendChild(s);})
			(window, document, 'bp', 'https://360.bestprice.gr/360.js');
			
			bp('connect', '" . $this->ba_id . "');
			bp('native', true);
		</script>
		<!-- BestPrice 360º WooCommerce end -->
		";
	}

	/**
	 * eCommerce tracking
	 *
	 * @param int $order_id
	 */
	protected function get_ecommerce_tracking_code($order_id)
	{
		// Get the order and output tracking code.
		$order = wc_get_order($order_id);

		// Make sure we have a valid order object.
		if (!$order) {
			return '';
		}

		return "<!-- BestPrice 360º Order Products Script start -->
		<script type='text/javascript'>
		" . $this->add_transaction($order) . "	
		</script>
		<!-- BestPrice 360º Order Products Script end -->
		";
	}

	function add_transaction($order)
	{
		$code = "bp('addOrder', {
			'orderId': '" . esc_js($order->get_order_number()) . "',         // Transaction ID. Required
			'revenue': '" . esc_js($order->get_total()) . "',           // Grand Total
			'shipping': '" . esc_js($order->get_total_shipping()) . "', // Shipping
			'tax': '" . esc_js($order->get_total_tax()) . "',           // Tax
			'method':'" . esc_js($order->get_payment_method_title()) . "', // Payment Method
			'currency': '" . esc_js($order->get_currency()) . "',       // Currency
		});";

		// Order items
		if ($order->get_items()) {
			foreach ($order->get_items() as $item) {
				$code .= self::add_items($order, $item);
			}
		}

		return $code;
	}

	function add_items($order, $item)
	{
		$_product = version_compare(WC_VERSION, '3.0', '<') ? $order->get_product_from_item($item) : $item->get_product();

		$code = "bp('addProduct', {";
		$code .= "'orderId': '" . esc_js($order->get_order_number()) . "',";

		$product_id = "";

		if ($this->ba_feed_id == "product_id") {
			$_product_id = $_product->get_id();
		} elseif ($this->ba_feed_id == "product_sku") {
			$_product_id = $_product->get_sku() ? $_product->get_sku() : $_product->get_id();
		}

		$code .= "'productId': '" . esc_js($_product_id) . "',";
		$code .= "'title': '" . esc_js($item['name']) . "',";
		$code .= "'price': '" . esc_js($order->get_item_total($item)) . "',";
		$code .= "'quantity': '" . esc_js($item['qty']) . "'";
		$code .= "});";

		return $code;
	}
}
