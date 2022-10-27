<?php
class Extensions_REST_Themes_Controller extends WP_REST_Themes_Controller
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
//     $controller = new Extensions_REST_Themes_Controller();
//     $controller->register_routes();
// }
// add_action('rest_api_init', 'prefix_plugin_library_register_routes_init');
