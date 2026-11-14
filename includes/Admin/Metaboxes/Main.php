<?php
/**
 * Admin Metaboxes class
 *
 * @package Marvelic\GatewayTemplate
 */

namespace Marvelic\BeamCheckout\Admin\Metaboxes;

defined( 'ABSPATH' ) || exit();

/**
 * Class Metaboxes
 *
 * @package Marvelic\GatewayTemplate
 * @since   1.0.0
 */
class Main {
	/**
	 * Main constructor.
	 */
	public function __construct() {
		new OrderMetaBox();
	}
}
