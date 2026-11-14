<?php
/**
 * Actions class
 *
 * @package Marvelic\GatewayTemplate
 */

namespace Marvelic\BeamCheckout\Actions;

defined( 'ABSPATH' ) || exit();

/**
 * Class Actions
 *
 * @package Marvelic\GatewayTemplate
 * @since   1.0.0
 */
class Main {
    /**
     * Actions constructor.
     */
    public function __construct() {
        new Order();
    }
}

