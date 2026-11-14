<?php
/**
 * Ajax class
 *
 * @package Marvelic\BeamCheckout
 */

namespace Marvelic\BeamCheckout\Ajax;

defined( 'ABSPATH' ) || exit();

/**
 * Class Ajax
 *
 * @package Marvelic\BeamCheckout
 * @since   1.0.0
 */
class Main {
	/**
	 * Ajax constructor.
	 */
	public function __construct() {
		new Order();
	}
}
