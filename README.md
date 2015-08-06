# Laravel Bundle for PayPal Website Payments Standard

### Installation


Require this package with composer:

```
composer require talandis/laravel-paypal
```

### Configuration

After updating composer, add the ServiceProvider to the providers array in config/app.php

```
'Talandis\LaravelPaypal\LaravelPaypalServiceProvider',
```

Copy the package config to your local config with the publish command:

```
php artisan config:publish talandis/laravel-paypal
```

Don't forget to enter your certificates and other details into configuration files.
Paypal public certificate is not included. You have to download it manually.

### Usage

#### Payment requests

Below is a simple sample of payment request.

```php

$paypal = new \Talandis\LaravelPaypal\WebsitePaymentsStandard();

$paypal->setOrderId( 15 );
$paypal->addItem( 'Beer', 10.99 );
$paypal->setReturnUrl(URL::to('paypal/return'));
$paypal->setCallbackUrl(URL::to('paypal/callback'));
$paypal->setCancelUrl(URL::to('paypal/cancel')));

$requestData = $paypal->getCartUploadParams();
$requestUrl = $paypal->getRequestUrl();
```

Sample form

```html
<form action="<?php echo $requestUrl ?>" method="post">
    <?php foreach ( $requestData as $fieldName => $value ): ?>
      <input type="hidden" name="<?php echo $fieldName ?>" value="<? echo $value ?>" />
    <?php endforeach; ?>
    <input type="submit" value="Make payment" />
</form>
```

#### Payment Data Transfer (PDT) validation

You will need additional PDF token from PayPal.

```php
$paypal = new \Talandis\LaravelPaypal\WebsitePaymentsStandard();

if ( $paypal->validatePDTRequest( Input::get('tx') ) ) {

}
```

#### Instant Payment Notification (IPN) validation

```php
$paypal = new \Talandis\LaravelPaypal\WebsitePaymentsStandard();

if ( $paypal->validateIPNRequest( Input::all() ) ) {

}
```

#### Multiple configurations per site

If you have several PayPal merchant accounts and need to use them depending on some conditions there is an optional method. You have to call setConfiguration() method and pass a configuration name. You have to call this method before getting request parameters. If setConfiguration method is not called first configuration will be used.

```php
$paypal = new \Talandis\LaravelPaypal\WebsitePaymentsStandard();

$paypal->setConfiguration( 'custom_configuration' );
$paypal->setOrderId( 15 );
$paypal->addItem( 'Beer', 10.99 );
$paypal->setReturnUrl(URL::to('paypal/return'));
$paypal->setCallbackUrl(URL::to('paypal/callback'));
$paypal->setCancelUrl(URL::to('paypal/cancel')));

$requestData = $paypal->getCartUploadParams();
$requestUrl = $paypal->getRequestUrl();
```

