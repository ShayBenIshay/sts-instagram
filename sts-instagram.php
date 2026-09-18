<?php
/**
 * Plugin Name: STS Instagram Feed
 * Plugin URI: https://shaytechsolutions.com
 * Description: Instagram Business API integration — fetches posts, handles token refresh, and exposes sts_fetch_instagram_posts() for the theme.
 * Version: 1.0.0
 * Author: Shay Tech Solutions
 * Author URI: https://shaytechsolutions.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sts-instagram
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'STS_INSTAGRAM_VERSION', '1.0.0' );
define( 'STS_INSTAGRAM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STS_INSTAGRAM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once STS_INSTAGRAM_PLUGIN_DIR . 'includes/class-sts-instagram.php';

register_activation_hook( __FILE__, array( 'STS_Instagram', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'STS_Instagram', 'deactivate' ) );

add_action( 'plugins_loaded', function() {
    STS_Instagram::get_instance();
} );

/**
 * Theme-facing public wrapper — used by blocks/instagram-feed/render.php.
 */
function sts_fetch_instagram_posts( $limit = 6 ) {
    return STS_Instagram::get_instance()->fetch_posts( $limit );
}
