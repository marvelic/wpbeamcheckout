<?php

namespace Marvelic\BeamCheckout\Entities;

use WC_Order;


class BeamCheckoutEntity
{

    /**
     *
     */
    const ORDER_META_BEAM_CHECKOUT_PURCHASE_ID = '_beam_checkout_purchase_id';
    /**
     *
     */
    const ORDER_META_BEAM_CHECKOUT_IS_REFUND = '_is_beam_checkout_refund';
    /**
     *
     */
    const ORDER_META_BEAM_CHECKOUT_PAYMENT_LINK = '_is_beam_checkout_payment_link';
    /**
     *
     */
    const ORDER_META_BEAM_CHECKOUT_STATUS = '_is_beam_checkout_status';


    /**
     * @var WC_Order|null
     */
    protected ?WC_Order $order = null;


    /**
     * @param $order
     * @return BeamCheckoutEntity
     */
    public static function by_order($order): BeamCheckoutEntity
    {
        $instance = new self();
        $instance->order = wc_get_order($order);
        return $instance;
    }

    /**
     * @param string $purchase_id
     *
     * @return false|BeamCheckoutEntity
     */
    public static function get_order_by_purchase_id(string $purchase_id)
    {
        // $orders = wc_get_orders([
        //     'purchase_id' => $purchase_id,
        //     'type' => wc_get_order_types()
        // ]);
        $orders = wc_get_orders([
            'meta_key' => '_beam_checkout_purchase_id',
            'meta_value' => $purchase_id,
            'type' => wc_get_order_types()
        ]);

        if ($orders) {
            return self::by_order($orders[0]);
        }

        return false;
    }

    /**
     * @return WC_Order|null
     */
    public function order(): ?WC_Order
    {
        return $this->order;
    }


    /**
     * @return string|null
     */
    public function get_purchase_id(): ?string
    {
        return $this->order->get_meta(self::ORDER_META_BEAM_CHECKOUT_PURCHASE_ID);
    }

    /**
     * @return string|null
     */
    public function get_payment_link(): ?string
    {
        return $this->order->get_meta(self::ORDER_META_BEAM_CHECKOUT_PAYMENT_LINK);
    }

    /**
     * @return string|null
     */
    public function get_status(): ?string
    {
        return $this->order->get_meta(self::ORDER_META_BEAM_CHECKOUT_STATUS);
    }


    /**
     * @param $purchase_id
     * @return mixed
     */
    public function set_purchase_id($purchase_id)
    {
        $this->order->update_meta_data(self::ORDER_META_BEAM_CHECKOUT_PURCHASE_ID, sanitize_text_field(wp_unslash($purchase_id)));
        return $this->order->save();
    }

    /**
     * @param $payment_link
     * @return int
     */
    public function set_payment_link(string $payment_link): int
    {
        $this->order->update_meta_data(self::ORDER_META_BEAM_CHECKOUT_PAYMENT_LINK, esc_url($payment_link));

        return  $this->order->save();
    }

    /**
     * @param string $status
     * @return int
     */
    public function set_status(string $status): int
    {
        $this->order->update_meta_data(self::ORDER_META_BEAM_CHECKOUT_STATUS, sanitize_text_field(wp_unslash($status)));

        return  $this->order->save();
    }

    /**
     * @param $type
     * @return int
     */
    public function refund($type): int
    {
        $this->order->update_meta_data(self::ORDER_META_BEAM_CHECKOUT_IS_REFUND, $type);
        return $this->order->save();
    }

    /**
     * @return array|mixed|string
     */
    public function is_refund()
    {
        return $this->order->get_meta(self::ORDER_META_BEAM_CHECKOUT_IS_REFUND);
    }


    /**
     * @return array[]
     */
    public function get_beam_checkout_order(): array
    {
        $discount = round( floatval($this->order->get_total_discount()), 2);
        $total = round(floatval($this->order->get_total()), 2);
        $subtotal = round(floatval($this->order->get_subtotal()), 2);

        $data = array(
            'channel' => (string)beamcheckout()->get_option( 'channel'),
            'order' => array(
                'merchantReferenceId' => (string)$this->order->get_id(),
                'netAmount' => $total,
                'totalAmount' => $subtotal,
                'totalDiscount' => $discount
            ),
            'redirectUrl' => $this->order->get_checkout_order_received_url()
        );

        $items = [];

        foreach ($this->order->get_items() as $item) {
            $product = $item->get_product();
            $product_data = [
                'name' => (string)strip_tags($item->get_name()),
                'price' => floatval(($item->get_total()/$item->get_quantity())),
            ];

            $items[] = strip_tags($item->get_name()) . '(' . $item->get_quantity() . ')';

            if ($product && $sku = $product->get_sku()) {
                $product_data['sku'] = (string)$sku;
            }

            if (!$supported_payment_methods = beamcheckout()->get_option( 'supported_payment_methods')) {
                $supported_payment_methods = array('creditCard', 'eWallet', 'internetBanking','qrThb');
            }

            $data['supportedPaymentMethods'] = $supported_payment_methods;

            $data['order']['orderItems'][] = array(
                'product' => $product_data,
                'quantity' => $item->get_quantity()
            );

        }

        foreach ( $this->order->get_shipping_methods() as $shipping_method ) {
            if($shipping_method->get_total() > 0){

                $data['order']['orderItems'][] = array(
                    'product' => array(
                        'name' => (string)strip_tags($shipping_method->get_name()),
                        'price' => floatval($shipping_method->get_total()),
                    ),
                    'quantity' => 1
                );

                $items[] = $shipping_method->get_name();
            }
        }

        $data['order']['description'] = strip_tags(substr(join(', ', $items), 0, 490));

        return $data;
    }


    /**
     * @param string $type
     * @param $total
     * @return array
     */
    public function get_beam_checkout_order_refund(string $type, float $total): array
    {

        return array(
            'amount' => floatval($total),
            'option' => $type
        );
    }


}