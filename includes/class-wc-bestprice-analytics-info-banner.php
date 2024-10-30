<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Bestprice_Analytics_Info_Banner class
 *
 * Displays a message after install (if not dismissed and BA is not already configured) about how to configure the analytics plugin
 */
class WC_Bestprice_Analytics_Info_Banner {

	/** @var object Class Instance */
	private static $instance;

	/** @var boolean If the banner has been dismissed */
	private $is_dismissed = false;

	/**
	 * Get the class instance
	 */
	public static function get_instance( $dismissed = false, $ba_id = '' ) {
		return null === self::$instance ? ( self::$instance = new self( $dismissed, $ba_id ) ) : self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct( $dismissed = false, $ba_id = '' ) {
		$this->is_dismissed = (bool) $dismissed;
		if ( ! empty( $ba_id ) ) {
			$this->is_dismissed = true;
		}

		// Don't bother setting anything else up if we are not going to show the notice
		if ( true === $this->is_dismissed ) {
			return;
		}

		add_action( 'admin_notices', array( $this, 'banner' ) );
		add_action( 'admin_init', array( $this, 'dismiss_banner' ) );
	}

	/**
	 * Displays a info banner on WooCommerce settings pages
	 */
	public function banner() {
		$screen = get_current_screen();

		if ( ! in_array( $screen->base, array( 'ext_page_wc-settings', 'plugins' ) ) || $screen->is_network || $screen->action ) {
			return;
		}

		$integration_url = esc_url( admin_url( 'admin.php?page=wc-settings&tab=integration&section=bestprice_analytics' ) );
		$dismiss_url = $this->dismiss_url();

		$heading = __( 'Bestprice 360 &amp; WooCommerce', 'ext-bestprice-analytics-integration' );
		$configure = sprintf( __( '<a href="%s">Connect WooCommerce to BestPrice 360º</a> to finish setting up this integration.', 'ext-bestprice-analytics-integration' ), $integration_url );

		// Display the message..
		echo '<div class="updated fade"><p><strong>' . $heading . '</strong> ';
		echo '<a href="' . esc_url( $dismiss_url ) . '" title="' . __( 'Dismiss this notice.', 'ext-bestprice-analytics-integration' ) . '"> ' . __( '(Dismiss)', 'ext-bestprice-analytics-integration' ) . '</a>';
		echo '<p>' . $configure . "</p></div>\n";
	}

	/**
	 * Returns the url that the user clicks to remove the info banner
	 * @return (string)
	 */
	function dismiss_url() {
		$url = admin_url( 'admin.php' );

		$url = add_query_arg( array(
			'page'      => 'wc-settings',
			'tab'       => 'integration',
			'wc-notice' => 'dismiss-info-banner',
		), $url );

		return wp_nonce_url( $url, 'ext_info_banner_dismiss' );
	}

	/**
	 * Handles the dismiss action so that the banner can be permanently hidden
	 */
	function dismiss_banner() {
		if ( ! isset( $_GET['wc-notice'] ) ) {
			return;
		}

		if ( 'dismiss-info-banner' !== $_GET['wc-notice'] ) {
			return;
		}

		if ( ! check_admin_referer( 'ext_info_banner_dismiss' ) ) {
			return;
		}

		update_option( 'ext_dismissed_info_banner', true );

		if ( wp_get_referer() ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=integration' ) );
		}
	}

}
