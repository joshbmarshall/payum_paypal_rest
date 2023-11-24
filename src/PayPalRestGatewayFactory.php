<?php
namespace Cognito\PayumPayPalRest;

use Cognito\PayumPayPalRest\Action\ConvertPaymentAction;
use Cognito\PayumPayPalRest\Action\CaptureAction;
use Cognito\PayumPayPalRest\Action\ObtainNonceAction;
use Cognito\PayumPayPalRest\Action\StatusAction;
use Cognito\PayumPayPalRest\Action\SetShippingTrackingAction;
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
            'payum.factory_name' => 'paypal_rest',
            'payum.factory_title' => 'paypal_rest',

            'payum.template.obtain_nonce' => "@PayumPayPalRest/Action/obtain_nonce.html.twig",

            'payum.action.capture' => function (ArrayObject $config) {
                return new CaptureAction($config);
            },
            'payum.action.status' => new StatusAction(),
            'payum.action.convert_payment' => new ConvertPaymentAction(),
            'payum.action.obtain_nonce' => function (ArrayObject $config) {
                return new ObtainNonceAction($config['payum.template.obtain_nonce']);
            },
            'payum.action.set_shipping_tracking' => function (ArrayObject $config) {
                return new SetShippingTrackingAction($config);
            },
        ]);

        if (false == $config['payum.api']) {
            $config['payum.default_options'] = array(
                'sandbox' => true,
            );
            $config->defaults($config['payum.default_options']);
            $config['payum.required_options'] = [];

            $config['payum.api'] = function (ArrayObject $config) {
                $config->validateNotEmpty($config['payum.required_options']);

                return new Api((array) $config, $config['payum.http_client'], $config['httplug.message_factory']);
            };
        }
        $payumPaths = $config['payum.paths'];
        $payumPaths['PayumPayPalRest'] = __DIR__ . '/Resources/views';
        $config['payum.paths'] = $payumPaths;
    }
}
