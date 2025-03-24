<?php

namespace Cognito\PayumPayPalRest\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\Capture;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Reply\HttpRedirect;

class CaptureAction implements ActionInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    private $config;

    /**
     * @param string $templateName
     */
    public function __construct(ArrayObject $config)
    {
        $this->config = $config;
    }

    /**
     * {@inheritDoc}
     *
     * @param Capture $request
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());
        if ($model['status']) {
            return;
        }
        $getHttpRequest = new GetHttpRequest();
        $this->gateway->execute($getHttpRequest);

        if ($getHttpRequest->method == 'GET' && isset($getHttpRequest->request['token'])) {
            if ($model['paypal_rest_token'] != $getHttpRequest->request['token']) {
                $model['status'] = 'error';
                $model['error']  = 'Token does not match';

                return;
            }
            // Do the call to PayPal to complete the payment
            $data   = (object) [];
            $result = $this->doPostRequest('/v2/checkout/orders/' . $getHttpRequest->request['token'] . '/capture', $data);

            if ($result['status'] == 'COMPLETED') {
                $model['status']               = 'success';
                $model['transactionReference'] = $result['id'];
                $model['result']               = 'success';

                return;
            }
            $model['status'] = 'error';
            $model['error']  = 'Payment was not approved: ' . $result['error'];

            return;
        }
        $payment_id = $model['merchant_reference'];

        $purchase_unit = [
            'reference_id' => $payment_id,
            'amount'       => [
                'currency_code' => $model['currency'],
                'value'         => $model['amount'],
            ],
        ];

        $shipping_preference = 'NO_SHIPPING';

        if ($model['order'] ?? false) {
            if ($model['order']['shipping'] ?? false) {
                $purchase_unit['shipping'] = [];
                if ($model['order']['shipping']['address']['city'] ?? false) {
                    $purchase_unit['shipping']['address'] = [
                        'address_line_1' => $model['order']['shipping']['address']['line1'],
                        'address_line_2' => $model['order']['shipping']['address']['line2'],
                        'admin_area_2'   => $model['order']['shipping']['address']['city'],
                        'admin_area_1'   => $model['order']['shipping']['address']['state'],
                        'postal_code'    => $model['order']['shipping']['address']['postal_code'],
                        'country_code'   => $model['order']['shipping']['address']['country'],
                    ];
                    $shipping_preference = 'SET_PROVIDED_ADDRESS';
                }
                if ($model['order']['shipping']['pickup'] ?? false) {
                    $purchase_unit['shipping']['type'] = 'PICKUP_IN_STORE';
                    $shipping_preference               = 'NO_SHIPPING';
                }
                if (!$purchase_unit['shipping']) {
                    unset($purchase_unit['shipping']);
                }
            }
        }
        $order_total = 0;
        foreach ($model['order']['items'] as $item) {
            $purchase_unit['items'][] = [
                'name'        => $item['name'],
                'quantity'    => $item['quantity'],
                'unit_amount' => [
                    'currency_code' => $model['currency'],
                    'value'         => $item['amount'],
                ],
            ];
            $order_total += $item['amount'] * $item['quantity'];
        }
        $order_total       = round($order_total, 2);
        $adjustment_amount = round($model['amount'] - $order_total, 2);
        if ($adjustment_amount) {
            // Create a discount / surcharge line
            $purchase_unit['items'][] = [
                'name'        => 'Adjustment',
                'quantity'    => 1,
                'unit_amount' => [
                    'currency_code' => $model['currency'],
                    'value'         => $adjustment_amount,
                ],
            ];
        }
        $purchase_unit['amount']['breakdown'] = [
            'item_total' => [
                'currency_code' => $model['currency'],
                'value'         => $order_total + $adjustment_amount,
            ],
        ];
        $data = [
            'intent'         => 'CAPTURE',
            'payment_source' => [
                'paypal' => [
                    'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                    'experience_context'        => [
                        'user_action'         => 'PAY_NOW',
                        'shipping_preference' => $shipping_preference,
                        'return_url'          => $request->getToken()->getTargetUrl(),
                        'cancel_url'          => $request->getToken()->getAfterUrl(),
                    ],
                ],
            ],
            'purchase_units' => [
                $purchase_unit,
            ],
        ];
        if ($model['shopper'] ?? false) {
            if ($model['shopper']['email'] ?? false) {
                $data['payment_source']['paypal']['email_address'] = $model['shopper']['email'];
            }
            if ($model['shopper']['first_name'] ?? false) {
                $data['payment_source']['paypal']['name'] = [
                    'given_name' => $model['shopper']['first_name'],
                    'surname'    => $model['shopper']['last_name'],
                ];
            }
            if ($model['shopper']['billing_address']['city'] ?? false) {
                $data['payment_source']['paypal']['address'] = [
                    'address_line_1' => $model['shopper']['billing_address']['line1'],
                    'address_line_2' => $model['shopper']['billing_address']['line2'],
                    'admin_area_2'   => $model['shopper']['billing_address']['city'],
                    'admin_area_1'   => $model['shopper']['billing_address']['state'],
                    'postal_code'    => $model['shopper']['billing_address']['postal_code'],
                    'country_code'   => $model['shopper']['billing_address']['country'],
                ];
            }
        }
        $returned_data = $this->doPostRequest('/v2/checkout/orders', $data);

        if ($returned_data['status'] != 'PAYER_ACTION_REQUIRED') {
            $model['status'] = 'error';
            $model['error']  = 'Could not create the order: ' . $returned_data['error'];

            return;
        }
        $model['paypal_rest_token'] = $returned_data['id'];

        foreach ($returned_data['links'] as $linkinfo) {
            if ($linkinfo['rel'] != 'payer-action') {
                continue;
            }

            throw new HttpRedirect($linkinfo['href']);
        }
    }

    /**
     * Get the site to use
     * @return string
     */
    public function baseurl()
    {
        if ($this->config['sandbox']) {
            return 'https://api-m.sandbox.paypal.com';
        }

        return 'https://api-m.paypal.com';
    }

    /**
     * Get authentication token for bearer string
     * @return string
     */
    public function getAuthToken()
    {
        static $token = '';

        if (!$token) {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => $this->baseurl() . '/v1/oauth2/token',
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
                CURLOPT_USERPWD        => $this->config['client_id'] . ':' . $this->config['client_secret'],
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: x-www-form-urlencoded',
                ],
            ]);
            $response = curl_exec($curl);
            $err      = curl_error($curl);

            curl_close($curl);

            if ($err) {
                throw new \Exception($err);
            }
            $responseData = json_decode($response, true);
            if (!array_key_exists('access_token', $responseData)) {
                throw new \Exception($response);
            }
            $token = $responseData['access_token'];
        }

        return $token;
    }

    /**
     * Perform POST request to PayPal servers
     * @param string $url relative path
     * @param string $data json encoded data
     * @return array
     */
    public function doPostRequest($url, $data)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => $this->baseurl() . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->getAuthToken(),
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
        ]);
        $response = curl_exec($curl);
        $err      = curl_error($curl);

        curl_close($curl);

        if ($err) {
            throw new \Exception($err);
        }

        return json_decode($response, true);
    }

    /**
     * Perform GET request to Airwallex servers
     * @param string $url relative path
     * @return array
     */
    public function doGetRequest($url)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => $this->baseurl() . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->getAuthToken(),
                'Content-Type: application/json',
            ],
        ]);
        $response = curl_exec($curl);
        $err      = curl_error($curl);

        curl_close($curl);

        if ($err) {
            throw new \Exception($err);
        }

        return json_decode($response, true);
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof \ArrayAccess;
    }
}
