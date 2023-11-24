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

class ObtainNonceAction implements ActionInterface, GatewayAwareInterface {
    use GatewayAwareTrait;


    /**
     * @var string
     */
    protected $templateName;

    /**
     * @param string $templateName
     */
    public function __construct(string $templateName) {
        $this->templateName = $templateName;
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
        $uri = \League\Uri\Http::createFromServer($_SERVER);

        $getHttpRequest = new GetHttpRequest();
        $this->gateway->execute($getHttpRequest);
        // Received payment intent information from Stripe
        if (isset($getHttpRequest->request['payment_intent'])) {
            $model['nonce'] = $getHttpRequest->request['payment_intent'];
            return;
        }
        $paymentIntentData = [
            'amount' => round($model['amount'] * pow(10, $model['currencyDigits'])),
            'shipping' => $model['shipping'],
            'currency' => $model['currency'],
            'metadata' => ['integration_check' => 'accept_a_payment'],
            'statement_descriptor' => $model['statement_descriptor_suffix'],
            'description' => $model['description'],
        ];

        $this->gateway->execute($renderTemplate = new RenderTemplate($this->templateName, array(
            'amount' => $model['currencySymbol'] . ' ' . number_format($model['amount'], $model['currencyDigits']),
            'actionUrl' => $uri->withPath('')->withFragment('')->withQuery('')->__toString() . $getHttpRequest->uri,
            'imgUrl' => $model['img_url'],
            'img2Url' => $model['img_2_url'],
            'billing' => $model['billing'] ?? [],
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
