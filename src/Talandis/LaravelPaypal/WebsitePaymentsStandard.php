<?php

namespace Talandis\LaravelPaypal;

use Whoops\Exception\ErrorException;

class WebsitePaymentsStandard
{

    protected $items = array();

    protected $orderId = "";

    protected $requestUrl = "https://www.paypal.com/cgi-bin/webscr";

    protected $sandboxRequestUrl = 'https://www.sandbox.paypal.com/cgi-bin/webscr';

    protected $currency = 'EUR';

    protected $language = 'EN';

    protected $isSandbox = false;

    public function __construct()
    {

        $this->setConfiguration(  );

    }

    public function setIsSandbox()
    {
        $this->isSandbox = true;
    }

    public function setConfiguration( $name = null )
    {
        $configuration = config('paypal');

        $fieldsMap = array(
            'certificateId',
            'businessEmail',
            'privateKey',
            'publicKey',
            'paypalPublicKey',
            'passphrase',
            'currency',
            'language',
            'requestUrl',
            'isSandbox',
            'paymentDataTransferToken'
        );

        foreach ($fieldsMap as $configurationField) {
            if (isset($configuration[$configurationField])) {
                $this->$configurationField = $configuration[$configurationField];
            }
        }
    }

    public function setCallbackUrl($url)
    {
        $this->callbackUrl = $url;
    }

    public function setReturnUrl($url)
    {
        $this->returnUrl = $url;
    }

    public function setCancelUrl($url)
    {
        $this->cancelUrl = $url;
    }

    public function addItem($name, $price, $quantity = 1, $shipping = null)
    {
        $this->items[] = array(
            'amount' => $price,
            'item_name' => $name,
            'quantity' => $quantity,
            'shipping' => $shipping
        );
    }

    public function getCartUploadParams()
    {

        $buttonParams = array(
            "cmd" => "_cart",
            "business" => $this->businessEmail,
            "cert_id" => $this->certificateId,
            "charset" => "utf-8",
            "return" => $this->returnUrl,
            "cancel_return" => $this->cancelUrl,
            "notify_url" => $this->callbackUrl,
            "upload" => 1,
            "custom" => $this->orderId,
            "currency_code" => $this->currency,
        );


        foreach ($this->items as $k => $item) {

            $index = $k + 1;

            $buttonParams['amount_' . $index] = $item['amount'];
            $buttonParams['quantity_' . $index] = $item['quantity'];
            $buttonParams['item_name_' . $index] = $item['item_name'];

            if (!empty($item['shipping'])) {
                $buttonParams['shipping_' . $index] = $item['shipping'];
            }

        }

        return array(
            'cmd' => '_s-xclick',
            'encrypted' => $this->encryptButton($buttonParams, $this->publicKey, $this->privateKey, $this->passphrase, $this->paypalPublicKey)
        );
    }

    public function setOrderId($id)
    {
        $this->orderId = $id;
    }

    public function getRequestUrl()
    {
        return $this->isSandbox ? $this->sandboxRequestUrl : $this->requestUrl;
    }

    private function signAndEncrypt($dataStr_, $ewpCertPath_, $ewpPrivateKeyPath_, $ewpPrivateKeyPwd_, $paypalCertPath_)
    {
        $dataStrFile = realpath(tempnam('/tmp', 'pp_'));
        $fd = fopen($dataStrFile, 'w');
        if (!$fd) {
            $error = "Could not open temporary file $dataStrFile.";
            throw new \ErrorException($error, 0);
        }
        fwrite($fd, $dataStr_);
        fclose($fd);
        $signedDataFile = realpath(tempnam('/tmp', 'pp_'));

        if (!@openssl_pkcs7_sign($dataStrFile, $signedDataFile, "file://{$ewpCertPath_}", array("file://{$ewpPrivateKeyPath_}", $ewpPrivateKeyPwd_), array(), PKCS7_BINARY)) {
            unlink($dataStrFile);
            unlink($signedDataFile);
            $error = "Could not sign data: " . openssl_error_string();
            throw new \ErrorException($error, 0);
        }
        unlink($dataStrFile);

        $signedData = file_get_contents($signedDataFile);
        $signedDataArray = explode("\n\n", $signedData);
        $signedData = $signedDataArray[1];
        $signedData = base64_decode($signedData);

        unlink($signedDataFile);

        $decodedSignedDataFile = realpath(tempnam('/tmp', 'pp_'));
        $fd = fopen($decodedSignedDataFile, 'w');
        if (!$fd) {
            $error = "Could not open temporary file $decodedSignedDataFile.";
            throw new \ErrorException($error, 0);
        }
        fwrite($fd, $signedData);
        fclose($fd);

        $encryptedDataFile = realpath(tempnam('/tmp', 'pp_'));
        if (!@openssl_pkcs7_encrypt($decodedSignedDataFile, $encryptedDataFile, file_get_contents($paypalCertPath_), array(), PKCS7_BINARY)) {
            unlink($decodedSignedDataFile);
            unlink($encryptedDataFile);
            $error = "Could not encrypt data: " . openssl_error_string();
            throw new \ErrorException($error, 0);
        }

        unlink($decodedSignedDataFile);

        $encryptedData = file_get_contents($encryptedDataFile);
        if (!$encryptedData) {
            $error = "Encryption and signature of data failed.";
            throw new \ErrorException($error, 0);
        }

        unlink($encryptedDataFile);

        $encryptedDataArray = explode("\n\n", $encryptedData);
        $encryptedData = trim(str_replace("\n", '', $encryptedDataArray[1]));

        return $encryptedData;
    }

    private function encryptButton($buttonParams_, $ewpCertPath_, $ewpPrivateKeyPath_, $ewpPrivateKeyPwd_, $paypalCertPath_)
    {
        $contentBytes = array();
        foreach ($buttonParams_ as $name => $value) {
            $contentBytes[] = "$name=$value";
        }
        $contentBytes = implode("\n", $contentBytes);

        $encryptedDataReturn = $this->signAndEncrypt($contentBytes, $ewpCertPath_, $ewpPrivateKeyPath_, $ewpPrivateKeyPwd_, $paypalCertPath_);

        return "-----BEGIN PKCS7-----" . $encryptedDataReturn . "-----END PKCS7-----";
    }

    public function validatePDTRequest($tx)
    {

        $req = array(
            'cmd' => '_notify-synch',
            'tx' => $tx,
            'at' => $this->paymentDataTransferToken
        );

        $ch = curl_init($this->getRequestUrl());
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2 );
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1 );
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));

        $res = curl_exec($ch);
        if (curl_errno($ch) != 0) {
            throw new ErrorException('cURL Error: ' . curl_error($ch));
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return ($status == 200 AND strpos($res, 'SUCCESS') === 0);

    }

    public function validateIPNRequest($data)
    {

        $req = $this->getIpnRequestString($data);

        $ch = curl_init($this->getRequestUrl());
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2 );
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1 );
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));

        $res = curl_exec($ch);
        if (curl_errno($ch) != 0) {
            throw new ErrorException('cURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        $this->orderId = $data['custom'];

        return (strcmp($res, 'VERIFIED') == 0);
    }

    protected function getIpnRequestString($data)
    {
        $req = '';

        if (function_exists('get_magic_quotes_gpc')) {
            $get_magic_quotes_exists = true;
        }
        foreach ($data as $key => $value) {
            if ($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
                $value = urlencode(stripslashes($value));
            } else {
                $value = urlencode($value);
            }
            $req .= "$key=$value&";
        }

        $req .= 'cmd=_notify-validate';

        return $req;
    }

    public function getOrderId()
    {
        return $this->orderId;
    }

}
