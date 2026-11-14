<?php


namespace Marvelic\BeamCheckout\Api;


use Exception;
use Marvelic\BeamCheckout\Entities\BeamCheckoutEntity;

class BeamCheckoutRefundPurchaseApi extends Base
{

    /**
     * @param BeamCheckoutEntity $BeamCheckoutEntity
     * @return int
     * @throws Exception
     */
    public function cancel(BeamCheckoutEntity $BeamCheckoutEntity): int
    {
        return $this->create($BeamCheckoutEntity, 'void');
    }

    /**
     * @param BeamCheckoutEntity $BeamCheckoutEntity
     * @return int
     * @throws Exception
     */
    public function refund(BeamCheckoutEntity $BeamCheckoutEntity): int
    {
        return $this->create($BeamCheckoutEntity, 'refund');
    }


    /**
     * @param BeamCheckoutEntity $BeamCheckoutEntity
     * @return array
     * @throws Exception
     */
    public function get_option(BeamCheckoutEntity $BeamCheckoutEntity): array
    {
        if(!$purchase_id = $BeamCheckoutEntity->get_purchase_id()){
            throw new Exception(__('Not purchaseId', 'woocommerce-gateway-beamcheckout'));
        }

        $merchant_id = beamcheckout()->get_option('merchant_id');
        $response = $this->request("refunds/{$merchant_id}/{$purchase_id}/option");

        beamcheckout()->log($response);

        if (!isset($response['option'])) {
            throw new Exception(__('Not refund option', 'woocommerce-gateway-beamcheckout'));
        }

        return array(
            'type' => mb_strtolower(sanitize_text_field(wp_unslash($response['option']))),
            'total' => floatval($response['maxRefundableAmount'])
        );

    }

    /**
     * @param BeamCheckoutEntity $BeamCheckoutEntity
     * @param string $type
     * @return int
     * @throws Exception
     */
    public function create(BeamCheckoutEntity $BeamCheckoutEntity, string $type): int
    {

        if(!$purchase_id = $BeamCheckoutEntity->get_purchase_id()){
            throw new Exception(__('Not purchaseId', 'woocommerce-gateway-beamcheckout'));
        }

        $option = $this->get_option($BeamCheckoutEntity);

        if ($option['type'] !== $type) {
            throw new Exception(__('Not type', 'woocommerce-gateway-beamcheckout'));
        }

        if(!$option['total'] > 0){
            throw new Exception(__('The amount to be refunded is 0', 'woocommerce-gateway-beamcheckout'));
        }

        $merchant_id = beamcheckout()->get_option('merchant_id');

        $response = $this->request(
            "refunds/{$merchant_id}/{$purchase_id}",
            'POST',
            $BeamCheckoutEntity->get_beam_checkout_order_refund($option['type'] , floatval($option['total']))
        );

        if (!isset($response['code'])) {
            throw new Exception(__('Not refund', 'woocommerce-gateway-beamcheckout'));
        }

        $BeamCheckoutEntity->order()->add_order_note(
            __($response['message'], 'woocommerce-gateway-beamcheckout'),
        );

        return $BeamCheckoutEntity->refund($option['type']);

    }

}