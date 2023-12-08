# PayPal REST Payment Module

The Payum extension to purchase through PayPal using REST API

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
        // Get client id and secret from https://developer.paypal.com/api/rest/
        'client_id' => 'username',
        'client_secret' => 'password',
        'img_url' => 'https://path/to/logo/image.jpg',
        'img_2_url' => 'https://path/to/logo/pay_by_image.jpg',
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
    // All items optional
    'invoice_id' => 'ABC-123',
    'shipping' => [
        'name' => [
            'full_name' => 'John Smith',
        ],
        'address' => [
            'address_line_1' => '1 Example Street',
            'address_line_2' => '',
            'admin_area_2' => 'City Name',
            'admin_area_1' => 'State Name',
            'postal_code' => 'Postal Code',
            'country_code' => 'Country', // e.g. US / GB / AU
        ],
    ],
    // If below are given, must add up (minus discounts) to the total amount
    'items' => [
        [
            'name' => 'DSLR Camera',
            'description' => 'Black Digital SLR',
            'sku' => 'sku01',
            'unit_amount' => [
                'currency_code' => 'USD',
                'value' => 150,
            ],
            'quantity' => 2,
            'category' => 'PHYSICAL_GOODS',
        ],
    ],
    'item_total' => 0, // Auto-calculated if items above exist
    'tax_total' => 0,
    'shipping_amount' => 0,
    'handling_amount' => 0,
    'insurance_amount' => 0,
    'shipping_discount_amount' => 0,
    'discount_amount' => 0,
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

### Set Shipping Tracking for a payment

```php
<?php
$gateway = $payum->getGateway('paypal_rest');
$gateway->execute($status = new \Cognito\PayumPayPalRest\Action\SetShippingTrackingAction([
    'transaction_id'  => '8UG60269FS070471K',
    'tracking_number' => '443844607830',
    'status'          => 'SHIPPED',
    'carrier'         => 'FEDEX',
]));

$success = $status['errors']
```

### Refund part or all of a transaction

```php
<?php
$gateway = $payum->getGateway('paypal_rest');

$storage = $payum->getStorage(\Payum\Core\Model\Payment::class);

// Fill in the transaction, currency and amount.
// Leave the amount blank or 0 to refund the whole transaction
$payment = $storage->create();
$payment->setNumber($transaction_id);
$payment->setCurrencyCode($currency_code);
$payment->setTotalAmount($amount);

$storage->update($payment);
$refundToken = $payum->getTokenFactory()->createRefundToken($this->processor, $payment, 'done');

$gateway->execute(new \Payum\Core\Request\Refund($refundToken));
$gateway->execute($status = new \Payum\Core\Request\GetHumanStatus($refundToken));
if ($status->isRefunded())
{
    return $status->getModel()['id'];
}
else
{
    throw new \Exception(json_encode($payment->getDetails()));
}
```

## License

Payum PayPal Rest is released under the [MIT License](LICENSE).
