<?php
class Extensions_REST_Plugins_Controller extends WP_REST_Plugins_Controller
{

    const PATTERN = '[^.\/]+(?:\/[^.\/]+)?';
    public $has_permission = false;
    // Here initialize our namespace and resource name.
    public function __construct()
    {
        $this->namespace     = 'wp/v2';
        $this->rest_base = 'plugins';
    }
    // Register our routes.
    public function register_routes()
    {
        register_rest_route(
            $this->namespace, '/' . $this->rest_base, 
            array(
                // Here we register the readable endpoint for collections.
                array(
                    'methods'   => WP_REST_Server::READABLE,
                    'callback'  => array($this, 'get_items'),
                    'permission_callback' => array($this, 'get_items_permissions_check'),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_item' ),
                    'permission_callback' => array( $this, 'create_item_permissions_check' ),
                ),
                // Register our schema callback.
                'schema' => array($this, 'get_item_schema'),
            )
        );
    }
    /**
	 * Checks if a given request has access to upload plugins.
	 *
	 * @since 5.5.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to create items, WP_Error object otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return new WP_Error(
				'rest_cannot_install_plugin',
				__( 'Sorry, you are not allowed to install plugins on this site.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		if ( 'inactive' !== $request['status'] && ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error(
				'rest_cannot_activate_plugin',
				__( 'Sorry, you are not allowed to activate plugins.' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}
	/**
	 * Uploads a plugin and optionally activates it.
	 *
	 * @since 5.5.0
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		global $wp_filesystem;

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		$slug = $request['slug'];

		// Verify filesystem is accessible first.
		$filesystem_available = $this->is_filesystem_available();
		if ( is_wp_error( $filesystem_available ) ) {
			return $filesystem_available;
		}

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => array(
					'sections'       => false,
					'language_packs' => true,
				),
			)
		);

		if ( is_wp_error( $api ) ) {
			if ( false !== strpos( $api->get_error_message(), 'Plugin not found.' ) ) {
				$api->add_data( array( 'status' => 404 ) );
			} else {
				$api->add_data( array( 'status' => 500 ) );
			}

			return $api;
		}

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );

		$result = $upgrader->install( $api->download_link );

		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 500 ) );

			return $result;
		}

		// This should be the same as $result above.
		if ( is_wp_error( $skin->result ) ) {
			$skin->result->add_data( array( 'status' => 500 ) );

			return $skin->result;
		}

		if ( $skin->get_errors()->has_errors() ) {
			$error = $skin->get_errors();
			$error->add_data( array( 'status' => 500 ) );

			return $error;
		}

		if ( is_null( $result ) ) {
			// Pass through the error from WP_Filesystem if one was raised.
			if ( $wp_filesystem instanceof WP_Filesystem_Base
				&& is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors()
			) {
				return new WP_Error(
					'unable_to_connect_to_filesystem',
					$wp_filesystem->errors->get_error_message(),
					array( 'status' => 500 )
				);
			}

			return new WP_Error(
				'unable_to_connect_to_filesystem',
				__( 'Unable to connect to the filesystem. Please confirm your credentials.' ),
				array( 'status' => 500 )
			);
		}

		$file = $upgrader->plugin_info();

		if ( ! $file ) {
			return new WP_Error(
				'unable_to_determine_installed_plugin',
				__( 'Unable to determine what plugin was installed.' ),
				array( 'status' => 500 )
			);
		}

		if ( 'inactive' !== $request['status'] ) {
			$can_change_status = $this->plugin_status_permission_check( $file, $request['status'], 'inactive' );

			if ( is_wp_error( $can_change_status ) ) {
				return $can_change_status;
			}

			$changed_status = $this->handle_plugin_status( $file, $request['status'], 'inactive' );

			if ( is_wp_error( $changed_status ) ) {
				return $changed_status;
			}
		}

		// Install translations.
		$installed_locales = array_values( get_available_languages() );
		/** This filter is documented in wp-includes/update.php */
		$installed_locales = apply_filters( 'plugins_update_check_locales', $installed_locales );

		$language_packs = array_map(
			static function( $item ) {
				return (object) $item;
			},
			$api->language_packs
		);

		$language_packs = array_filter(
			$language_packs,
			static function( $pack ) use ( $installed_locales ) {
				return in_array( $pack->language, $installed_locales, true );
			}
		);

		if ( $language_packs ) {
			$lp_upgrader = new Language_Pack_Upgrader( $skin );

			// Install all applicable language packs for the plugin.
			$lp_upgrader->bulk_upgrade( $language_packs );
		}

		$path          = WP_PLUGIN_DIR . '/' . $file;
		$data          = get_plugin_data( $path, false, false );
		$data['_file'] = $file;

		$response = $this->prepare_item_for_response( $data, $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%s', $this->namespace, $this->rest_base, substr( $file, 0, - 4 ) ) ) );

		return $response;
	}
    /**
     * Check permissions for the plugins.
     *
     * @param WP_REST_Request $request Current request.
     */
    public function get_items_permissions_check($request)
    {
        $is_error = new WP_Error('rest_forbidden', esc_html__('You cannot view the post resource.'), array('status' => rest_authorization_required_code()));
        if (!current_user_can('activate_plugins')) {
            return $is_error;
        }
        return true;
    }

}


// function prefix_plugin_library_register_routes_init()
// {
//     $controller = new Extensions_REST_Plugins_Controller();
//     $controller->register_routes();
// }
// add_action('rest_api_init', 'bca_plugin_library_register_routes_init');
