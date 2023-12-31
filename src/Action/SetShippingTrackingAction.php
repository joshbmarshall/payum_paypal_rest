<?php

namespace Cognito\PayumPayPalRest\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\ApiAwareInterface;
use Payum\Core\ApiAwareTrait;
use Cognito\PayumPayPalRest\Api;

/**
 * @property Api $api
 */
class SetShippingTrackingAction implements ActionInterface, ApiAwareInterface
{
    use ApiAwareTrait;


    /**
     * @var ArrayObject
     */
    protected $model;

    /**
     * @param string $templateName
     */
    public function __construct($model)
    {
        $this->model = new ArrayObject($model);
        $this->apiClass = Api::class;
    }

    /**
     * Call this after execution to see if any error
     */
    public function hasError(): bool {
        return count($this->getErrors()) > 0;
    }

    /**
     * Call this after execution to get the error list
     */
    public function getErrors(): array {
        return $this->model['errors'] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);
        $model = ArrayObject::ensureArrayObject($request->model);

        $model->replace(
            $this->api->setShippingTracking((array) $model)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof SetShippingTrackingAction;
    }
}
