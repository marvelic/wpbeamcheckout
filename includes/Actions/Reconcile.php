<?php
/**
 * Payment reconciliation.
 *
 * Webhooks can fail to arrive (hosting firewall, CDN, bot protection, ...).
 * This class asks the Beam API - an authenticated outbound request made with
 * the merchant's own credentials - whether an unpaid order has actually been
 * paid, and completes the order if (and only if) every check passes.
 * No inbound request is trusted here, so nothing in this class can be spoofed.
 *
 * @package Marvelic\BeamCheckout
 */

namespace Marvelic\BeamCheckout\Actions;

use Marvelic\BeamCheckout\Entities\BeamCheckoutEntity;
use WC_Order;

defined('ABSPATH') || exit();

/**
 * Class Reconcile
 *
 * @package Marvelic\BeamCheckout
 */
class Reconcile
{
    /**
     * Action Scheduler hook / group.
     */
    const SCHEDULE_HOOK  = 'beamcheckout_reconcile_orders';
    const SCHEDULE_GROUP = 'beamcheckout';

    /**
     * Run the batch every 5 minutes.
     */
    const INTERVAL = 300;

    /**
     * Statuses in which an order may still be waiting for a payment result.
     * "cancelled" is included on purpose: a customer can pay a QR code after
     * WooCommerce auto-cancelled the order.
     */
    const UNPAID_STATUSES = array('pending', 'on-hold', 'failed', 'cancelled');

    /**
     * Only look at orders created within this window.
     */
    const MAX_ORDER_AGE = 2 * DAY_IN_SECONDS;

    /**
     * Orders older than this are polled less often.
     */
    const FRESH_ORDER_AGE = 2 * HOUR_IN_SECONDS;
    const STALE_RECHECK   = HOUR_IN_SECONDS;

    /**
     * Max orders processed (= max Beam API calls) per batch.
     */
    const BATCH_LIMIT = 25;

    /**
     * Order meta: last time we asked Beam about this order.
     */
    const META_LAST_CHECK = '_beam_checkout_last_reconcile';

    /**
     * Purchase state meaning "paid" (same value the webhook uses).
     */
    const PAID_STATE = 'complete';

    /**
     * Back-off when Beam answers 429 / 5xx or does not answer (Beam docs: exponential back-off with jitter).
     */
    const BACKOFF_UNTIL = 'beam_reconcile_backoff_until';
    const BACKOFF_STEP  = 'beam_reconcile_backoff_step';
    const BACKOFF_BASE  = 60;
    const BACKOFF_MAX   = 1800;

    /**
     * Constructor.
     */
    public function __construct()
    {
        // 1. Before WooCommerce auto-cancels an unpaid order, ask Beam first.
        add_filter('woocommerce_cancel_unpaid_order', array($this, 'filter_cancel_unpaid_order'), 10, 2);

        // 2. When the customer comes back to the order-received page.
        add_action('template_redirect', array($this, 'maybe_reconcile_on_return'), 5);

        // 3. Background safety net.
        add_action('init', array($this, 'schedule'), 20);
        add_action(self::SCHEDULE_HOOK, array($this, 'run_batch'));
    }

    /**
     * Don't cancel an unpaid order that Beam reports as paid.
     *
     * @param bool     $cancel Whether WooCommerce is about to cancel.
     * @param WC_Order $order  Order.
     *
     * @return bool
     */
    public function filter_cancel_unpaid_order($cancel, $order)
    {
        if (!$cancel || !$order instanceof WC_Order || !$this->is_beam_order($order)) {
            return $cancel;
        }

        if ($this->reconcile($order, 'before-auto-cancel')) {
            return false;
        }

        return $cancel;
    }

    /**
     * Customer returned from Beam: confirm the payment before the page renders.
     * The order key from the URL must match, exactly like WooCommerce requires.
     */
    public function maybe_reconcile_on_return()
    {
        if (!function_exists('is_wc_endpoint_url') || !is_wc_endpoint_url('order-received')) {
            return;
        }

        global $wp;

        $order_id = absint($wp->query_vars['order-received'] ?? 0);
        $key      = isset($_GET['key']) ? wc_clean(wp_unslash($_GET['key'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification

        if (!$order_id || !is_string($key) || '' === $key) {
            return;
        }

        $order = wc_get_order($order_id);

        if (!$order || !hash_equals((string) $order->get_order_key(), $key) || !$this->is_beam_order($order)) {
            return;
        }

        $this->reconcile($order, 'order-received');
    }

    /**
     * Register the recurring job with Action Scheduler (bundled with WooCommerce).
     */
    public function schedule()
    {
        if (!function_exists('as_has_scheduled_action') || !function_exists('as_schedule_recurring_action')) {
            return;
        }

        if (!as_has_scheduled_action(self::SCHEDULE_HOOK, array(), self::SCHEDULE_GROUP)) {
            as_schedule_recurring_action(time() + 60, self::INTERVAL, self::SCHEDULE_HOOK, array(), self::SCHEDULE_GROUP);
        }
    }

    /**
     * Check recent unpaid Beam orders against the Beam API.
     */
    public function run_batch()
    {
        if (!beamcheckout()->get_option('enabled')) {
            return;
        }

        $order_ids = wc_get_orders(
            array(
                'status'         => self::UNPAID_STATUSES,
                'payment_method' => beamcheckout()->get_option('plugin_id'),
                'date_created'   => '>' . (time() - self::MAX_ORDER_AGE),
                'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
                    array(
                        'key'     => BeamCheckoutEntity::ORDER_META_BEAM_CHECKOUT_PURCHASE_ID,
                        'compare' => 'EXISTS',
                    ),
                ),
                'orderby'        => 'date',
                'order'          => 'DESC',
                'limit'          => 100,
                'return'         => 'ids',
            )
        );

        $processed = 0;

        foreach ($order_ids as $order_id) {
            if ($processed >= self::BATCH_LIMIT || $this->in_backoff()) {
                break;
            }

            $order = wc_get_order($order_id);

            if (!$order || !$this->is_due($order)) {
                continue;
            }

            $processed++;
            $this->reconcile($order, 'scheduler');
        }
    }

    /**
     * Ask Beam whether the order is paid and complete it if all checks pass.
     *
     * @param WC_Order $order  Order.
     * @param string   $source Who triggered the check (for the order note/log).
     *
     * @return bool True if the order is now paid because of this check.
     */
    public function reconcile(WC_Order $order, string $source): bool
    {
        if (!$this->is_beam_order($order) || !$order->has_status(self::UNPAID_STATUSES) || $this->in_backoff()) {
            return false;
        }

        // Cheap lock: stops page refreshes / overlapping jobs from hammering the API.
        $lock = 'beam_reconcile_' . $order->get_id();
        if (get_transient($lock)) {
            return false;
        }
        set_transient($lock, 1, 30);

        $entity = BeamCheckoutEntity::by_order($order);

        if (!$entity->get_purchase_id()) {
            return false;
        }

        $order->update_meta_data(self::META_LAST_CHECK, time());
        $order->save_meta_data();

        try {
            $info = beamcheckout()->api('order')->fetch($entity);
        } catch (\Exception $e) {
            $code = (int) $e->getCode();

            // 0 = no response (timeout / DNS / TLS), 429 = rate limited, 5xx = Beam problem: slow down.
            if (0 === $code || 429 === $code || $code >= 500) {
                $this->register_failure();
            }

            beamcheckout()->log(
                array(
                    'order_id' => $order->get_id(),
                    'source'   => $source,
                    'result'   => 'Beam API check failed',
                    'status'   => $code,
                )
            );

            return false;
        }

        $this->reset_backoff();

        if (!$this->is_valid_paid_purchase($info, $order, $entity)) {
            return false;
        }

        // Re-read: the webhook may have completed the order while we were calling the API.
        $fresh = wc_get_order($order->get_id());

        if (!$fresh || !$fresh->has_status(self::UNPAID_STATUSES)) {
            return false;
        }

        $was_cancelled = $fresh->has_status('cancelled');

        if (!$fresh->payment_complete($entity->get_purchase_id())) {
            return false;
        }

        BeamCheckoutEntity::by_order($fresh)->set_status(self::PAID_STATE);

        $fresh->add_order_note(
            sprintf(
                /* translators: 1: trigger source */
                __('Payment confirmed by checking Beam directly (%s).', 'woocommerce-gateway-beamcheckout'),
                $source
            )
        );

        if ($was_cancelled) {
            $fresh->add_order_note(
                __('This order had been cancelled before the payment was detected. Please check stock levels.', 'woocommerce-gateway-beamcheckout')
            );
        }

        beamcheckout()->log(
            array(
                'order_id' => $fresh->get_id(),
                'source'   => $source,
                'result'   => 'completed via API reconcile',
            )
        );

        return true;
    }

    /**
     * Verify the API response really describes a paid purchase for this order.
     *
     * @param array              $info   Purchase returned by Beam.
     * @param WC_Order           $order  Order.
     * @param BeamCheckoutEntity $entity Entity.
     *
     * @return bool
     */
    protected function is_valid_paid_purchase(array $info, WC_Order $order, BeamCheckoutEntity $entity): bool
    {
        $state = isset($info['state']) && is_string($info['state']) ? $info['state'] : '';

        if (self::PAID_STATE !== $state) {
            return false;
        }

        $merchant_id  = (string) beamcheckout()->get_option('merchant_id');
        $reference_id = (string) ($info['order']['merchantReferenceId'] ?? '');
        $net_amount   = $info['order']['netAmount'] ?? null;
        $currency     = (string) ($info['order']['currencyCode'] ?? '');

        $checks = array(
            'merchantId'          => hash_equals($merchant_id, (string) ($info['merchantId'] ?? '')),
            'purchaseId'          => hash_equals((string) $entity->get_purchase_id(), (string) ($info['purchaseId'] ?? '')),
            'merchantReferenceId' => hash_equals((string) $order->get_id(), $reference_id),
            'amount'              => is_numeric($net_amount) && abs((float) $net_amount - (float) $order->get_total()) < 0.01,
            'currency'            => '' !== $currency && 0 === strcasecmp($currency, $order->get_currency()),
        );

        $failed = array_keys(array_filter($checks, static function ($ok) {
            return !$ok;
        }));

        if ($failed) {
            beamcheckout()->log(
                array(
                    'order_id' => $order->get_id(),
                    'result'   => 'Beam reports paid but validation failed - order NOT changed',
                    'failed'   => $failed,
                )
            );

            return false;
        }

        return true;
    }

    /**
     * Is this order due for a check in the scheduled batch?
     *
     * @param WC_Order $order Order.
     *
     * @return bool
     */
    protected function is_due(WC_Order $order): bool
    {
        $created = $order->get_date_created();

        if (!$created || (time() - $created->getTimestamp()) < self::FRESH_ORDER_AGE) {
            return true;
        }

        $last = (int) $order->get_meta(self::META_LAST_CHECK);

        return (time() - $last) >= self::STALE_RECHECK;
    }

    /**
     * @return bool
     */
    protected function in_backoff(): bool
    {
        return (int) get_transient(self::BACKOFF_UNTIL) > time();
    }

    /**
     * Exponential back-off with jitter: 1, 2, 4 ... up to 30 minutes.
     */
    protected function register_failure()
    {
        $step = min(10, (int) get_transient(self::BACKOFF_STEP));
        $wait = min(self::BACKOFF_MAX, self::BACKOFF_BASE * (2 ** $step));
        $wait += wp_rand(0, (int) ($wait / 4));

        set_transient(self::BACKOFF_UNTIL, time() + $wait, $wait);
        set_transient(self::BACKOFF_STEP, $step + 1, HOUR_IN_SECONDS);
    }

    protected function reset_backoff()
    {
        delete_transient(self::BACKOFF_UNTIL);
        delete_transient(self::BACKOFF_STEP);
    }

    /**
     * @param WC_Order $order Order.
     *
     * @return bool
     */
    protected function is_beam_order(WC_Order $order): bool
    {
        return $order->get_payment_method() === beamcheckout()->get_option('plugin_id');
    }
}
