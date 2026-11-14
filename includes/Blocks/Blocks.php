<?php

namespace Marvelic\BeamCheckout\Blocks;

class BeamCheckoutBlocks
{
  public function __construct()
  {
    // Hook the custom function to the 'woocommerce_blocks_loaded' action
    add_action(
      'woocommerce_blocks_loaded',
      array($this, 'register_beam_gateway_payment_method_type')
    );
  }

  public function register_beam_gateway_payment_method_type()
  {
    // Check if the required class exists
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
      return;
    }

    // Include the custom Blocks Checkout class
    require_once plugin_dir_path(__FILE__) . '/BlocksGateway.php';

    // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
    add_action(
      'woocommerce_blocks_payment_method_type_registration',
      function (\Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
        // Register an instance of My_Custom_Gateway_Blocks
        $payment_method_registry->register(new \Marvelic\BeamCheckout\Blocks\Beam_Gateway_Blocks);
      }
    );
  }
}

new BeamCheckoutBlocks();
