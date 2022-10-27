<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

if (!class_exists('Marketplace_Settings')) {
    class Marketplace_Settings
    {

        public $Marketplace_Updater;
        public $extensions;

        public $settings;
        public $sections;
        public $fields;

        public function __construct()
        {
            add_action('init', array($this, 'init'));
            add_filter('wp_is_application_passwords_available', array($this,'wp_is_application_passwords_available'));
        }
        public function init()
        {
            
            // add_filter('wp_is_application_passwords_available_for_user', array($this,'wp_is_application_passwords_available_for_user'), 10, 2);

            if (class_exists('Marketplace_Updater')) {

                $this->Marketplace_Updater = new Marketplace_Updater();
                $this->extensions = $this->Marketplace_Updater->plugins_api_request(true);
                $this->init_settings();
                // register fields
                add_action('admin_init', array($this, 'register_settings_fields'));
                // create admin setting page
                add_action('admin_menu', array($this, 'add_settings_subpage'));

                add_action('admin_head', array($this, 'admin_head') );
            }
        }
        public function admin_head()
        {
            ?>
            <style>
                .marketplace.hide-title > th {display: none;}
                .marketplace.hide-title > td {padding-left: 0;padding-right: 0;}
            </style>
            <?php
        }
        public function wp_is_application_passwords_available( $available ) {
            $dev_enviornments = array( 'local', 'development', 'staging' );
            if (in_array(wp_get_environment_type(), $dev_enviornments) && !is_ssl()) {
                $available = true;
            }
            return $available;
        }
        public function wp_is_application_passwords_available_for_user( $available, $user )
        {
            if (user_can($user, 'manage_options')) {
                $available = true;
            }
            return $available;
        }
        public function init_settings()
        {
            $this->pages = array(
                array(
                    'page_title' => 'General Settings',
                    'menu_title' => 'Market Place',
                    'capability' => 'manage_options',
                    'menu_slug' => 'marketplace-general',
                    'function' => array($this, 'menu_page'),
                    'icon_url' => 'dashicons-block-default',
                    'position' => 65
                ),
                array(
                    'page_slug' => 'marketplace-general',
                    'page_title' => 'General Settings',
                    'menu_title' => 'General Settings',
                    'capability' => 'manage_options',
                    'menu_slug' => 'marketplace-general',
                    'function' => array($this, 'menu_page'),
                    'icon_url' => 'dashicons-block-default',
                    'position' => 65
                )
            );
            $this->settings = array(
                array(
                    'option_group' => 'marketplace-general',
                    'option_name' => 'marketplace_general',
                    'page' => 'marketplace-general',
                ),
                array(
                    'option_group' => 'marketplace-general',
                    'option_name' => 'marketplace_purge_cache',
                    'page' => 'marketplace-general',
                    'args' => array(
                        'sanitize_callback' => array( $this, 'marketplace_purge_cache_sanitize' )
                    ),
                ),
                // array(
                //     'option_group' => 'marketplace-general',
                //     'option_name' => 'marketplace_get_auth',
                //     'page' => 'marketplace-general',
                //     'args' => array(
                //         'sanitize_callback' => array( $this, 'marketplace_auth_sanitize' )
                //     ),
                // )
            );

            $this->sections = array(
                array(
                    'id' => 'general',
                    'title' => 'General',
                    'callback' => array($this, 'page_section'),
                    'page' => 'marketplace-general'
                )
            );

            $this->fields = array(
                array(
                    'id' => 'marketplace_purge_cache',
                    'title' => 'Clear Cache',
                    'callback' => array($this, 'submit_button'),
                    'page' => 'marketplace-general',
                    'section' => 'general',
                    'args' => array(
                        'name' => 'marketplace_purge_cache',
                        'label_for' => 'marketplace_purge_cache',
                        'title' => 'Clear Cache',
                        'class' => 'marketplace hide-title',
                        'description' => '',
                        'default' => '',
                        'type' => 'text',
                        'option_group' => 'marketplace_purge_cache',
                    )
                ),
                // array(
                //     'id' => 'marketplace_get_auth',
                //     'title' => '',
                //     'callback' => array($this, 'submit_button'),
                //     'page' => 'marketplace-general',
                //     'section' => 'general',
                //     'args' => array(
                //         'name' => 'marketplace_get_auth',
                //         'label_for' => 'marketplace_get_auth',
                //         'title' => 'Get API Keys',
                //         'class' => 'marketplace hide-title',
                //         'description' => '',
                //         'default' => '',
                //         'type' => 'text',
                //         'option_group' => 'marketplace_get_auth',
                //     )
                // ),
                array(
                    'id' => 'api_domain',
                    'title' => 'Marketplace url',
                    'callback' => array($this, 'input_field'),
                    'page' => 'marketplace-general',
                    'section' => 'general',
                    'args' => array(
                        'name' => 'api_domain',
                        'label_for' => 'api_domain',
                        'class' => 'marketplace',
                        'description' => '',
                        'default' => '',
                        'type' => 'url',
                        'option_group' => 'marketplace_general',
                    )
                ),
                array(
                    'id' => 'api_username',
                    'title' => 'API Username',
                    'callback' => array($this, 'input_field'),
                    'page' => 'marketplace-general',
                    'section' => 'general',
                    'args' => array(
                        'name' => 'api_username',
                        'label_for' => 'api_username',
                        'class' => 'marketplace',
                        'description' => '',
                        'default' => '',
                        'type' => 'text',
                        'option_group' => 'marketplace_general',
                    )
                ),
                array(
                    'id' => 'api_key',
                    'title' => 'API Key',
                    'callback' => array($this, 'input_field'),
                    'page' => 'marketplace-general',
                    'section' => 'general',
                    'args' => array(
                        'name' => 'api_key',
                        'label_for' => 'api_key',
                        'class' => 'marketplace',
                        'description' => '',
                        'default' => '',
                        'type' => 'password',
                        'option_group' => 'marketplace_general',
                    )
                ),
            );
        }
        public function marketplace_purge_cache_sanitize( $values )
        {
            if (isset($values) && $values == 'Clear Cache') {
                $this->Marketplace_Updater->force_purge_transient_cache( true );
                add_settings_error('marketplace_manager_notices', 'marketplace_manager_settings_message','Cache has been cleared.', 'updated');
            }

            return $values;

        }
        public function marketplace_auth_sanitize( $values )
        {
            $admin_url = admin_url( 'authorize-application.php' );

            $auth_url = add_query_arg( array( 
                'app_name' => 'Market Place',
                'username' => 'marketplace',
            ), $admin_url );

            wp_redirect( $auth_url, 301 );
            exit;

        }
        /**
         * Register field options for the admin submenu page
         */
        public function register_settings_fields()
        {

            if (is_array($this->settings) && !empty($this->settings)) {
                foreach ($this->settings as $key => $setting) {
                    register_setting(
                        $setting['option_group'],
                        $setting['option_name'],
                        isset( $setting['args'] ) ? $setting['args'] : array()
                    );
                }
            }
            if (is_array($this->sections) && !empty($this->sections)) {
                foreach ($this->sections as $key => $section) {
                    add_settings_section(
                        $section['id'],
                        __($section['title']),
                        $section['callback'],
                        $section['page']
                    );
                }
            }
            if (is_array($this->fields) && !empty($this->fields)) {
                foreach ($this->fields as $key => $field) {
                    add_settings_field(
                        $field['id'],
                        __($field['title']),
                        $field['callback'],
                        $field['page'], // add to this fields
                        $field['section'], // add to this section
                        $field['args']
                    );
                }
            }
        }
        public function add_settings_subpage()
        {
            foreach ($this->pages as $key => $page) {
                if (isset($page['page_slug'])) {
                    add_submenu_page(
                        $page['page_slug'],
                        $page['page_title'],
                        $page['menu_title'],
                        $page['capability'],
                        $page['menu_slug'],
                        $page['function'],
                        $page['position'],
                    );
                } else {
                    add_menu_page(
                        $page['page_title'],
                        $page['menu_title'],
                        $page['capability'],
                        $page['menu_slug'],
                        $page['function'],
                        $page['icon_url'],
                        $page['position'],
                    );
                }
            }
        }
        public function do_settings()
        {
            if (!isset($_GET['page'])) return;
            $pages = array();
            if (is_array($this->settings) && !empty($this->settings)) {
                foreach ($this->settings as $key => $setting) {
                    if ($_GET['page'] === $setting['page'] && !isset( $pages[$setting['option_group']] ) ) {
                        $pages[$setting['option_group']] = $setting['option_group'];
                        settings_fields($setting['option_group']); // output security fields for the registered setting "marketplace_settings"
                        do_settings_sections($setting['option_group']); // output setting sections and their fields
                    }
                }
            }
        }
        /**
         * Register an admin submenu page.
         */
        public function menu_page()
        {

            if (!current_user_can('manage_options')) {
                return;
            }

            // add error/update messages
            settings_errors('marketplace_manager_notices');

            require_once MARKETPLACE_PATH . '/admin/admin.php';
        }

        public function page_section($args)
        {
        }

        public function input_field($args)
        {
            $option = isset($args['option_group']) ? get_option($args['option_group']) : false;
            $value = (isset($option[$args['name']])) ? $option[$args['name']] : $args['default'];
            $name = isset($args['name']) ? $args['option_group'] . '[' . $args['name'] . ']' : false;
            $type = isset($args['type']) ? $args['type'] : 'text';

            if (!$name) return null;

            $attributes = '';
            $attributes_args = array();
            if ($type == 'checkbox') {
                $attributes_args[] = checked($value, true, false);
                $attributes .= implode(' ', $attributes_args);
            }
            echo '<input type="' . $type . '" id="' . $name . '" name="' . $name . '" value="' . $value . '" ' . $attributes . '/>';
        }
        public function submit_button($args)
        {
            $option = isset($args['option_group']) ? get_option($args['option_group']) : false;
            if ( isset($args['name']) && $args['option_group'] !== $args['name'] ) {
                $value = (isset($option[$args['name']])) ? $option[$args['name']] : $args['default'];
                $name = isset($args['name']) ? $args['option_group'] . '[' . $args['name'] . ']' : false;
                $type = isset($args['type']) ? $args['type'] : 'text';
            } else {
                $value = ( $option ) ? $option : $args['default'];
                $name = $args['name'];
                $type = isset($args['type']) ? $args['type'] : 'text';
            }
            $title = isset($args['title']) ? $args['title'] : '';

            if (!$name) return null;

            $attributes = '';
            $attributes_args = array();
            if ($type == 'checkbox') {
                $attributes_args[] = checked($value, true, false);
                $attributes .= implode(' ', $attributes_args);
            }
            submit_button( $title, 'small', $args['name'] );
        }
    }

    new Marketplace_Settings();
}
