<?php declare(strict_types=1);
namespace Gratis\Fleet;

/**
 * Bulk plugin updates across the fleet.
 *
 * Fans a single "update plugins" command out to every connected worker site
 * (via SiteManager::command → the worker's /command REST endpoint, which runs
 * Plugin_Upgrader::bulk_upgrade locally) and aggregates the results. Also
 * updates the controller (local) site so "update everything" is truly fleet-wide.
 */
final class BulkUpdater {

    /**
     * Update plugins across the whole fleet.
     *
     * @return array{sites:array<int,array<string,mixed>>,total_updated:int}
     */
    public static function updateAll(): array {
        $report = ['sites' => [], 'total_updated' => 0];

        // Local controller site.
        $local = self::updateLocal();
        $report['sites'][] = ['label' => get_bloginfo('name') . ' (this site)', 'url' => home_url()] + $local;
        $report['total_updated'] += (int) ($local['updated'] ?? 0);

        // Remote worker sites.
        foreach (SiteManager::all() as $site) {
            $result = SiteManager::command($site, 'update_plugins');
            $entry  = [
                'label'   => $site['label'] ?? $site['url'],
                'url'     => $site['url'],
                'success' => (bool) ($result['success'] ?? false),
                'updated' => (int) ($result['updated'] ?? 0),
                'error'   => $result['error'] ?? '',
            ];
            $report['sites'][]      = $entry;
            $report['total_updated'] += $entry['updated'];
        }

        /**
         * Fires after a fleet-wide bulk plugin update.
         *
         * @param array $report Aggregated update report.
         */
        do_action('gratis_fleet_bulk_updated', $report);

        return $report;
    }

    /** Update all plugins on the local (controller) site. */
    public static function updateLocal(): array {
        if (!function_exists('get_plugin_updates')) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $updates = get_site_transient('update_plugins');
        if (!$updates || empty($updates->response)) {
            return ['success' => true, 'updated' => 0];
        }

        $upgrader = new \Plugin_Upgrader(new \Automatic_Upgrader_Skin());
        $results  = $upgrader->bulk_upgrade(array_keys($updates->response));

        return ['success' => true, 'updated' => is_array($results) ? count(array_filter($results)) : 0];
    }

    /** Count pending plugin updates available across the fleet (local only, cheap). */
    public static function localPending(): int {
        $updates = get_site_transient('update_plugins');
        return $updates && !empty($updates->response) ? count($updates->response) : 0;
    }
}
