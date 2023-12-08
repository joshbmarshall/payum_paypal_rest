<?php

namespace Cognito\PayumPayPalRest;

use Http\Message\MessageFactory;
use Payum\Core\Exception\Http\HttpException;
use Payum\Core\HttpClientInterface;

class Api
{
    /**
     * @var HttpClientInterface
     */
    protected $client;

    /**
     * @var MessageFactory
     */
    protected $messageFactory;

    /**
     * @var array
     */
    protected $options = [];

    private $accessToken = '';

    /**
     * @param array               $options
     * @param HttpClientInterface $client
     * @param MessageFactory      $messageFactory
     *
     * @throws \Payum\Core\Exception\InvalidArgumentException if an option is invalid
     */
    public function __construct(array $options, HttpClientInterface $client, MessageFactory $messageFactory)
    {
        $this->options = $options;
        $this->client = $client;
        $this->messageFactory = $messageFactory;
    }

    public function refund(string $transaction_id, $amount = null, $currency_code = '')
    {
        $request = [];
        if ($amount)
        {
            $request['amount'] = [
                'value' => $amount,
                'currency_code' => $currency_code,
            ];
        }
        return array_merge(['isRefund' => true], $this->doRequest('/v2/payments/captures/' . $transaction_id . '/refund', $request));
    }

    public function setShippingTracking(array $fields)
    {
        /*
            $fields = [
                'transaction_id'  => '8MC585209K746392H',
                'tracking_number' => '443844607820',
                'status'          => 'SHIPPED',
                'carrier'         => 'FEDEX',
            ];
        */
        $request = [
            'trackers' => [$fields],
        ];
        return $this->doRequest('/v1/shipping/trackers-batch', $request);
    }

    public function captureOrder(string $order_id)
    {
        return $this->doRequest('/v2/checkout/orders/' . $order_id . '/capture');
    }

    public function placeOrder(array $fields)
    {
        return $this->doRequest('/v2/checkout/orders', $fields);
    }

    private function getAuthorizationBearer()
    {
        if ($this->accessToken)
        {
            return $this->accessToken;
        }

        // Log in
        $request = $this->messageFactory->createRequest('post', $this->getApiEndpoint() . '/v1/oauth2/token', [
            'Authorization' => 'Basic ' . base64_encode($this->options['client_id'] . ':' . $this->options['secret']),
        ], http_build_query(['grant_type' => 'client_credentials']));

        $response = $this->client->send($request);

        if (false == ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300))
        {
            throw HttpException::factory($request, $response);
        }

        $decoded = json_decode($response->getBody()->getContents());

        $this->accessToken = $decoded->access_token ?? '';

        return $this->accessToken;
    }

    /**
     * @param array $fields
     *
     * @return array
     */
    protected function doRequest(string $url, array $fields = [], string $method = 'POST')
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->getAuthorizationBearer(),
        ];

        $request = $this->messageFactory->createRequest($method, $this->getApiEndpoint() . $url, $headers, $fields ? json_encode($fields) : null);

        $response = $this->client->send($request);

        $decoded = json_decode($response->getBody()->getContents(), true);

        return $decoded;
    }

    /**
     * @return string
     */
    protected function getApiEndpoint()
    {
        return $this->options['sandbox'] ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }
}
