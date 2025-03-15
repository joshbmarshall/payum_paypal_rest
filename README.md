# PayPal REST Payment Module

The Payum extension to purchase through PayPal using their REST interface

## Install and Use

To install, it's easiest to use composer:

    composer require cognito/payum_paypal_rest

### Build the config

```php
<?php

use Payum\Core\PayumBuilder;
use Payum\Core\GatewayFactoryInterface;

$defaultConfig = [];

$payum = (new PayumBuilder)
    ->addGatewayFactory('paypal_rest', function(array $config, GatewayFactoryInterface $coreGatewayFactory) {
        return new \Cognito\PayumPayPalRest\PayPalRestGatewayFactory($config, $coreGatewayFactory);
    })

    ->addGateway('paypal_rest', [
        'factory' => 'paypal_rest',
        'merchantId' => 'Your merchant Id',
        'authenticationKey' => 'Your Authentication Key',
        'sandbox' => true,
    ])

    ->getPayum()
;
```

### Request payment

```php
<?php

use Payum\Core\Request\Capture;

$storage = $payum->getStorage(\Payum\Core\Model\Payment::class);
$request = [
    'invoice_id' => 100,
];

$payment = $storage->create();
$payment->setNumber(uniqid());
$payment->setCurrencyCode($currency);
$payment->setTotalAmount(100); // Total cents
$payment->setDescription(substr($description, 0, 45));
$payment->setDetails([
    'local' => [
    ],
    'merchant_reference' => 'MYREF',
    'shopper' => [
        'first_name' => '',
        'last_name'] => '',
        'email' => '',
        'phone' => '',
        'billing_address' => [
            'line1' => '',
            'line2' => '',
            'city' => '',
            'postal_code' => '',
            'country' => '',
        ],
    ],
    'order' => [
        'shipping' => [
            'address' => [
            'line1' => '',
            'line2' => '',
            'city' => '',
            'postal_code' => '',
            'country' => '',
        ],
        'items' => [
            [
                'name' => 'Product 1',
                'amount' => 49.95,
                'quantity' => 2,
            ],
            [
                'name' => 'Shipping',
                'amount' => 9.95,
                'quantity' => 1,
            ],
        ],
    ],
]);
$storage->setInternalDetails($payment, $request);

$captureToken = $payum->getTokenFactory()->createCaptureToken('paypal_rest', $payment, 'done.php');
$url = $captureToken->getTargetUrl();
header("Location: " . $url);
die();
```

### Check it worked

```php
<?php
/** @var \Payum\Core\Model\Token $token */
$token = $payum->getHttpRequestVerifier()->verify($request);
$gateway = $payum->getGateway($token->getGatewayName());

/** @var \Payum\Core\Storage\IdentityInterface $identity **/
$identity = $token->getDetails();
$model = $payum->getStorage($identity->getClass())->find($identity);
$gateway->execute($status = new GetHumanStatus($model));

/** @var \Payum\Core\Request\GetHumanStatus $status */

// using shortcut
if ($status->isNew() || $status->isCaptured() || $status->isAuthorized()) {
    // success
} elseif ($status->isPending()) {
    // most likely success, but you have to wait for a push notification.
} elseif ($status->isFailed() || $status->isCanceled()) {
    // the payment has failed or user canceled it.
}
```

## License

Payum PayPal Rest is released under the [MIT License](LICENSE).
