<?php
/**
 * Plugin Name: Gratis Fleet
 * Plugin URI: https://github.com/juslintek/gratis-fleet
 * Description: Free multi-WordPress fleet management. Manage unlimited sites from one dashboard. What ManageWP charges $2/site/mo for.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * Author URI: https://github.com/juslintek
 * License: GPL-2.0-or-later
 * Text Domain: gratis-fleet
 */

defined('ABSPATH') || exit;

define('GRATIS_FLEET_VERSION', '1.0.0');
define('GRATIS_FLEET_PATH', plugin_dir_path(__FILE__));

require_once __DIR__ . '/autoload.php';

add_action('plugins_loaded', function () {
    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::add_command('gratis-fleet', \Gratis\Fleet\CLI\FleetCommand::class);
    }

    // Start uptime monitoring if sites are configured
    if (get_option('gratis_fleet_sites')) {
        \Gratis\Fleet\UptimeMonitor::init();
    }
});

// REST API for fleet communication (worker mode)
add_action('rest_api_init', [\Gratis\Fleet\API\WorkerEndpoint::class, 'register']);

// Admin dashboard (controller mode)
add_action('admin_menu', function () {
    
    add_menu_page(
        'Fleet Manager',
        'Fleet',
        'manage_options',
        'gratis-fleet',
        [\Gratis\Fleet\Admin\Dashboard::class, 'render'],
        'dashicons-networking',
        3
    );
});
