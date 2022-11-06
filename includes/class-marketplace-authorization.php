<?php
class Marketplace_Authorization
{
    public $secret_key;
    public $options;
    public $ciphering;

    public function __construct()
    {
        $this->secret_key = _marketplace_get_api_credentials()['api_secret_key'];
        $this->options = 0;
        $this->ciphering = "AES-128-CTR";
    }
    public function encrypt( $string )
    {
        // Store the encryption key
        $encryption_key = "marketplace-library";

        return openssl_encrypt($string, $this->ciphering, $encryption_key, $this->options, $this->secret_key);
    }
    public function decrypt( $string )
    {
        // Store the decryption key
        $decryption_key = "marketplace-library";

        // Use openssl_decrypt() function to decrypt the data
        return openssl_decrypt($string, $this->ciphering, $decryption_key, $this->options, $this->secret_key);
    }
}

new Marketplace_Authorization();
