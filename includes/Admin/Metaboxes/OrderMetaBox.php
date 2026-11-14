<?php
/**
 * Order capture metabox class
 *
 * @package Marvelic\GatewayTemplate
 */

namespace Marvelic\BeamCheckout\Admin\Metaboxes;
use WP_Post;

defined( 'ABSPATH' ) || exit();

/**
 * Class OrderCapture
 *
 * @package Marvelic\GatewayTemplate
 * @since   1.0.0
 */
class OrderMetaBox {
    /**
     * ExampleMetaBox constructor.
     */
    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
    }

    /**
     * Register meta boxes on order page
     */
    public function add_meta_boxes() {
        global $post;

        $screen     = get_current_screen();
        $post_types = wc_get_order_types();

        if ( ! in_array( $screen->id, $post_types, true ) || ! in_array( $post->post_type, $post_types, true ) ) {
            return;
        }

        $order = wc_get_order( $post->ID );

        if ( empty( $order ) ) {
            return;
        }

        if($order->get_payment_method() !== beamcheckout()->get_option('plugin_id')){
            return;
        }

        add_meta_box(
            beamcheckout()->get_option( 'id' ) . '_capture_payment',
            __( 'BeamCheckout' ),
            array( $this, 'generate_meta_box' ),
            $post_types,
            'side',
            'high'
        );
    }

    /**
     * Show customer meta box content
     *
     * @param  WP_Post $post  current post object.
     * @param  array   $args  additional arguments sent to add_meta_box function.
     */
    public function generate_meta_box( WP_Post $post, array $args ) {
        beamcheckout()->include_template(
            'admin/order-metabox.php', [
                'order_id' => $post->ID,
                'args' => $args
            ]
        );
    }
}
