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
            $orderDetails = $this->api->placeOrder([]);
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
