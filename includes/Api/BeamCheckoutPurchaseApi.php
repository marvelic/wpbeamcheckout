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
