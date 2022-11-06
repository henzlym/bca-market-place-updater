<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

if (!class_exists('Marketplace_Updater')) {

	class Marketplace_Updater
	{

		public $plugin_slug;
		public $version;
		public $cache_key;
		public $themes_cache_key;
		public $cache_allowed;
		public $wp_rest_plugins_url;
		public $themes;

		public function __construct()
		{
			$this->plugin_slug = plugin_basename(__DIR__);
			$this->version = '1.0';
			$this->cache_key = 'marketplace_extensions_plugins_updater_cache';
			$this->plugins_cache_key = 'extensions_update_plugins';
			$this->themes_cache_key = 'extensions_update_themes';
			$this->cache_allowed = true;
			$this->cache_time = MINUTE_IN_SECONDS;
			$this->wp_rest_plugins_url = '';
			$api_credentials = _marketplace_get_api_credentials();
			if ($api_credentials['api_domain']) {
				$this->wp_rest_plugins_url = $api_credentials['api_domain'];
			}
			add_action('init', array($this, 'init'));

		}
		public function init()
		{
			add_filter('http_request_reject_unsafe_urls', array($this, 'filter_reject_unsafe_urls'), 10, 2);
			add_filter('plugins_api', array($this, 'set_package_info'), 20, 3);
			add_filter('plugins_api_result', array($this, 'set_package_info_results'), 20, 3);
			add_filter('themes_api', array($this, 'set_package_info'), 20, 3);
			add_filter('themes_api_result', array($this, 'set_package_info_results'), 10, 3);
			add_filter('site_transient_update_plugins', array($this, 'get_package_updates'), 10, 2);
			add_filter('site_transient_update_themes', array($this, 'get_package_updates'), 10, 2);
			add_action('upgrader_process_complete', array($this, 'purge_transient_cache'), 10, 2);
		}
		public function build_query_themes_object( $queried_theme, $action )
		{
			if (!is_object($queried_theme)||empty($queried_theme)||empty($action)) return false;

			$theme = new stdClass();

			if ( $action === 'query_themes' ) {
				$queried_theme->package->query_themes->author = (array) $queried_theme->package->query_themes->author;
				$theme =(object) array_merge(
					(array) $queried_theme->package->default,
					(array) $queried_theme->package->query_themes
				);

				return $theme;
			}

			if ( $action === 'theme_information' ) {
				$theme =(object) array_merge(
					(array) $queried_theme->package->default,
					(array) $queried_theme->package->theme_information
				);
				return $theme;
			}

			if ($action == 'update') {
				$theme = (array) $queried_theme->package->update;
				return $theme;
			}

			return false;
		}
		public function build_plugins_query_object( $queried_plugin, $action )
		{
			if (!is_object($queried_plugin)||empty($queried_plugin)||empty($action)) return false;

			$plugin = new stdClass();

			if ( $action === 'query_plugins' ) {
				$queried_plugin->package->query_plugins->icons = (array) $queried_plugin->package->default->icons;
				$queried_plugin->package->query_plugins->banners = (array) $queried_plugin->package->default->banners;
				$plugin = $queried_plugin->package->query_plugins;

				return $plugin;
			}

			if ( $action === 'plugin_information' ) {
				$queried_plugin->package->plugin_information->icons = (array) $queried_plugin->package->plugin_information->icons;
				$queried_plugin->package->plugin_information->banners = (array) $queried_plugin->package->plugin_information->banners;
				$plugin = (object) $queried_plugin->package->plugin_information;
				return $plugin;
			}

			if ($action == 'update') {
				$plugin = $queried_plugin->package->update;
				return $plugin;
			}

			return false;
		}

		public function set_reject_unsafe_urls_false($args)
		{
			$args['reject_unsafe_urls'] = false;
			return $args;
		}

		public function filter_reject_unsafe_urls($pass_url, $url)
		{
			$parsed_url = wp_parse_url($url);
			$wp_rest_plugins_url = wp_parse_url($this->wp_rest_plugins_url);

			remove_filter('http_request_args', array($this, 'set_reject_unsafe_urls_false'));

			if ($parsed_url['host'] == $wp_rest_plugins_url['host']) {
				add_filter('http_request_args', array($this, 'set_reject_unsafe_urls_false'));
			}

			return $pass_url;
		}

		public function set_package_info( $res, $action, $args )
		{
			if ( !('theme_information' !== $action || 'plugin_information' !== $action) ) return $res;

			$packages = array();
			$packages = ( 'theme_information' == $action ) ? $this->themes_api_request() : $packages;
			$packages = ( 'plugin_information' == $action ) ? $this->plugins_api_request() : $packages;

			if (empty($packages)) return $res;
			if (!isset($packages->{$args->slug})) return $res;

			$package = $packages->{$args->slug};
			$res = ( 'theme_information' == $action ) ? $this->build_query_themes_object( $package, $action ) : $package;
			$res = ( 'plugin_information' == $action ) ? $this->build_plugins_query_object( $package, $action ) : $package;
			if ( 'plugin_information' == $action ) {
				$res->language_packs = array();
			}

			return $res;

		}
		public function set_package_info_results( $res, $action, $args )
		{
			if ( !('query_themes' !== $action || 'query_plugins' !== $action) ) return $res;

			$packages = array();
			$packages = ( 'query_themes' == $action ) ? $this->themes_api_request() : $packages;
			$packages = ( 'query_plugins' == $action ) ? $this->plugins_api_request() : $packages;

			if (empty($packages)) return $res;

			foreach ( $packages as $key => $package ) {

				$package = ( 'query_themes' == $action ) ? $this->build_query_themes_object( $package, $action ) : $package;
				$package = ( 'query_plugins' == $action ) ? $this->build_plugins_query_object( $package, $action ) : $package;

				if (empty($package) || !isset($package->tpd) ) continue;

				array_unshift( $res->{str_replace('query_','',$action)}, $package);
			}

			return $res;

		}
		public function api_request($wp_rest_url, $associative = false, $cache_key = 'extensions_update_plugins')
		{
			$remote = get_transient($cache_key);
			$api_credentials = _marketplace_get_api_credentials();
			$Marketplace_Authorization = new Marketplace_Authorization();
            $authorization = $Marketplace_Authorization->decrypt($api_credentials['api_authorization_token']);
			if ( !current_user_can( 'manage_options' ) || !$authorization ) {
				return false;
			}

			if ( false === $remote || $this->cache_allowed == false ) {
				error_log(print_r("api_request:set cache " . $cache_key, true));
				$remote = wp_remote_get(
					$wp_rest_url,
					array(
						'timeout' => 10,
						'headers' => array(
							'Authorization' => 'Basic ' .  base64_encode( $authorization )
						)
					)
				);

				set_transient($cache_key, $remote, $this->cache_time);

				if (
					is_wp_error($remote)
					|| 200 !== wp_remote_retrieve_response_code($remote)
					|| empty(wp_remote_retrieve_body($remote))
				) {
					return false;
				}

			}

			$remote = json_decode(wp_remote_retrieve_body($remote), $associative);

			return $remote;

		}
		public function plugins_api_request($associative = false)
		{
			$remote = $this->api_request( $this->wp_rest_plugins_url . 'wp-json/wp/v2/marketplace/plugins', $associative);

			return $remote;

		}
		public function themes_api_request($associative = false)
		{
			$remote = $this->api_request( $this->wp_rest_plugins_url . 'wp-json/wp/v2/marketplace/themes', $associative, 'extensions_update_themes');

			return $remote;
		}
		public function get_package_updates($value, $transient)
		{
			if ( !( $transient !== 'update_plugins' || $transient !== 'update_themes' ) ) {
				return $value;
			}
			$packages = false;
			if ( $transient == 'update_plugins' ) {
				$packages = $this->plugins_api_request();
			} else if ( $transient == 'update_themes' ) {
				$packages = $this->themes_api_request();
			}

			if (empty($packages) || _marketplace_api_request_is_error( $packages )) return $value;
			if (empty($value->checked)) return $value;

			foreach ($packages as $key => $package) {
				$package_key = false;
				$current_package = false;
				$current_package_version = false;
				if ( $transient == 'update_plugins' && is_admin() ) {
					$package_key = $package->plugin . '.php';
					if (!file_exists( WP_PLUGIN_DIR . '/' . $package_key )) continue;
					if( ! function_exists('get_plugin_data') ){
						require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
					}
					$current_package = get_plugin_data( WP_PLUGIN_DIR . '/' . $package_key );
					$current_package_version = ! empty( $current_package['Version'] ) ? $current_package['Version'] : false;

				} else if ( $transient == 'update_themes' ) {
					$package = (object) $package;
					$package_key = $package->stylesheet;
					$current_package = wp_get_theme( $package->stylesheet );
					$current_package = $current_package->exists() ? $current_package : false;
					if ($current_package) {
						$current_package_version = $current_package->get('Version');
					}
				}

				if (!$current_package) continue;

				if (
					version_compare($current_package_version, $package->version, '<')
					&& version_compare($package->requires_wp, get_bloginfo('version'), '<=')
					&& version_compare($package->requires_php, PHP_VERSION, '<')
				) {
					if ( $transient == 'update_themes' ) {
						$res = $this->build_query_themes_object( $package, 'update' );
					} else {
						$res = $this->build_plugins_query_object( $package, 'update' );
					}

					$value->response[$package_key] = $res;
				}
			}

			return $value;

		}
		public function purge_transient_cache($upgrader, $options)
		{
			if (
				$this->cache_allowed
				&& 'update' === $options['action']
				&& 'plugin' === $options['type']
			) {
				// just clean the cache when new plugin version is installed
				delete_transient($this->cache_key);
				delete_transient($this->themes_cache_key);
			}
		}

		public function force_purge_transient_cache( $force_purge = false )
		{
			// ?marketplace_force_purge=true
			if ( $force_purge == false && ( isset($_GET['marketplace_force_purge']) && $_GET['marketplace_force_purge'] == false )) {
				return;
			}

			delete_transient($this->plugins_cache_key);
			delete_transient($this->themes_cache_key);
		}
	}

	new Marketplace_Updater();
}
