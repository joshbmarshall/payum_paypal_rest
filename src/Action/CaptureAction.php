<?php

namespace Cognito\PayumPayPalRest\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\ApiAwareTrait;
use Payum\Core\Request\Capture;
use Cognito\PayumPayPalRest\Request\Api\ObtainNonce;
use Cognito\PayumPayPalRest\Api;

class CaptureAction implements ActionInterface, GatewayAwareInterface, \Payum\Core\ApiAwareInterface {
    use GatewayAwareTrait;
    use ApiAwareTrait;

    private $config;

    /**
     * @param string $templateName
     */
    public function __construct(ArrayObject $config) {
        $this->config = $config;
        $this->apiClass = Api::class;
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

        $model['img_url'] = $this->config['img_url'] ?? '';
        $model['img_2_url'] = $this->config['img_2_url'] ?? '';
        $model['client_id'] = $this->config['client_id'] ?? '';

        $obtainNonce = new ObtainNonce($request->getModel());
        $obtainNonce->setModel($model);

        $this->gateway->execute($obtainNonce);

        if (!$model->offsetExists('status')) {
            // Check if user cancelled
            if ($model['nonce'] == 'cancel') {
                $model['status'] = 'failed';
                $model['error'] = 'Cancelled';
                return;
            }

            // Ask PayPal to capture funds
            $captureResult = $this->api->captureOrder($model['payPalOrderDetails']['id']);
            $model['result'] = $captureResult;

            if ($captureResult['status'] == 'COMPLETED') {
                // Capture successful
                $model['status'] = 'success';
                foreach ($captureResult['purchase_units'] as $purchase_unit) {
                    foreach ($purchase_unit['payments']['captures'] as $capture) {
                        $model['transactionReference'] = $capture['id'];
                        $model['PAYMENTINFO_0_FEEAMT'] = $capture['seller_receivable_breakdown']['paypal_fee']['value'];
                    }
                }
            } else {
                $model['status'] = 'failed';
                $model['error'] = $captureResult['name'] . ' ' . $captureResult['message'];
            }
        }
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
