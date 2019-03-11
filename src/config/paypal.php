<?php

return array(
    'isSandbox' => env('PAYPAL_SANDBOX'),
    'certificateId' => env('PAYPAL_CERTIFICATE_ID'),
    'businessEmail' => env('PAYPAL_BUSINESS_EMAIL'),
    'privateKey' => config_path(env('PAYPAL_PRIVATE_KEY_PATH')),
    'publicKey' => config_path(env('PAYPAL_PUBLIC_KEY_PATH')),
    'paypalPublicKey' => config_path(env('PAYPAL_CERTIFICATE_PATH')),
    'passphrase' => env('PAYPAL_PASSPHRASE'),
    'paymentDataTransferToken' => env('PAYPAL_PDT_TOKEN'),
);
