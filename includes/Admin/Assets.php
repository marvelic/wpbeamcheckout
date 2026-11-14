<?php
/**
 * Admin assets class
 *
 * @package Marvelic\BeamCheckout
 */

namespace Marvelic\BeamCheckout\Admin;

defined( 'ABSPATH' ) || exit();

/**
 * Class Assets
 *
 * @package Marvelic\BeamCheckout
 * @since   1.0.0
 */
class Assets {
	/**
	 * Assets constructor.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Enqueue plugin admin scripts
	 */
	public function enqueue_admin_scripts() {
		wp_enqueue_style(
			beamcheckout()->get_option( 'id' ) . '/css/admin',
			beamcheckout()->get_option( 'assets_url' ) . 'css/admin.min.css',
			array(),
			beamcheckout()->get_option( 'version' )
		);

		wp_enqueue_script(
			beamcheckout()->get_option( 'id' ) . '/js/admin',
			beamcheckout()->get_option( 'assets_url' ) . 'js/admin.min.js',
			array( 'jquery', 'jquery-blockui' ),
			beamcheckout()->get_option( 'version' ),
			true
		);

		wp_localize_script(
			beamcheckout()->get_option( 'id' ) . '/js/admin',
			'beamCheckoutAdminData',
			array(
				'urls' => array(),
				'i18n' => array(
					'request_error' => __( 'Request Error. Try Again.', 'woocommerce-gateway-beamcheckout' ),
				),
			)
		);

		$this->enqueue_gateway_settings_script();
	}

	/**
	 * Enqueue the live/test key switching script on the gateway settings page only
	 */
	private function enqueue_gateway_settings_script() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';

		if ( strtolower( $section ) !== strtolower( beamcheckout()->get_option( 'id' ) ) ) {
			return;
		}

		$handle = beamcheckout()->get_option( 'id' ) . '/js/gateway-settings';

		wp_enqueue_script(
			$handle,
			beamcheckout()->get_option( 'assets_url' ) . 'js/gateway-settings.js',
			array( 'jquery' ),
			// Use the file time so a changed script is never served from a stale browser or page-cache copy.
			beamcheckout()->get_option( 'version' ) . '.' . filemtime( beamcheckout()->get_option( 'plugin_path' ) . 'assets/dist/js/gateway-settings.js' ),
			true
		);

		wp_localize_script(
			$handle,
			'beamCheckoutSettings',
			array(
				'id'             => beamcheckout()->get_option( 'id' ),
				'testTitle'  => __( 'Test mode is on.', 'woocommerce-gateway-beamcheckout' ),
				'testNotice' => __( 'Payments made in this mode are not real and no money will be collected. Test mode only works once Beam has opened a test account for your merchant, so please contact Beam to request one before using it. Switch back to Live to accept real payments.', 'woocommerce-gateway-beamcheckout' ),
				'liveNotice' => __( 'Live mode is on. Your store accepts real payments using your live API Key and HMAC Key.', 'woocommerce-gateway-beamcheckout' ),
			)
		);
	}
}
