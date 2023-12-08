<?php

namespace Cognito\PayumPayPalRest\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\LogicException;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Cognito\PayumPayPalRest\Api;
use Payum\Core\Request\Refund;

class RefundAction implements ActionInterface, ApiAwareInterface
{
	/**
	 * @var Api
	 */
	protected $api;

	/**
	 * {@inheritDoc}
	 */
	public function setApi($api)
	{
		if (false == $api instanceof Api)
		{
			throw new UnsupportedApiException('Not supported.');
		}

		$this->api = $api;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute($request)
	{
		/** @var $request Refund */
		RequestNotSupportedException::assertSupports($this, $request);

		$model = ArrayObject::ensureArrayObject($request->getModel());
		$firstModel = $request->getFirstModel();

		$model->replace(
			$this->api->refund($firstModel->getNumber(), $firstModel->getTotalAmount(), $firstModel->getCurrencyCode())
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports($request)
	{
		return
			$request instanceof Refund &&
			$request->getModel() instanceof \ArrayAccess;
	}
}
