<?php

use Marvelic\BeamCheckout\Entities\BeamCheckoutEntity;

defined('ABSPATH') || exit;

if (!isset($order_id)) {
    return;
}

$beam_checkout_order = BeamCheckoutEntity::by_order($order_id);


?>

<div class="woocommerce-gateway-beamcheckout_capture_payment__container">

    <?php if ($purchase_id = $beam_checkout_order->get_purchase_id()) : ?>
        <div class="woocommerce-gateway-beamcheckout_capture_payment__info">
            <div class="woocommerce-gateway-beamcheckout_capture_payment__info_label"><?php _e('Purchase ID', 'woocommerce-gateway-beamcheckout'); ?>: </div>
            <div class="woocommerce-gateway-beamcheckout_capture_payment__info_content"><?php esc_attr_e($purchase_id, 'woocommerce-gateway-beamcheckout'); ?> </div>
        </div>
    <?php endif; ?>

    <?php if ($payment_link = $beam_checkout_order->get_payment_link()) : ?>
        <div class="woocommerce-gateway-beamcheckout_capture_payment__info">
            <div class="woocommerce-gateway-beamcheckout_capture_payment__info_content">
                <a href="<?php echo esc_url($payment_link); ?>"
                   class="woocommerce-gateway-beamcheckout_capture_payment__info_link"
                   target="_blank"
                ><?php _e('Payment link', 'woocommerce-gateway-beamcheckout'); ?></a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($status = $beam_checkout_order->get_status()) : ?>
        <div class="woocommerce-gateway-beamcheckout_capture_payment__info">
            <div class="woocommerce-gateway-beamcheckout_capture_payment__info_label"><?php _e('Status', 'woocommerce-gateway-beamcheckout'); ?>  : </div>
            <div class="woocommerce-gateway-beamcheckout_capture_payment__info_content"><?php esc_attr_e($status, 'woocommerce-gateway-beamcheckout'); ?> </div>
        </div>
    <?php endif; ?>

</div>
