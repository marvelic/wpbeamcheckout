<?php
/**
 * Frontend Account class
 *
 * @package Marvelic\GatewayTemplate
 */

namespace Marvelic\BeamCheckout\Frontend;

defined( 'ABSPATH' ) || exit();

/**
 * Class Checkout
 *
 * @package Marvelic\GatewayTemplate
 * @since   1.0.0
 */
class Checkout {
	/**
	 * Enqueue js and css on checkout page
	 */
	public static function enqueue_assets() {
		wp_enqueue_style(
			beamcheckout()->get_option( 'id' ) . '/css/checkout',
			beamcheckout()->get_option( 'assets_url' ) . 'css/checkout.min.css',
			array(),
			beamcheckout()->get_option( 'version' )
		);

		wp_enqueue_script(
			beamcheckout()->get_option( 'id' ) . '/js/checkout',
			beamcheckout()->get_option( 'assets_url' ) . 'js/checkout.min.js',
			array( 'jquery' ),
			beamcheckout()->get_option( 'version' ),
			true
		);

		wp_localize_script(
			beamcheckout()->get_option( 'id' ) . '/js/checkout',
			'gatewayTemplateData',
			self::get_js_data()
		);

		wp_set_script_translations(
			beamcheckout()->get_option( 'id' ) . '/js/checkout',
			'woocommerce-gateway-gateway-template'
		);
	}

	/**
	 * Get js localize object
	 *
	 * @return array[]
	 */
	public static function get_js_data(): array {
		return array(
			'gateway_id' => beamcheckout()->get_option( 'gateway_id' ),
			'is_admin'   => current_user_can( 'manage_woocommerce' ),
			'urls'       => array(),
		);
	}

	
}
