<?php

/**
 * Plugin Name:       Marketplace Updater
 * Description:       Connect to our marketplace to get all of the web tools we offer.
 * Requires at least: 5.8
 * Requires PHP:      7.0
 * Version:           0.1.0
 * Author:            
 * Author URI:        
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       marketplace-updater
 *
 * @package           create-block
 */
defined('ABSPATH') || exit;
define('MARKETPLACE_FILE', __FILE__);
define('MARKETPLACE_URL', plugin_dir_url(__FILE__));
define('MARKETPLACE_PATH', plugin_dir_path(__FILE__));

require_once MARKETPLACE_PATH . '/includes/helpers.php';
require_once MARKETPLACE_PATH . '/includes/class-marketplace-authorization.php';
require_once MARKETPLACE_PATH . '/includes/class-marketplace-updater.php';
require_once MARKETPLACE_PATH . '/includes/class-marketplace-settings.php';