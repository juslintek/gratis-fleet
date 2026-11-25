<?php
/**
 * Plugin Name: Gratis Fleet
 * Plugin URI: https://github.com/juslintek/gratis-fleet
 * Description: Multi-WordPress fleet management. Manage unlimited sites from one dashboard, run fleet-wide bulk plugin updates, monitor uptime, and scan security/SEO.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * Author URI: https://github.com/juslintek
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gratis-fleet
 */

defined('ABSPATH') || exit;

define('GRATIS_FLEET_VERSION', '1.0.0');
define('GRATIS_FLEET_FILE', __FILE__);
define('GRATIS_FLEET_PATH', plugin_dir_path(__FILE__));
define('GRATIS_FLEET_DIR', plugin_dir_path(__FILE__));
define('GRATIS_FLEET_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/autoload.php';

add_action('plugins_loaded', function () {
    // One-time migration: disable autoload on growing options.
    if (!get_option('gratis_fleet_autoload_fixed')) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($wpdb->options, ['autoload' => 'no'], ['option_name' => 'gratis_fleet_sites']);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($wpdb->options, ['autoload' => 'no'], ['option_name' => 'gratis_fleet_webhooks']);
        update_option('gratis_fleet_autoload_fixed', 1);
    }

    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::add_command('gratis-fleet', \Gratis\Fleet\CLI\FleetCommand::class);
    }

    // Start uptime monitoring if sites are configured.
    if (get_option('gratis_fleet_sites')) {
        \Gratis\Fleet\UptimeMonitor::init();
        \Gratis\Fleet\Notifications::init();
        \Gratis\Fleet\WebhookHub::init();
    }

    // Admin-post handlers for the settings/management page.
    \Gratis\Fleet\Admin\Settings::registerActions();
});

// REST API for fleet communication (worker mode).
add_action('rest_api_init', [\Gratis\Fleet\API\WorkerEndpoint::class, 'register']);

// Admin dashboard (controller mode) + settings submenu.
add_action('admin_menu', function () {
    add_menu_page(
        __('Fleet Manager', 'gratis-fleet'),
        __('Fleet', 'gratis-fleet'),
        'manage_options',
        'gratis-fleet',
        [\Gratis\Fleet\Admin\Dashboard::class, 'render'],
        'dashicons-networking',
        3
    );

    \Gratis\Fleet\Admin\Settings::registerMenu();
});
