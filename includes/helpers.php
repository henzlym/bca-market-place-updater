<?php
function _marketplace_get_option_field($group = null, $field = null){
    if ( $group === null) return false;
    $option = isset($group) ? get_option($group) : false;
    return ($field !== null && isset($option[$field])) ? $option[$field] : $option;
}
function _marketplace_get_api_credentials()
{
	$api_credentials = array(
		'username' => false,
		'api_key' => false,
		'api_domain' => ''
	);
	if ($extensions_general = _marketplace_get_option_field('marketplace_general')) {
		$api_username = ( isset($extensions_general['api_username']) ) ? $extensions_general['api_username'] : false;
		$api_key = ( isset($extensions_general['api_key']) ) ? $extensions_general['api_key'] : false;
		$api_domain = ( isset($extensions_general['api_domain']) ) ? $extensions_general['api_domain'] : false;
		if ( $api_username && $api_key ) {
			$api_credentials = array(
				'username' => $api_username,
				'api_key' => $api_key,
				'api_domain' => $api_domain
			);
		}
	}
	return $api_credentials;
}
