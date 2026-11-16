<?php
/**
 * Ajax Order class
 *
 * @package Marvelic\BeamCheckout
 */

namespace Marvelic\BeamCheckout\Ajax;

use Marvelic\BeamCheckout\Entities\BeamCheckoutEntity;
use WC_Order;

defined('ABSPATH') || exit();

/**
 * Class Ajax Order
 *
 * @package Marvelic\BeamCheckout
 * @since   1.0.0
 */
class Order extends Base
{
    /**
     * Prefix for actions
     *
     * @var string
     */
    const PREFIX = 'beamcheckout_order';

    /**
     * Ajax for wc api (registration with prefix)
     *
     * @var array
     */
    const ACTIONS = array(
        'webhook',
    );

    /**
     * @var string
     */
    protected string $state;

    /**
     * @var string
     */
    protected string $transaction_id;

    /**
     * Success url handler
     */
    public function webhook()
    {
        $order = $this->get_order_from_get_request();

        // Already paid (e.g. confirmed by the API check or a webhook retry): acknowledge so Beam stops retrying.
        if ($this->state === 'complete' && $order->order()->is_paid()) {
            wp_send_json_success(
                array(
                    'success' => true,
                    'message' => __('payment already complete', 'woocommerce-gateway-beamcheckout'),
                )
            );
        }

        // Only "complete" is a payment result for this event. Acknowledge anything else with 2xx
        // so Beam does not retry something we will never act on.
        if ($this->state !== 'complete') {
            beamcheckout()->log(array('event' => 'webhook ignored', 'state' => $this->state));
            wp_send_json_success(
                array(
                    'success' => true,
                    'message' => __('ignored', 'woocommerce-gateway-beamcheckout'),
                )
            );
        }

        if ($this->state === 'complete' && $order->order()->payment_complete($this->transaction_id)) {

            $order->order()->add_order_note(__('Payment complete', 'woocommerce'));

            $order->set_status($this->state);

            wp_send_json_success(
                array(
                    'success' => true,
                    'message' => __('payment complete', 'woocommerce-gateway-beamcheckout'),
                )
            );

        } else {
            beamcheckout()->log('Error');
            wp_send_json_error(
                array(
                    'success' => false,
                    'message' => __('Error', 'woocommerce-gateway-beamcheckout'),
                ),
            );
        }
    }

    /**
     * Validate $_GET data and return  order data based on $_GET data
     *
     * @return false|BeamCheckoutEntity|WC_Order
     */
    public function get_order_from_get_request()
    {
        $data = file_get_contents('php://input');

        // Log only what is needed to diagnose delivery problems. Never the body (customer data / PII,
        // see Beam go-live checklist), the full $_SERVER or the signature itself.
        beamcheckout()->log(
            array(
                'event'           => 'webhook received',
                'remote_addr'     => sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')),
                'forwarded_for'   => sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')),
                'user_agent'      => sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '')),
                'method'          => sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? '')),
                'beam_event'      => sanitize_text_field(wp_unslash($_SERVER['HTTP_X_BEAM_EVENT'] ?? '')),
                'notification_id' => sanitize_text_field(wp_unslash($_SERVER['HTTP_X_BEAM_WEBHOOK_NOTIFICATION_ID'] ?? '')),
                'has_signature'   => isset($_SERVER['HTTP_X_HUB_SIGNATURE']),
                'body_bytes'      => strlen($data),
            )
        );

        if(!$this->validate_request($data)){
            beamcheckout()->log('X_HUB_SIGNATURE is invalid');
            wp_send_json_error(
                array(
                    'success' => false,
                    'message' => __('X_HUB_SIGNATURE is invalid', 'woocommerce-gateway-beamcheckout'),
                ),
                401
            );
        }

        $data = json_decode($data, true);

        if (!is_array($data)) {
            beamcheckout()->log('Wrong data');
            wp_send_json_error(
                array(
                    'success' => false,
                    'message' => __('Wrong data', 'woocommerce-gateway-beamcheckout'),
                )
            );
        }

        if (!isset($data['merchantId'])) {
            beamcheckout()->log('merchantId not set');
            wp_send_json_error(
                array(
                    'success' => false,
                    'message' => __('merchantId not set', 'woocommerce-gateway-beamcheckout'),
                )
            );
        }

        $merchant_id = sanitize_text_field(wp_unslash($data['merchantId'] ?? ''));
        $purchase_id = sanitize_text_field(wp_unslash($data['purchaseId'] ?? ''));
        $this->state = sanitize_text_field(wp_unslash($data['state'] ?? ''));

        if ($merchant_id !== beamcheckout()->get_option('merchant_id') || $purchase_id === '' || $this->state === '') {
            beamcheckout()->log('Data is invalid');
            wp_send_json_error(
                array(
                    'success' => false,
                    'message' => __('Data is invalid', 'woocommerce-gateway-beamcheckout'),
                )
            );
        }

        beamcheckout()->log(
            array(
                'event'      => 'webhook accepted',
                'purchaseId' => $purchase_id,
                'state'      => $this->state,
            )
        );

        $order = BeamCheckoutEntity::get_order_by_purchase_id($purchase_id);

        if (!$order ||
            $order->order()->get_payment_method() !== beamcheckout()->get_option('plugin_id')
        ) {
            beamcheckout()->log('Wrong order');
            // 404 (not 2xx): Beam retries non-2xx up to 10 times, which helps if the order is not saved yet.
            wp_send_json_error(
                array(
                    'success' => false,
                    'message' => __('Wrong order', 'woocommerce-gateway-beamcheckout'),
                ),
                404
            );
        }

        $this->transaction_id = $purchase_id;

        return $order;
    }

    /**
     * @param $data
     * @return bool
     */
    protected function validate_request($data): bool
    {
        if (!isset($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
            return false;
        }

        $signature = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_HUB_SIGNATURE']));

        $key = base64_decode((string) beamcheckout()->get_option('private_key'));

        // Without a configured HMAC key anyone could compute a valid signature.
        if (!is_string($key) || '' === $key) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $data, $key), $signature);
    }


}
