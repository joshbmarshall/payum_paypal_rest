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

class CaptureAction implements ActionInterface, GatewayAwareInterface {
    use GatewayAwareTrait;

    private $config;

    /**
     * @param string $templateName
     */
    public function __construct(ArrayObject $config) {
        $this->config = $config;
    }

    /**
     * {@inheritDoc}
     *
     * @param Capture $request
     */
    public function execute($request) {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());
        if ($model['status']) {
            return;
        }
        $getHttpRequest = new GetHttpRequest();
        $this->gateway->execute($getHttpRequest);
        if ($getHttpRequest->method == 'GET' && isset($getHttpRequest->request['status'])) {
            if ($model['paypal_rest_token'] != $getHttpRequest->request['token']) {
                $model['status'] = 'error';
                $model['error'] = 'Token does not match';
                return;
            }
            if ($getHttpRequest->request['status'] == 'CANCELLED') {
                $model['status'] = 'error';
                $model['error'] = 'Cancelled by customer';
                return;
            }
            if ($getHttpRequest->request['status'] == 'SUCCESS') {
                // Do the call to PayPal to complete the payment
                $data = [
                    'token' => $model['paypal_rest_token'],
                    'amount' => $model['amount'],
                    'currency' => $model['currency'],
                ];
                $result = $this->doPostRequest('/order/confirm', $data);
                if ($result['result'] == 'SUCCESS') {
                    $model['status'] = 'success';
                    $model['transactionReference'] = $result['orderId'];
                    $model['result'] = 'success';
                    return;
                }
                $model['status'] = 'error';
                $model['error'] = 'Payment was not approved: ' . $result['error'];
                return;
            }
            $model['status'] = 'error';
            $model['error'] = 'Unknown status: ' . $getHttpRequest->request['status'];
            return;
        }
        $payment_id = $model['merchant_reference'];

        $data = [
            'amount' => $model['amount'],
            'currency' => $model['currency'],
            'returnUrl' => $request->getToken()->getTargetUrl(),
            'merchantReference' => $payment_id,
            'customer' => [
                "firstName" => $model['shopper']['first_name'],
                "lastName" => $model['shopper']['last_name'],
                "email" => $model['shopper']['email'],
                "phone" => $model['shopper']['phone'],
            ],
            "billingAddress" => [
                "address1" => trim($model['shopper']['billing_address']['line1'] . ' ' . $model['shopper']['billing_address']['line2']),
                "city" => $model['shopper']['billing_address']['city'],
                "postcode" => $model['shopper']['billing_address']['postal_code'],
                "country" => $model['shopper']['billing_address']['country'],
            ],
            "shippingAddress" => [
                "address1" => trim($model['order']['shipping']['address']['line1'] . ' ' . $model['order']['shipping']['address']['line2']),
                "city" => $model['order']['shipping']['address']['city'],
                "postcode" => $model['order']['shipping']['address']['postal_code'],
                "country" => $model['order']['shipping']['address']['country'],
            ],
            "items" => [],
        ];
        $itemcnt = 0;
        $order_total = 0;
        foreach ($model['order']['items'] as $item) {
            $itemcnt++;
            $data['items'][] = [
                "id" => $payment_id . '-' . $itemcnt,
                "description" => $item['name'],
                "quantity" => $item['quantity'],
                "price" => $item['amount'],
            ];
            $order_total += $item['amount'];
        }
        $order_total = round($order_total, 2);
        if ($order_total != $model['amount']) {
            // Create a discount / surcharge line
            $itemcnt++;
            $data['items'][] = [
                "id" => $payment_id . '-' . $itemcnt,
                "description" => 'Adjustment',
                "quantity" => 1,
                "price" => round($model['amount'] - $order_total, 2),
            ];
        }

        $returned_data = $this->doPostRequest('/order/create', $data);
        if ($returned_data['result'] == 'ERROR') {
            $model['status'] = 'error';
            $model['error'] = 'Could not create the order: ' . $returned_data['error'];
            return;
        }
        $model['paypal_rest_token'] = $returned_data['token'];
        throw new HttpRedirect($returned_data['paymentUrl']);
    }

    /**
     * Get the site to use
     * @return string
     */
    public function baseurl() {
        if ($this->config['sandbox']) {
            return 'api-m.sandbox.paypal.com';
        } else {
            return 'https://api-m.paypal.com';
        }
    }

    /**
     * Perform POST request to PayPal servers
     * @param string $url relative path
     * @param string $data json encoded data
     * @return array
     */
    public function doPostRequest($url, $data) {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->baseurl() . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                'Authorization: Basic ' . base64_encode($this->config['merchantId'] . ':' . $this->config['authenticationKey']),
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            throw new \Exception($err);
        }
        return json_decode($response, true);
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request) {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof \ArrayAccess;
    }
}
