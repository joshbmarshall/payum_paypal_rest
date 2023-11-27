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
        // TODO
        $request = '{
            "intent": "CAPTURE",
            "purchase_units": [
              {
                "reference_id": "'.uniqid('R').'",
                "description": "Camera Shop",
                "invoice_id": "INV-CameraShop-'.uniqid('I').'",
                "custom_id": "CUST-CameraShop",
                "amount": {
                  "currency_code": "USD",
                  "value": 350,
                  "breakdown": {
                    "item_total": {
                      "currency_code": "USD",
                      "value": 300
                    },
                    "shipping": {
                      "currency_code": "USD",
                      "value": 20
                    },
                    "tax_total": {
                      "currency_code": "USD",
                      "value": 30
                    }
                  }
                },
                "items": [
                  {
                    "name": "DSLR Camera",
                    "description": "Black Camera - Digital SLR",
                    "sku": "sku01",
                    "unit_amount": {
                      "currency_code": "USD",
                      "value": 300
                    },
                    "quantity": "1",
                    "category": "PHYSICAL_GOODS"
                  }
                ],
                "shipping": {
                  "address": {
                    "address_line_1": "2211 North Street",
                    "address_line_2": "",
                    "admin_area_2": "San Jose",
                    "admin_area_1": "CA",
                    "postal_code": "95123",
                    "country_code": "US"
                  }
                }
              }
            ],
            "application_context": {
              "shipping_preference": "SET_PROVIDED_ADDRESS",
              "user_action": "PAY_NOW"
            }
          }';
        return $this->doRequest('/v2/checkout/orders', json_decode($request, true));
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
        ray($decoded);
        /*
        if (false == ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300))
        {
            throw HttpException::factory($request, $response);
        }
        */

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
