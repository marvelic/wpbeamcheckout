<?php
/**
 * Api class
 *
 * @package Marvelic\BeamCheckout
 */

namespace Marvelic\BeamCheckout\Api;

defined( 'ABSPATH' ) || exit();

/**
 * Class Api
 *
 * @package Marvelic\BeamCheckout
 * @since   1.0.0
 */
class Main {
	/**
	 * APIs
	 *
	 * @var array
	 */
	private array $apis = array(
		'order' => BeamCheckoutPurchaseApi::class, /*
		'refund' => BeamCheckoutRefundPurchaseApi::class, */
	);

	/**
	 * Initiated APIs
	 *
	 * @var array
	 */
	private array $initiated_apis = array();

	/**
	 * Get api by name.
	 *
	 * @param string $api_name Api name to get.
	 *
	 * @return object
	 */
	public function get( string $api_name ): ?object {
		if ( isset( $this->initiated_apis[ $api_name ] ) ) {
			return $this->initiated_apis[ $api_name ];
		}

		if ( isset( $this->apis[ $api_name ] ) ) {
			$this->initiated_apis[ $api_name ] = new $this->apis[ $api_name ]();

			return $this->initiated_apis[ $api_name ];
		}

		return null;
	}
}
