<?php

namespace Cognito\PayumPayPalRest\Action;

use Cognito\PayumPayPalRest\Request\Api\ObtainNonce;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\LogicException;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\RenderTemplate;
use Cognito\PayumPayPalRest\Api;

class ObtainNonceAction implements ActionInterface, GatewayAwareInterface, \Payum\Core\ApiAwareInterface  {
    use GatewayAwareTrait;
    use \Payum\Core\ApiAwareTrait;


    /**
     * @var string
     */
    protected $templateName;

    /**
     * @param string $templateName
     */
    public function __construct(string $templateName) {
        $this->templateName = $templateName;
        $this->apiClass = Api::class;
    }

    /**
     * {@inheritDoc}
     */
    public function execute($request) {
        /** @var $request ObtainNonce */
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        if ($model['card']) {
            throw new LogicException('The token has already been set.');
        }
        if (!$model->offsetExists('payPalOrderDetails')) {
            $purchase_unit = [
                'description' => $model['description'],
                'soft_descriptor' => $model['description'],
                'amount' => [
                    'currency_code' => $model['currency'],
                    'value' => $model['amount'],
                ],
            ];
            if ($model['invoice_id']) {
                $purchase_unit['invoice_id'] = $model['invoice_id'];
            }
            if ($model['shipping']) {
                $purchase_unit['shipping'] = $model['shipping'];
            }
            if ($model['items']) {
                $purchase_unit['items'] = $model['items'];
                $total_item_cost = 0;
                foreach ($model['items'] as $item) {
                    $total_item_cost += $item['unit_amount']['value'] * $item['quantity'];
                }
                $purchase_unit['amount']['breakdown'] = [
                    'item_total' => [
                        'currency_code' => $model['currency'],
                        'value' => $total_item_cost,
                    ],
                ];
            }
            if ($model['item_total']) {
                $purchase_unit['amount']['breakdown']['item_total'] = [
                    'currency_code' => $model['currency'],
                    'value' => $model['item_total'],
                ];
            }
            if ($model['tax_total']) {
                $purchase_unit['amount']['breakdown']['tax_total'] = [
                    'currency_code' => $model['currency'],
                    'value' => $model['tax_total'],
                ];
            }
            if ($model['shipping_amount']) {
                $purchase_unit['amount']['breakdown']['shipping'] = [
                    'currency_code' => $model['currency'],
                    'value' => $model['shipping_amount'],
                ];
            }
            if ($model['handling_amount']) {
                $purchase_unit['amount']['breakdown']['handling'] = [
                    'currency_code' => $model['currency'],
                    'value' => $model['handling_amount'],
                ];
            }
            if ($model['insurance_amount']) {
                $purchase_unit['amount']['breakdown']['insurance'] = [
                    'currency_code' => $model['currency'],
                    'value' => $model['insurance_amount'],
                ];
            }
            if ($model['shipping_discount_amount']) {
                $purchase_unit['amount']['breakdown']['shipping_discount'] = [
                    'currency_code' => $model['currency'],
                    'value' => $model['shipping_discount_amount'],
                ];
            }
            if ($model['discount_amount']) {
                $purchase_unit['amount']['breakdown']['discount'] = [
                    'currency_code' => $model['currency'],
                    'value' => $model['discount_amount'],
                ];
            }
            $request = array_merge([
                'intent' => 'CAPTURE',
                'purchase_units' => [$purchase_unit],
            ], $model['order'] ?? []);

            $orderDetails = $this->api->placeOrder($request);
            if ($orderDetails['name']) {
                // Maybe an error like mismatching amounts
                throw new \Exception(json_encode($orderDetails['details']));
            }
            $model['payPalOrderDetails'] = $orderDetails;
        }

        $uri = \League\Uri\Http::createFromServer($_SERVER);

        $this->gateway->execute($getHttpRequest = new GetHttpRequest());
        // Received paymentID from PayPal
        if (isset($getHttpRequest->request['payment_id'])) {
            $model['nonce'] = $getHttpRequest->request['payment_id'];
            return;
        }

        $this->gateway->execute($renderTemplate = new RenderTemplate($this->templateName, array(
            'actionUrl' => $uri->withPath('')->withFragment('')->withQuery('')->__toString() . $getHttpRequest->uri,
            'imgUrl' => $model['img_url'],
            'img2Url' => $model['img_2_url'],
            'currency' => $model['currency'],
            'client_id' => $model['client_id'],
            'order_id' => $model['payPalOrderDetails']['id'],
        )));

        throw new HttpResponse($renderTemplate->getResult());
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request) {
        return
            $request instanceof ObtainNonce &&
            $request->getModel() instanceof \ArrayAccess;
    }
}
