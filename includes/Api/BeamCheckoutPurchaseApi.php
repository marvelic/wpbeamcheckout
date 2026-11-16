<?php
/**
 * Api class
 *
 * @package Marvelic\BeamCheckout
 */

namespace Marvelic\BeamCheckout\Api;

use Exception;
use Marvelic\BeamCheckout\Entities\BeamCheckoutEntity;

defined('ABSPATH') || exit();

/**
 * Base API class
 *
 * @package Marvelic\BeamCheckout
 * @since   1.0.0
 */
class BeamCheckoutPurchaseApi extends Base
{

    /**
     * @param BeamCheckoutEntity $order
     * @return string
     * @throws Exception
     */
    public function create(BeamCheckoutEntity $order): string
    {

        $response = $this->request('purchases/' . beamcheckout()->get_option('merchant_id'), 'POST', $order->get_beam_checkout_order());

        if (!isset($response['paymentLink']) && !isset($response['purchaseId'])) {
            throw new Exception(__('Not paymentLink Or purchaseId', 'woocommerce-gateway-beamcheckout'));
        }

        $order->set_purchase_id(sanitize_text_field(wp_slash($response['purchaseId'])));
        return esc_url($response['paymentLink']);

    }


    /**
     * Read the purchase from Beam and let API errors bubble up.
     *
     * The exception code is the HTTP status (0 when there was no response), so the
     * caller can back off on 429 / 5xx / timeouts as Beam's docs require.
     *
     * @param BeamCheckoutEntity $order
     * @return array
     * @throws Exception
     */
    public function fetch(BeamCheckoutEntity $order): array
    {
        if (!$purchase_id = $order->get_purchase_id()) {
            throw new Exception('No purchase id', 404);
        }

        $merchant_id = beamcheckout()->get_option('merchant_id');

        return $this->request("purchases/{$merchant_id}/{$purchase_id}");
    }

    /**
     * @param BeamCheckoutEntity $order
     * @return array|false
     * @throws Exception
     */
    public function get_info(BeamCheckoutEntity $order)
    {

        if(!$purchase_id = $order->get_purchase_id()){
            return false;
        }

        $merchant_id = beamcheckout()->get_option('merchant_id');

        try {
            $response = $this->request("purchases/{$merchant_id}/{$purchase_id}");
        } catch (Exception $e) {
            beamcheckout()->log(
                array(
                    'error' => $e->getMessage(),
                )
            );

            return false;
        }

        return $response;

    }

}
