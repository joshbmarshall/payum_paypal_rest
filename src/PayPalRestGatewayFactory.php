<?php

namespace Cognito\PayumPayPalRest;

use Cognito\PayumPayPalRest\Action\ConvertPaymentAction;
use Cognito\PayumPayPalRest\Action\CaptureAction;
use Cognito\PayumPayPalRest\Action\StatusAction;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;

class PayPalRestGatewayFactory extends GatewayFactory
{
    /**
     * {@inheritDoc}
     */
    protected function populateConfig(ArrayObject $config)
    {
        $config->defaults([
            'payum.factory_name'  => 'paypal_rest',
            'payum.factory_title' => 'paypal_rest',

            'payum.action.capture' => function (ArrayObject $config) {
                return new CaptureAction($config);
            },
            'payum.action.status'          => new StatusAction(),
            'payum.action.convert_payment' => new ConvertPaymentAction(),
        ]);
    }
}
