<?php declare(strict_types=1);
namespace Gratis\Fleet;

/**
 * Email notifications for fleet events.
 * Sends alerts for: downtime, security issues, available updates.
 */
final class Notifications {

    public static function init(): void {
        add_action('gratis_fleet_uptime_check', [__CLASS__, 'checkDowntime'], 20);
        add_action('admin_init', [__CLASS__, 'checkUpdates']);
    }

    public static function checkDowntime(): void {
        if (!get_option('gratis_fleet_notify_downtime', true)) return;

        $sites = SiteManager::all();
        foreach ($sites as $site) {
            $stats = UptimeMonitor::stats($site['id'], 1); // Last hour
            if ($stats['checks'] > 0 && $stats['uptime'] < 50) {
                self::send(
                    'Site Down: ' . $site['label'],
                    sprintf('%s appears to be down. Uptime in last hour: %s%%. Last check: %s',
                        $site['url'], $stats['uptime'], $stats['last']['timestamp'] ?? 'unknown')
                );
            }
        }
    }

    public static function checkUpdates(): void {
        if (!get_option('gratis_fleet_notify_updates', true)) return;

        $updates = get_site_transient('update_plugins');
        $count = $updates && !empty($updates->response) ? count($updates->response) : 0;

        if ($count > 0) {
            $lastNotified = (int) get_option('gratis_fleet_last_update_notify', 0);
            if (time() - $lastNotified > 86400) { // Once per day max
                self::send(
                    $count . ' Plugin Update(s) Available',
                    sprintf('%d plugin(s) have updates available on %s. Run: wp gratis-fleet update-all',
                        $count, get_site_url())
                );
                update_option('gratis_fleet_last_update_notify', time());
            }
        }
    }

    public static function send(string $subject, string $message): void {
        $to = get_option('gratis_fleet_notify_email', get_option('admin_email'));
        $subject = '[Gratis Fleet] ' . $subject;
        wp_mail($to, $subject, $message);
    }
}
