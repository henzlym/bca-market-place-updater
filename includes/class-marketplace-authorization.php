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
    public function create_secret_key()
    {
        update_option('marketplace-library-secret-key', $this->generate_uuid4() );
    }
    /**
     * Generate a random UUID (version 4).
     *
     * @since 4.7.0
     *
     * @return string UUID.
     */
    public function generate_uuid4() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff )
        );
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
    public function authorize_plugin()
    {
        $admin_url = admin_url( 'authorize-application.php' );
        $auth_url = add_query_arg( array( 
            'app_name' => 'Market Place',
            'app_id' => $this->generate_uuid4(),
            // 'success_url' => admin_url( 'admin.php?page=marketplace-general' )
        ), $admin_url );

        wp_redirect( $auth_url, 301 );
        exit;
    }
}

new Marketplace_Authorization();
