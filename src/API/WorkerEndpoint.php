<?php declare(strict_types=1);
namespace Gratis\Fleet\API;

/** REST endpoints exposed by worker sites for fleet management. */
final class WorkerEndpoint
{
    public static function register(): void
    {
        register_rest_route('gratis-fleet/v1', '/status', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'status'],
            'permission_callback' => [__CLASS__, 'authenticate'],
        ]);

        register_rest_route('gratis-fleet/v1', '/command', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'command'],
            'permission_callback' => [__CLASS__, 'authenticate'],
        ]);
    }

    public static function authenticate(\WP_REST_Request $request): bool
    {
        $key = $request->get_header('X-Fleet-Key');
        $stored = get_option('gratis_fleet_api_key', '');
        if (!$stored) {
            // Auto-generate key on first access attempt
            $stored = wp_generate_password(32, false);
            update_option('gratis_fleet_api_key', $stored);
        }
        return $key && hash_equals($stored, $key);
    }

    public static function status(): \WP_REST_Response
    {
        global $wp_version;
        $theme = wp_get_theme();
        $plugins = get_option('active_plugins', []);
        $updates = get_site_transient('update_plugins');
        $pending = $updates && !empty($updates->response) ? count($updates->response) : 0;

        return new \WP_REST_Response([
            'wordpress'      => $wp_version,
            'php'            => PHP_VERSION,
            'theme'          => $theme->get('Name') . ' ' . $theme->get('Version'),
            'plugins_active' => count($plugins),
            'updates_pending' => $pending,
            'disk_free'      => @disk_free_space(ABSPATH) ? round(disk_free_space(ABSPATH) / 1073741824, 1) . ' GB' : 'unknown',
            'uptime'         => time() - (int) get_option('gratis_fleet_boot_time', time()),
            'site_url'       => get_site_url(),
            'admin_email'    => get_option('admin_email'),
        ]);
    }

    public static function command(\WP_REST_Request $request): \WP_REST_Response
    {
        $action = $request->get_param('action');
        $params = $request->get_param('params') ?? [];

        $result = match ($action) {
            'update_plugins' => self::updatePlugins(),
            'update_themes'  => self::updateThemes(),
            'update_core'    => self::updateCore(),
            'purge_cache'    => self::purgeCache(),
            'get_plugins'    => self::getPlugins(),
            'get_health'     => self::getHealth(),
            default          => ['success' => false, 'error' => "Unknown action: {$action}"],
        };

        return new \WP_REST_Response($result);
    }

    private static function updatePlugins(): array
    {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $updates = get_site_transient('update_plugins');
        if (!$updates || empty($updates->response)) {
            return ['success' => true, 'updated' => 0];
        }
        $upgrader = new \Plugin_Upgrader(new \Automatic_Upgrader_Skin());
        $plugins = array_keys($updates->response);
        $results = $upgrader->bulk_upgrade($plugins);
        return ['success' => true, 'updated' => count(array_filter($results))];
    }

    private static function updateThemes(): array
    {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        $updates = get_site_transient('update_themes');
        if (!$updates || empty($updates->response)) {
            return ['success' => true, 'updated' => 0];
        }
        $upgrader = new \Theme_Upgrader(new \Automatic_Upgrader_Skin());
        $themes = array_keys($updates->response);
        $results = $upgrader->bulk_upgrade($themes);
        return ['success' => true, 'updated' => count(array_filter($results))];
    }

    private static function updateCore(): array
    {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        $upgrader = new \Core_Upgrader(new \Automatic_Upgrader_Skin());
        $result = $upgrader->upgrade(get_preferred_from_update_core());
        return ['success' => !is_wp_error($result), 'version' => get_bloginfo('version')];
    }

    private static function purgeCache(): array
    {
        do_action('gratis_cache_purge_all', 'fleet');
        if (function_exists('wp_cache_flush')) wp_cache_flush();
        return ['success' => true, 'purged' => true];
    }

    private static function getPlugins(): array
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all = get_plugins();
        $active = get_option('active_plugins', []);
        $list = [];
        foreach ($all as $file => $data) {
            $list[] = [
                'file'    => $file,
                'name'    => $data['Name'],
                'version' => $data['Version'],
                'active'  => in_array($file, $active),
            ];
        }
        return ['success' => true, 'plugins' => $list];
    }

    private static function getHealth(): array
    {
        return [
            'success'    => true,
            'db_size'    => self::getDbSize(),
            'post_count' => (int) wp_count_posts()->publish,
            'user_count' => (int) count_users()['total_users'],
            'php_memory' => ini_get('memory_limit'),
            'opcache'    => function_exists('opcache_get_status') && @opcache_get_status(false),
        ];
    }

    private static function getDbSize(): string
    {
        global $wpdb;
        $size = $wpdb->get_var("SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = DATABASE()");
        return $size ? round($size / 1048576, 1) . ' MB' : 'unknown';
    }
}
