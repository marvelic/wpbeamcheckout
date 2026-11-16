<?php
/**
 * Api class
 *
 * @package Marvelic\BeamCheckout
 */

namespace Marvelic\BeamCheckout\Api;

use Exception;

defined('ABSPATH') || exit();

/**
 * Base API class
 *
 * @package Marvelic\BeamCheckout
 * @since   1.0.0
 */
abstract class Base
{
    /**
     * API base url
     *
     * @var string
     */
    private string $api_url = 'https://partner-api.beamdata.co/';

    /**
     * @var string
     */
    private string $test_api_url = 'https://playground.api.beamcheckout.com/';

    /**
     * Is test API
     *
     * @var bool
     */
    private bool $is_test;

    /**
     * @var string
     */
    protected string $authorization;

    /**
     * Api constructor.
     */
    public function __construct()
    {
        $this->is_test = beamcheckout()->get_option('is_test') ?? true;
    }

    /**
     * @param string $merchant_id
     * @param string $key
     * @return string
     */
    protected function authorization(string $merchant_id, string $key): string
    {
        beamcheckout()->log(
            array(
                'merchant_id' => $merchant_id,
                'key' => self::mask_secret($key),
            )
        );

        return $this->authorization = 'Basic ' . base64_encode($merchant_id . ':' . $key);
    }

    /**
     * Mask a secret for logs: never write credentials in full.
     *
     * @param string $secret Secret value.
     * @return string
     */
    protected static function mask_secret(string $secret): string
    {
        return '' === $secret ? '' : str_repeat('*', 8) . ' (' . strlen($secret) . ' chars)';
    }

    /**
     * @param string $endpoint
     * @return string
     */
    protected function get_api_url(string $endpoint): string
    {
        return (beamcheckout()->get_option('is_test') ? $this->test_api_url : $this->api_url) . $endpoint;
    }

    /**
     * API request
     *
     * @param string $endpoint api endpoint.
     * @param string $method http method.
     * @param array $body request body.
     *
     * @return array
     * @throws Exception If request error.
     */
    protected function request(string $endpoint, $method = 'GET', $body = array()): array
    {

        if(!$this->authorization = $this->authorization(beamcheckout()->get_option('merchant_id'), beamcheckout()->get_option('api_key'))){
            throw new Exception(__('Authorization data is not specified ', 'woocommerce-gateway-beamcheckout'));
        }

        $url = $this->get_api_url($endpoint);

        if ('GET' === $method) {
            $url = add_query_arg($body, $url);
        }

        $params = array(
            'body' => empty($body) || 'GET' === $method ? null : wp_json_encode($body),
            'method' => $method,
            'headers' => [
                'Authorization' => $this->authorization,
                'Content-Type' => 'application/json'
            ],
        );

        // Log only what is needed to debug (endpoint, status, request id). Never credentials,
        // customer data or the order key that is part of the redirect URL.
        $log_body = $body;
        if (isset($log_body['redirectUrl']) && is_string($log_body['redirectUrl'])) {
            $log_body['redirectUrl'] = strtok($log_body['redirectUrl'], '?');
        }

        beamcheckout()->log(
            array(
                'endpoint' => $endpoint,
                'method' => $method,
                'body' => $log_body,
            )
        );

        $res = wp_remote_request($url, $params);

        if (is_wp_error($res)) {
            beamcheckout()->log(
                array(
                    'endpoint' => $endpoint,
                    'error' => $res->get_error_message(),
                )
            );

            // Code 0 = no HTTP response (timeout, DNS, TLS ...).
            throw new Exception($res->get_error_message(), 0);
        }

        $status_code = (int) wp_remote_retrieve_response_code($res);
        $request_id  = wp_remote_retrieve_header($res, 'x-beam-request-id');

        if ($status_code > 299) {
            beamcheckout()->log(
                array(
                    'endpoint' => $endpoint,
                    'status' => $status_code,
                    'request_id' => $request_id,
                    'error' => substr((string) $res['body'], 0, 300),
                )
            );

            // The HTTP status is the exception code so callers can tell 429/5xx (retry later) from 4xx.
            throw new Exception($res['body'], $status_code);
        }

        $res_body = json_decode($res['body'], true);

        beamcheckout()->log(
            array(
                'endpoint' => $endpoint,
                'status' => $status_code,
                'request_id' => $request_id,
                'purchaseId' => is_array($res_body) ? ($res_body['purchaseId'] ?? null) : null,
                'state' => is_array($res_body) ? ($res_body['state'] ?? null) : null,
            )
        );


        if (!empty($res['body']) && is_null($res_body)) {
            beamcheckout()->log(
                array(
                    'endpoint' => $endpoint,
                    'status' => $status_code,
                    'request_id' => $request_id,
                    'error' => 'wrong body',
                )
            );

            throw new Exception(__('Api request decoding error', 'woocommerce-gateway-beamcheckout'));
        }

        return $res_body;
    }
}
