<?php

namespace Cognito\PayumPayPalRest\Request\Api;

use Payum\Core\Request\Generic;

class SetShippingTracking extends Generic
{
	protected $response;

	public function getResponse()
	{
		return $this->response;
	}

	public function setResponse($value)
	{
		$this->response = $value;
	}
}
