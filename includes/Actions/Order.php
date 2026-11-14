<?php
/**
 * Order actions class
 *
 * @package Marvelic\GatewayTemplate
 */

namespace Marvelic\BeamCheckout\Actions;

defined( 'ABSPATH' ) || exit();

/**
 * Class Order
 *
 * @package Marvelic\GatewayTemplate
 * @since   1.0.0
 */
class Order {
    /**
     * OrderController constructor.
     */
    public function __construct() {
        add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', [$this, 'handle_order_purchase_id_query_var'], 10, 2 );
    }

    /**
     * @param $query
     * @param $query_vars
     * @return mixed
     */
    public function handle_order_purchase_id_query_var( $query,  $query_vars) {

        if ( ! empty( $query_vars['purchase_id'] ) ) {
            $query['meta_query'][] = array(
                'key' => '_beam_checkout_purchase_id',
                'value' => esc_attr( $query_vars['purchase_id'] ),
            );
        }

        return $query;
    }

}
