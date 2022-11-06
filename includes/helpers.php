<?php
function _marketplace_get_option_field($group = null, $field = null){
    if ( $group === null) return false;
    $option = isset($group) ? get_option($group) : false;
    return ($field !== null && isset($option[$field])) ? $option[$field] : $option;
}
function _marketplace_get_api_credentials()
{
	$api_credentials = array(
		'api_authorization_token' => false,
		'api_secret_key' => false,
		'api_domain' => ''
	);
	if ($extensions_general = _marketplace_get_option_field('marketplace_general')) {
		$api_authorization_token = ( isset($extensions_general['api_authorization_token']) ) ? $extensions_general['api_authorization_token'] : false;
		$api_secret_key = ( isset($extensions_general['api_secret_key']) ) ? $extensions_general['api_secret_key'] : false;
		$api_domain = ( isset($extensions_general['api_domain']) ) ? $extensions_general['api_domain'] : false;
		$api_credentials = array(
			'api_authorization_token' => $api_authorization_token,
			'api_secret_key' => $api_secret_key,
			'api_domain' => $api_domain
		);
	}
	return $api_credentials;
}
function _marketplace_api_request_is_error( $response )
{
	$errors = array(
		'incorrect_password'
	);

	if (isset($response->code) && in_array($response->code, $errors ) ) {
		if (isset($response->message) && $response->message ) {
			$type = 'error';
			$message = __( $response->message, 'marketplace' );
			$errors = get_settings_errors( 'marketplace_updater_api_notices' );

			if (empty($errors)) {
				add_settings_error('marketplace_updater_api_notices', 'marketplace_updater_settings_message',$message, $type);
			}

		}
		return true;
	}

	return false;
}
