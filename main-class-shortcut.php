<?php

/**
 * Main class shortcut
 *
 * @package Marvelic\BeamCheckout
 */

use Marvelic\BeamCheckout\Main;

/**
 * Shortcut for getting Main class instance
 *
 * @return Main
 */
function beamcheckout(): Main
{
	return Main::get_instance();
}
