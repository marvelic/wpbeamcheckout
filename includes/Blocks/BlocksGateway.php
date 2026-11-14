<?php

namespace Marvelic\BeamCheckout\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Marvelic\BeamCheckout\Gateway;

final class Beam_Gateway_Blocks extends AbstractPaymentMethodType {

    private $gateway;
    protected $name = 'woocommerce-gateway-beamcheckout';// your payment gateway name

    public function initialize() {
        $this->settings = get_option( 'woocommerce-gateway-beamcheckout_settings', [] );        
        $this->gateway = new Gateway();
    }

    public function is_active() {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles() {

        wp_register_script(
            'woocommerce-gateway-beamcheckout-blocks-integration',
            plugin_dir_url(__FILE__) . 'checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );
        if( function_exists( 'wp_set_script_translations' ) ) {            
            wp_set_script_translations( 'woocommerce-gateway-beamcheckout-blocks-integration');            
        }
        return [ 'woocommerce-gateway-beamcheckout-blocks-integration' ];
    }

    public function get_payment_method_data() {
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
            'icon' => \beamcheckout()->get_option('assets_url') . 'images/beamLogo.png'
        ];
    }

}
?>