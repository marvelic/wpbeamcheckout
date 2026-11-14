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
                'key' => $key
            )
        );

        return $this->authorization = 'Basic ' . base64_encode($merchant_id . ':' . $key);
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

        beamcheckout()->log(
            array(
                'url' => $url,
                'method' => $method,
                'body' => $body,
                'params' => $params,
            )
        );

        $res = wp_remote_request($url, $params);

        if (is_wp_error($res)) {
            beamcheckout()->log(
                array(
                    'error' => $res->get_error_message(),
                )
            );

            throw new Exception($res->get_error_message());
        }elseif (wp_remote_retrieve_response_code($res) > 299){
            throw new Exception($res['body']);
        }

        $res_body = json_decode($res['body'], true);

        beamcheckout()->log(
            array(
                'response' => $res,
            )
        );


        if (!empty($res['body']) && is_null($res_body)) {
            beamcheckout()->log(
                array(
                    'error' => 'wrong body',
                    'body' => $res['body'],
                )
            );

            throw new Exception(__('Api request decoding error', 'woocommerce-gateway-beamcheckout'));
        }

        return $res_body;
    }
}
