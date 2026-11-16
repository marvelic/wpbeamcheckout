<?php

/**
 * Plugin Name: BeamCheckout Payment Gateway
 * Plugin URI: https://marvelic.co.th
 * Description: Accept payments via Gateway Template. Playground Added.
 * Author: Marvelic
 * Author URI: https://marvelic.co.th
 * Text Domain: woocommerce-gateway-beamcheckout
 * Domain Path: /languages
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 * Requires at least: 5.7
 * Requires PHP: 7.4
 * Version: 1.9.1
 *
 * @package Marvelic\BeamCheckout
 */

namespace Marvelic\BeamCheckout;

defined('ABSPATH') || exit();

/**
 * Class Main
 *
 * @package Marvelic\BeamCheckout
 * @since   1.0.0
 */
class Main
{
	/**
	 * Class instance
	 *
	 * @var Main|null
	 */
	private static ?Main $instance = null;

	/**
	 * Plugin id
	 *
	 * @var string
	 */
	private string $plugin_id = 'woocommerce-gateway-beamcheckout';

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	private string $version = '1.9.1';

	/**
	 * Plugin url
	 *
	 * @var string
	 */
	private string $plugin_url;

	/**
	 * Plugin path
	 *
	 * @var string
	 */
	private string $plugin_path;

	/**
	 * Plugin main file name with plugin dir name
	 *
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Assets url
	 *
	 * @var string
	 */
	private string $assets_url;

	/**
	 * Settings array
	 *
	 * @var array|null
	 */
	private ?array $plugin_options = null;

	/**
	 * Settings array
	 *
	 * @var array|null
	 */
	private ?array $gateway_options = null;

	/**
	 * Admin class instance
	 *
	 * @var Api\Main|null
	 */
	private ?Api\Main $api = null;

	/**
	 * Admin class instance
	 *
	 * @var Admin\Main|null
	 */
	private ?Admin\Main $admin = null;

	/**
	 * Main constructor.
	 */
	private function __construct()
	{
		$this->plugin_url  = plugin_dir_url(__FILE__);
		$this->plugin_path = plugin_dir_path(__FILE__);
		$this->assets_url  = $this->plugin_url . '/assets/dist/';
		$this->plugin_file = plugin_basename(__FILE__);

		require_once __DIR__ . '/vendor/autoload.php';

		load_plugin_textdomain($this->plugin_id, false, "$this->plugin_id/languages/");

		add_action('plugins_loaded', array($this, 'action_plugins_loaded'));

		register_activation_hook(__FILE__, array($this, 'activation_hook'));
		register_deactivation_hook(__FILE__, array($this, 'deactivation_hook'));

		add_filter('woocommerce_payment_gateways', array($this, 'filter_add_payment_gateway'));

		// Add support for WooCommerce Blocks & Custom Order Tables
		add_action('before_woocommerce_init', array($this, 'beam_declare_cart_checkout_blocks_compatibility'));
	}

	/**
	 * Get api class instance.
	 *
	 * @param string $api_name Api name to get.
	 *
	 * @return object|null
	 */
	public function api(string $api_name): ?object
	{
		if (is_null($this->api)) {
			$this->api = new Api\Main();
		}

		return $this->api->get($api_name);
	}


	/**
	 * Disable plugin if Woocommerce not active
	 */
	public function action_plugins_loaded()
	{
		if (! class_exists('WC_Payment_Gateway')) {
			$this->disable_plugin();
			return;
		}


		
		$this->admin = new Admin\Main();
		new Ajax\Main();
		new Actions\Main();

		require_once plugin_dir_path(__FILE__) . 'includes/Blocks/Blocks.php';
	}

	/**
	 * Add Gateway to Woo gateways list
	 *
	 * @param  array $gateways  Array of gateway classes or class names.
	 *
	 * @return array
	 */
	public function filter_add_payment_gateway(array $gateways): array
	{
		$gateways[] = Gateway::class;

		return $gateways;
	}

	/**
	 * Activation hook
	 */
	public function activation_hook() {}

	/**
	 * Deactivation hook
	 */
	public function deactivation_hook()
	{
		// Stop the recurring payment check so it does not keep running (and failing) without the plugin.
		if (function_exists('as_unschedule_all_actions')) {
			as_unschedule_all_actions('beamcheckout_reconcile_orders', array(), 'beamcheckout');
		}
	}

	/**
	 * Get Gateway setting
	 *
	 * @param string $name Setting key.
	 * @return mixed
	 */
	public function get_option(string $name)
	{

		// Init plugin options.
		if (empty($this->plugin_options)) {
			$this->plugin_options = array(
				'id'          => $this->plugin_id,
				'plugin_id'   => $this->plugin_id,
				'version'     => $this->version,
				'plugin_url'  => $this->plugin_url,
				'plugin_path' => $this->plugin_path,
				'plugin_file' => $this->plugin_file,
				'assets_url'  => $this->assets_url,
			);
		}

		// Return plugin option.
		if (isset($this->plugin_options[$name])) {
			return $this->plugin_options[$name];
		}

		// Init gateway options.
		if (empty($this->gateway_options)) {

			$gateway = $this->gateway();

			if (empty($gateway)) {
				$this->log(
					array(
						'Get_settings without Gateway',
						debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
					)
				);

				return null;
			}

			$is_test = $gateway->get_option('is_test') === 'yes';

			$this->gateway_options = array(
				'gateway_id' => $gateway->id,
				'enabled'    => $gateway->get_option('enabled') === 'yes',
				'is_test'    => $is_test,
				'merchant_id'      => $gateway->get_option('merchant_id'),
				'api_key'   => $gateway->get_option($is_test ? 'test_api_key' : 'api_key'),
				'private_key'   => $gateway->get_option($is_test ? 'test_private_key' : 'private_key'),
				'channel'   => $gateway->get_option('channel'),
				'supported_payment_methods'   => $gateway->get_option('supported_payment_methods'),
				'icon_location'   => $gateway->get_option('icon_location'),
				'is_cancel_order'   => $gateway->get_option('is_cancel_order') === 'yes',
				'is_refund_order'   => $gateway->get_option('is_refund_order') === 'yes',
				'logging'    => $is_test || $gateway->get_option('logging') === 'yes',
			);
		}

		// Return gateway option.
		return $this->gateway_options[$name] ?? null;
	}

	/**
	 * Get Gateway instance.
	 *
	 * @return Gateway|null Gateway.
	 */
	public function gateway(): ?Gateway
	{
		return WC()->payment_gateways()->payment_gateways()[$this->plugin_id] ?? null;
	}

	/**
	 * Wrapper of wc_get_template function
	 *
	 * @param string $template Template name.
	 * @param array  $args     Arguments.
	 * @param bool   $return   Return or echo. Echo by default.
	 *
	 * @return bool|string
	 */
	public function include_template(string $template, $args = array(), $return = false)
	{
		if ($return) {
			ob_start();
		}

		wc_get_template(
			$template,
			$args,
			'',
			$this->plugin_path . 'templates/'
		);

		if ($return) {
			return ob_get_clean();
		}

		return true;
	}

	/**
	 * Check if template file exists
	 *
	 * @param string $template Template name.
	 *
	 * @return bool
	 */
	public function template_exists(string $template): bool
	{
		return file_exists($this->plugin_path . 'templates/' . $template);
	}

	/**
	 * Disable plugin
	 */
	public function disable_plugin()
	{
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		deactivate_plugins($this->plugin_file);
	}

	/**
	 * Add data to Woo logs
	 *
	 * @param array|string $data             Data to add to logs.
	 * @param string       $code_source      Source of log in code.
	 * @param string       $log_file_postfix Postfix for woo log file.
	 */
	public function log($data, string $code_source = '', string $log_file_postfix = '')
	{

		if (
			function_exists('wc_get_logger') &&
			((defined('WP_DEBUG') && WP_DEBUG) || $this->get_option('logging'))
		) {

			if (empty($code_source)) {
				$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]; //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace

				$code_source  = isset($backtrace['class']) ? $backtrace['class'] . '::' : '';
				$code_source .= $backtrace['function'] ?? '';
			}

			$data = array(
				'source' => $code_source,
				'data'   => $data,
			);

			wc_get_logger()->debug(
				print_r($data, true), // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				array('source' => $this->plugin_id . (empty($log_file_postfix) ? '' : "-$log_file_postfix"))
			);
		}
	}

	/**
	 * Get singleton instance
	 *
	 * @return Main
	 */
	public static function get_instance(): Main
	{
		static $instance_requested = false;

		if (true === $instance_requested && is_null(self::$instance)) {
			$message = 'Function beamcheckout() called in time of initialization main plugin class. Recursion prevented';

			if (defined('WP_DEBUG') && WP_DEBUG) {
				$message .= '<br>Stack trace for debugging:<br><pre>' . print_r( //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
					true
				) . '</pre>';
			}

			wp_die($message); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$instance_requested = true;

		if (is_null(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	// Add support for WooCommerce Blocks & Custom Order Tables
	public function beam_declare_cart_checkout_blocks_compatibility()
	{
		if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
		}
	}
}

require_once __DIR__ . '/main-class-shortcut.php';

beamcheckout();
