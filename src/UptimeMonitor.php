<?php declare(strict_types=1);
namespace Gratis\Fleet;

defined('ABSPATH') || exit;
/**
 * Uptime monitoring — what ManageWP charges $1/site/mo for. Free in Gratis.
 * Runs via WP-Cron on the controller site, pings fleet sites.
 */
final class UptimeMonitor {

    private const OPTION = 'gratis_fleet_uptime_log';
    private const CRON_HOOK = 'gratis_fleet_uptime_check';

    public static function init(): void {
        add_action(self::CRON_HOOK, [__CLASS__, 'checkAll']);

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'five_minutes', self::CRON_HOOK);
        }

        // Register custom interval.
        add_filter('cron_schedules', function ($schedules) { // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- uptime monitoring intentionally polls every 5 minutes.
            $schedules['five_minutes'] = ['interval' => 300, 'display' => 'Every 5 minutes'];
            return $schedules;
        });
    }

    public static function checkAll(): void {
        $sites = SiteManager::all();
        foreach ($sites as $site) {
            self::check($site);
        }
    }

    public static function check(array $site): array {
        $start = microtime(true);
        $response = wp_remote_get($site['url'], [
            'timeout' => 10,
        ]);
        $duration = round((microtime(true) - $start) * 1000);

        $entry = [
            'site_id'   => $site['id'],
            'timestamp' => gmdate('c'),
            'online'    => !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200,
            'code'      => is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response),
            'ms'        => $duration,
            'error'     => is_wp_error($response) ? $response->get_error_message() : null,
        ];

        self::log($entry);
        return $entry;
    }

    /** Get uptime stats for a site over the last N hours. */
    public static function stats(string $siteId, int $hours = 24): array {
        $log = self::getLog();
        $cutoff = strtotime("-{$hours} hours");
        $entries = array_filter($log, fn($e) =>
            ($e['site_id'] ?? '') === $siteId &&
            strtotime($e['timestamp'] ?? '') >= $cutoff
        );

        if (empty($entries)) return ['checks' => 0, 'uptime' => 0, 'avg_ms' => 0];

        $online = count(array_filter($entries, fn($e) => $e['online']));
        $avgMs = array_sum(array_column($entries, 'ms')) / count($entries);

        return [
            'checks'  => count($entries),
            'uptime'  => round($online / count($entries) * 100, 1),
            'avg_ms'  => round($avgMs),
            'last'    => end($entries),
        ];
    }

    private static function log(array $entry): void {
        $log = self::getLog();
        $log[] = $entry;
        // Keep last 2000 entries
        if (count($log) > 2000) $log = array_slice($log, -2000);
        update_option(self::OPTION, $log, false);
    }

    private static function getLog(): array {
        return get_option(self::OPTION, []);
    }
}
