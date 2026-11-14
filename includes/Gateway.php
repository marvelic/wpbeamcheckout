<?php

/**
 * Gateway class
 *
 * @package Marvelic\BeamCheckout
 */

namespace Marvelic\BeamCheckout;

use Exception;

use Marvelic\BeamCheckout\Ajax\Order as AjaxOrder;
use Marvelic\BeamCheckout\Entities\BeamCheckoutEntity;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use WC_Payment_Gateway;

defined('ABSPATH') || exit();

/**
 * Class Gateway
 *
 * @package Marvelic\BeamCheckout
 * @since   1.0.0
 */
class Gateway extends WC_Payment_Gateway
{

    /**
     * Gateway constructor.
     */
    public function __construct()
    {

        $this->id = beamcheckout()->get_option('id');
        $this->method_title = __('Beam Checkout', 'woocommerce-gateway-beamcheckout');
        $this->method_description = __('Beam Checkout', 'woocommerce-gateway-beamcheckout');
        $this->has_fields = true;
     //   $this->supports = array('products', 'refunds');
        $this->supports = array('products');
        $this->icon = beamcheckout()->get_option('assets_url') . 'images/beamLogo.png';

        $this->init_form_fields();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');

        if ($this->get_option('icon_location') === 'before_title') {

            add_filter(
                'woocommerce_gateway_icon',
                array(
                    $this,
                    'icon_before_title_style'
                ),
                10000,
                2
            );

            $this->title = $this->get_icon() . $this->title;
            $this->icon = false;
        }

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array(
                $this,
                'process_admin_options',
            )
        );

        // add_action('woocommerce_blocks_loaded', array($this, 'register_block'));

        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        add_action('woocommerce_order_status_cancelled', array($this, 'cancel_order'), 10, 1);

        // Override checkout
        // add_action('admin_init', array($this, 'update_checkout_content'));
    }

    /*public function update_checkout_content()
    {
        // Get the WooCommerce checkout page ID
        $checkout_page_id = get_option('woocommerce_checkout_page_id');

        // Check if WooCommerce checkout page ID is found
        if (isset($_GET['section']) && $_GET['section'] == 'woocommerce-gateway-beamcheckout') {
            $override = $this->get_option('override_template');

            if (empty($override) || $override == 'yes') {
                // Change to classic checkout
                $custom_content = '<!-- wp:woocommerce/classic-shortcode {"shortcode":"checkout"} /-->';

                // Update the content of the WooCommerce checkout page
                $page = get_post($checkout_page_id);
                if ($page) {
                    $page->post_content = $custom_content;
                    wp_update_post($page);
                }
            }
        }
    }*/


    /**
     * Render key fields, hiding the set that does not match the saved mode.
     *
     * Hiding on the server avoids a flash of both key sets before the settings script runs.
     * The rows stay in the form, so the hidden values are still posted and saved.
     *
     * @param string $key  Field key.
     * @param array  $data Field data.
     *
     * @return string
     */
    public function generate_password_html($key, $data)
    {
        $html = parent::generate_password_html($key, $data);

        $is_test = $this->get_option('is_test') === 'yes';
        $hidden_keys = $is_test ? array('api_key', 'private_key') : array('test_api_key', 'test_private_key');

        if (in_array($key, $hidden_keys, true)) {
            $html = preg_replace('/<tr\b/', '<tr style="display:none"', $html, 1);
        }

        return $html;
    }

    /**
     * @param $icon
     * @param $gateway_id
     * @return string|string[]
     */
    public function icon_before_title_style($icon, $gateway_id)
    {
        if ($gateway_id !== beamcheckout()->get_option('id')) {
           return $icon;
        }

        return str_replace('alt', 'class="payment_method_woocommerce-gateway-beamcheckout__img" alt', $icon);
    }

    /**
     * Initialise settings form fields.
     *
     * Add an array of fields to be displayed on the gateway's settings screen.
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce-gateway-beamcheckout'),
                'label' => __('Enable Gateway', 'woocommerce-gateway-beamcheckout'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce-gateway-beamcheckout'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-gateway-beamcheckout'),
                'default' => $this->method_title,
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce-gateway-beamcheckout'),
                'type' => 'text',
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-gateway-beamcheckout'),
                'default' => $this->method_description,
                'desc_tip' => true,
            ),


            'merchant_id' => array(
                'title' => __('Merchant ID', 'woocommerce-gateway-beamcheckout'),
                'type' => 'text',
                'default' => '',
                'description' => __('Provide your Beam merchant id', 'woocommerce-gateway-beamcheckout'),
            ),
            'is_test' => array(
                'title' => __('Mode', 'woocommerce-gateway-beamcheckout'),
                'type' => 'select',
                'options' => array(
                    'no' => __('Live', 'woocommerce-gateway-beamcheckout'),
                    'yes' => __('Test', 'woocommerce-gateway-beamcheckout'),
                ),
                'description' => __('Choose which set of keys is used. Both sets are saved, so you can switch back and forth at any time.', 'woocommerce-gateway-beamcheckout'),
                'default' => 'no',
                'desc_tip' => true,
            ),

            'api_key' => array(
                'title' => __('API Key', 'woocommerce-gateway-beamcheckout'),
                'type' => 'password',
                'default' => '',
                'description' => __('Provide the API key that has been generated for you', 'woocommerce-gateway-beamcheckout'),
            ),

            'private_key' => array(
                'title' => __('HMAC Key', 'woocommerce-gateway-beamcheckout'),
                'type' => 'password',
                'default' => '',
                'description' => __('', 'woocommerce-gateway-beamcheckout'),
            ),

            'test_api_key' => array(
                'title' => __('TEST API Key', 'woocommerce-gateway-beamcheckout'),
                'type' => 'password',
                'default' => '',
                'description' => __('Provide the API key that has been generated for you', 'woocommerce-gateway-beamcheckout'),
            ),

            'test_private_key' => array(
                'title' => __('TEST HMAC Key', 'woocommerce-gateway-beamcheckout'),
                'type' => 'password',
                'default' => '',
                'description' => __('', 'woocommerce-gateway-beamcheckout'),
            ),

            'channel' => array(
                'title' => __('Channel', 'woocommerce-gateway-beamcheckout'),
                'type' => 'text',
                'default' => 'Woocommerce',
                'description' => '',
            ),

            'supported_payment_methods' => array(
                'title' => __('Supported Payment Methods', 'woocommerce-gateway-beamcheckout'),
                'type' => 'multiselect',
                'options' => array(
                    'creditCard' => 'Credit Card',
                    'eWallet' => 'eWallet',
                    'internetBanking' => 'Internet Banking',
                    'installmentsCc' => 'Installment (via credit card)',
                    'bnpl' => 'Buy now pay later (via Atome)',
                    'qrThb' => 'PromptPay QR',
                ),
                'default' => array('creditCard', 'eWallet', 'internetBanking', 'qrThb'),
                'select_buttons' => true,
                'custom_attributes' => array(
                    'size' => 6
                ),
                'description' => __('Supported Payment Methods overrides the merchants default payment method setting Payment method specified here must be enabled by the merchant', 'woocommerce-gateway-beamcheckout'),
            ),

            'icon_location' => array(
                'title' => __('Icon location', 'woocommerce-gateway-beamcheckout'),
                'type' => 'select',
                'options' => array(
                    'after_title' => 'After title',
                    'before_title' => 'Before title',
                ),
                'default' => 'after_title',
            ),
            /*
            'is_cancel_order' => array(
                'title' => __('Allow order cancellation', 'woocommerce-gateway-beamcheckout'),
                'type' => 'checkbox',
                'description' => __('Allows you to cancel an order and cancel a payment', 'woocommerce-gateway-beamcheckout'),
                'default' => 'no',
                'desc_tip' => true,
            ),

            'is_refund_order' => array(
                'title' => __('Refund of funds', 'woocommerce-gateway-beamcheckout'),
                'type' => 'checkbox',
                'description' => __('Allows you to make a refund', 'woocommerce-gateway-beamcheckout'),
                'default' => 'no',
                'desc_tip' => true,
            ),
*/
            'webhook_url' => array(
                'title' => __('Webhook Url', 'woocommerce-gateway-beamcheckout'),
                'type' => 'webhook_url',
                'url' => AjaxOrder::get_action_url(
                    'webhook',
                    false
                ),
                'description' => '',
            ),

            'logging' => array(
                'title' => __('Logging', 'woocommerce-gateway-beamcheckout'),
                'label' => __('Log debug messages', 'woocommerce-gateway-beamcheckout'),
                'type' => 'checkbox',
                'description' => __('Save debug messages to the WooCommerce System Status log.', 'woocommerce-gateway-beamcheckout'),
                'default' => 'yes',
                'desc_tip' => true,
            ),

            // 'override_template' => array(
            //     'title' => __('Override Checkout', 'woocommerce-gateway-beamcheckout'),
            //     'type' => 'checkbox',
            //     'description' => __('In this version, checkout blocks are not supported yet. If you do not enable them, you will be unable to use our payment system.', 'woocommerce-gateway-beamcheckout'),
            //     'default' => 'no',
            //     'desc_tip' => true,
            // )
        );
    }


    /**
     * @param string $key
     * @param array $data
     * @return string
     */
    public function generate_webhook_url_html(string $key, array $data): string
    {
        $field_key = $this->get_field_key($key);
        $defaults = array(
            'title' => '',
            'class' => '',
            'css' => '',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => array(),
            'url' => ''
        );

        $data = wp_parse_args($data, $defaults);

        ob_start();
?>
        <tr>
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?><?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.
                    ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <span class="input-text regular-input <?php echo esc_attr($data['class']); ?>" id="<?php echo esc_attr($field_key); ?>" style="<?php echo esc_attr($data['css']); ?>" <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok.
                                                                                                                                                                                            ?>>
                        <?php echo esc_url($data['url']); ?>
                    </span>
                    <?php echo $this->get_description_html($data); // WPCS: XSS ok.
                    ?>
                </fieldset>
            </td>
        </tr>
<?php

        return ob_get_clean();
    }

    /**
     * Validate File Field.
     *
     * If the file was not loaded when saving, take the current path of the file.
     *
     * @param string $key Field key.
     * @param string $value Posted Value.
     *
     * @return string
     */
    public function validate_file_field(string $key, string $value): string
    {
        if (empty($value)) {
            $value = $this->get_option($key);
        }

        return $value;
    }


    /**
     * Process Payment.
     *
     * Process the payment. Override this in your gateway. When implemented, this should.
     * return the success and redirect in an array. e.g:
     *
     *        return array(
     *            'result'   => 'success',
     *            'redirect' => $this->get_return_url( $order )
     *        );
     *
     * @param mixed $order_id Order ID.
     *
     * @return array
     * @throws Exception If API error.
     */
    public function process_payment($order_id): array
    {
        try {

            $beam_checkout_entity = BeamCheckoutEntity::by_order($order_id);
            $redirect_url = false;

            if ($payment_link = $beam_checkout_entity->get_payment_link()) {
                $info = beamcheckout()
                    ->api('order')
                    ->get_info($beam_checkout_entity);

                if ($info) {
                    $redirect_url = $payment_link;
                }
            }

            if (!$redirect_url) {
                $redirect_url = beamcheckout()
                    ->api('order')
                    ->create($beam_checkout_entity);
            }
        } catch (Exception $e) {
            beamcheckout()->log(
                array(
                    'error' => $e->getMessage(),
                )
            );

            throw new Exception(
                beamcheckout()->get_option('is_test') ? $e->getMessage() : __('Request error. Try again or contact us', 'woocommerce-gateway-beamcheckout')
            );
        }

        $beam_checkout_entity->set_payment_link($redirect_url);

        if (is_wc_endpoint_url('order-pay')) {
            wp_redirect($redirect_url);
            die;
        }

        return array(
            'result' => 'success',
            'redirect' => $redirect_url,
        );
    }

    /**
     * Change default payment fields
     */
    public function payment_fields()
    {
        parent::payment_fields();
    }

    /**
     * Cancel order
     *
     * @param mixed $order_id order id to cancel order.
     *
     * @throws Exception If cancellation error.
     */
    public function cancel_order($order_id)
    {
        if (!$this->paid_for_order($order_id)) {
            return;
        }

        if (!$this->get_option('is_cancel_order')) {
            return;
        }

        $order_entity = BeamCheckoutEntity::by_order($order_id);

        try {
            beamcheckout()->api('refund')
                ->cancel($order_entity);

            $order_entity->order()->add_order_note(
                __('Payment cancelled', 'woocommerce-gateway-beamcheckout'),
            );
        } catch (Exception $e) {
            beamcheckout()->log($e->getMessage());

            throw new Exception($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Process refund.
     *
     * If the gateway declares 'refunds' support, this will allow it to refund.
     * a passed in amount.
     *
     * @param mixed $order_id Order ID.
     * @param float|null $amount Refund amount.
     * @param string $reason Refund reason.
     *
     * @return bool True or false based on success, or a WP_Error object.
     * @throws Exception If refund error.
     */
    public function process_refund($order_id, $amount = null, $reason = ''): bool
    {
        if (!$this->paid_for_order($order_id)) {
            return false;
        }

        if (!$this->get_option('is_refund_order')) {
            return false;
        }

        $order_entity = BeamCheckoutEntity::by_order($order_id);

        try {
            beamcheckout()->api('refund')
                ->refund($order_entity);

            $order_entity->order()->add_order_note(
                __('Payment cancelled', 'woocommerce-gateway-beamcheckout'),
            );
        } catch (Exception $e) {
            beamcheckout()->log($e->getMessage());

            throw new Exception($e->getMessage(), $e->getCode());
        }

        return true;
    }

    /**
     * Get settings page url
     *
     * @return string|void
     */
    public function get_settings_page_url(): string
    {
        return admin_url('admin.php?page=wc-settings&tab=checkout&section=' . strtolower(beamcheckout()->gateway()->id));
    }

    /**
     * Check that order is paid by the current gateway
     *
     * @param mixed $order order to check.
     *
     * @return bool
     */
    public function paid_for_order($order): bool
    {
        $order = wc_get_order($order);

        return !empty($order) && $order->get_payment_method() === $this->id;
    }

    /**
     * Enqueue gateway styles and scripts
     */
    public function enqueue_assets()
    {
        if (is_checkout() && $this->enabled) {
            Frontend\Checkout::enqueue_assets();
        }
    }
}
