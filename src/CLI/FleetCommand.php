<?php declare(strict_types=1);
namespace Gratis\Fleet\CLI;

use Gratis\Fleet\SiteManager;
use Gratis\Fleet\UptimeMonitor;
use Gratis\Fleet\SecurityScanner;
use Gratis\Fleet\SEOAuditor;
use Gratis\Fleet\BrokenLinkChecker;

/** WP-CLI commands: wp gratis-fleet <subcommand> */
final class FleetCommand {

    /**
     * Add a site to the fleet.
     *
     * ## OPTIONS
     * <url>
     * : Site URL.
     * <api-key>
     * : Fleet API key from the remote site.
     * [--label=<label>]
     * : Friendly label.
     *
     * ## EXAMPLES
     *     wp gratis-fleet add https://example.com abc123 --label="Client Site"
     *
     * @when after_wp_load
     */
    public function add($args, $assoc_args): void {
        $site = SiteManager::add($args[0], $args[1], $assoc_args['label'] ?? '');
        \WP_CLI::success("Added: {$site['label']} ({$site['url']})");
    }

    /**
     * List all fleet sites.
     *
     * ## EXAMPLES
     *     wp gratis-fleet list
     *
     * @when after_wp_load
     */
    public function list($args, $assoc_args): void {
        unset($args, $assoc_args);
        $sites = SiteManager::all();
        if (empty($sites)) {
            \WP_CLI::log('No sites in fleet. Add one: wp gratis-fleet add <url> <api-key>');
            return;
        }
        $rows = array_map(fn($s) => [
            'ID'        => substr($s['id'], 0, 8),
            'Label'     => $s['label'],
            'URL'       => $s['url'],
            'Status'    => $s['status'],
            'Last Seen' => $s['last_seen'] ?? 'never',
        ], $sites);
        \WP_CLI\Utils\format_items('table', $rows, ['ID', 'Label', 'URL', 'Status', 'Last Seen']);
    }

    /**
     * Check status of all fleet sites.
     *
     * ## EXAMPLES
     *     wp gratis-fleet status
     *
     * @when after_wp_load
     */
    public function status($args, $assoc_args): void {
        unset($args, $assoc_args);
        $sites = SiteManager::all();
        if (empty($sites)) {
            \WP_CLI::log('No sites in fleet.');
            return;
        }

        \WP_CLI::log('Checking ' . count($sites) . " site(s)...\n");
        foreach ($sites as $site) {
            $result = SiteManager::ping($site);
            if ($result['online']) {
                \WP_CLI::log("✓ {$site['label']}");
                \WP_CLI::log("  WP {$result['WordPress']} | PHP {$result['php']} | {$result['plugins_active']} plugins | {$result['updates_pending']} updates pending");
            } else {
                \WP_CLI::warning("✗ {$site['label']}: " . ($result['error'] ?? 'unreachable'));
            }
        }
    }

    /**
     * Update all plugins across the fleet.
     *
     * ## EXAMPLES
     *     wp gratis-fleet update-all
     *
     * @subcommand update-all
     * @when after_wp_load
     */
    public function update_all($args, $assoc_args): void {
        unset($args, $assoc_args);
        $sites = SiteManager::all();
        foreach ($sites as $site) {
            \WP_CLI::log("Updating: {$site['label']}...");
            $result = SiteManager::command($site, 'update_plugins');
            if ($result['success'] ?? false) {
                \WP_CLI::success("  Updated {$result['updated']} plugin(s)");
            } else {
                \WP_CLI::warning('  Failed: ' . ($result['error'] ?? 'unknown'));
            }
        }
    }

    /**
     * Purge cache across all fleet sites.
     *
     * ## EXAMPLES
     *     wp gratis-fleet purge-all
     *
     * @subcommand purge-all
     * @when after_wp_load
     */
    public function purge_all($args, $assoc_args): void {
        unset($args, $assoc_args);
        $sites = SiteManager::all();
        foreach ($sites as $site) {
            $result = SiteManager::command($site, 'purge_cache');
            $icon = ($result['success'] ?? false) ? '✓' : '✗';
            \WP_CLI::log("  {$icon} {$site['label']}");
        }
        \WP_CLI::success('Cache purged across ' . count($sites) . ' site(s).');
    }

    /**
     * Show the local fleet API key (for adding this site to another controller).
     *
     * ## EXAMPLES
     *     wp gratis-fleet key
     *
     * @when after_wp_load
     */
    public function key($args, $assoc_args): void {
        unset($args, $assoc_args);
        $key = get_option('gratis_fleet_api_key', '');
        if (!$key) {
            $key = wp_generate_password(32, false);
            update_option('gratis_fleet_api_key', $key);
        }
        \WP_CLI::log("Fleet API Key: {$key}");
        \WP_CLI::log('Add this site to a controller with:');
        \WP_CLI::log('  wp gratis-fleet add ' . get_site_url() . " {$key}");
    }

    /**
     * Check uptime of all fleet sites.
     *
     * ## EXAMPLES
     *     wp gratis-fleet uptime
     *
     * @when after_wp_load
     */
    public function uptime($args, $assoc_args): void {
        unset($args, $assoc_args);
        $sites = SiteManager::all();
        if (empty($sites)) {
            \WP_CLI::log('No sites. Checking local site...');
            $result = UptimeMonitor::check(['id' => 'local', 'url' => home_url()]);
            $icon = $result['online'] ? '✓' : '✗';
            \WP_CLI::log("  {$icon} " . home_url() . " ({$result['ms']}ms)");
            return;
        }

        foreach ($sites as $site) {
            $result = UptimeMonitor::check($site);
            $icon = $result['online'] ? '✓' : '✗';
            \WP_CLI::log("  {$icon} {$site['label']} — {$result['ms']}ms" . ($result['error'] ? " ({$result['error']})" : ''));
        }
    }

    /**
     * Run security scan on this site.
     *
     * ## EXAMPLES
     *     wp gratis-fleet security
     *
     * @when after_wp_load
     */
    public function security($args, $assoc_args): void {
        unset($args, $assoc_args);
        \WP_CLI::log("Running security scan...\n");
        $result = SecurityScanner::scan();

        \WP_CLI::log("Security Score: {$result['score']}/100");
        \WP_CLI::log('');

        if (empty($result['issues'])) {
            \WP_CLI::success('No issues found.');
            return;
        }

        foreach ($result['issues'] as $issue) {
            $icon = match ($issue['severity']) {
                'high'   => '✗',
                'medium' => '⚠',
                default  => '○',
            };
            \WP_CLI::log("  {$icon} [{$issue['severity']}] {$issue['detail']}");
        }
    }

    /**
     * Run SEO audit on a URL or the whole site.
     *
     * ## OPTIONS
     * [<url>]
     * : URL to audit. Defaults to full site audit.
     *
     * ## EXAMPLES
     *     wp gratis-fleet seo
     *     wp gratis-fleet seo https://example.com/about/
     *
     * @when after_wp_load
     */
    public function seo($args, $assoc_args): void {
        unset($assoc_args);
        if (!empty($args[0])) {
            $result = SEOAuditor::audit($args[0]);
            \WP_CLI::log("=== SEO Audit: {$result['url']} ===");
            \WP_CLI::log("Score: {$result['score']}/100");
            \WP_CLI::log('Title: ' . ($result['title'] ?: '(missing)'));
            \WP_CLI::log('Description: ' . (substr($result['desc'] ?? '', 0, 80) ?: '(missing)'));
            if (!empty($result['issues'])) {
                \WP_CLI::log("\nIssues:");
                foreach ($result['issues'] as $issue) {
                    \WP_CLI::log("  • [{$issue['severity']}] {$issue['detail']}");
                }
            }
            return;
        }

        \WP_CLI::log("Running full site SEO audit...\n");
        $result = SEOAuditor::auditSite();
        \WP_CLI::log("Average SEO Score: {$result['average_score']}/100\n");

        foreach ($result['pages'] as $page) {
            $icon = ($page['score'] ?? 0) >= 80 ? '✓' : (($page['score'] ?? 0) >= 50 ? '⚠' : '✗');
            $url = str_replace(home_url(), '', $page['url'] ?? '');
            \WP_CLI::log("  {$icon} {$page['score']}/100 — {$url}");
        }
    }
    /**
     * Check for broken links in published content.
     *
     * ## OPTIONS
     * [--limit=<n>]
     * : Max posts to scan.
     * ---
     * default: 50
     * ---
     *
     * ## EXAMPLES
     *     wp gratis-fleet broken-links
     *
     * @subcommand broken-links
     * @when after_wp_load
     */
    public function broken_links($args, $assoc_args): void {
        $limit = (int) ($assoc_args['limit'] ?? 50);
        \WP_CLI::log("Scanning for broken links (up to {$limit} posts)...");
        $result = BrokenLinkChecker::scan($limit);
        \WP_CLI::log("Links checked: {$result['checked']}");
        if (!empty($result['broken'])) {
            \WP_CLI::log("\nBroken links:");
            foreach ($result['broken'] as $link) {
                \WP_CLI::log("  ✗ [{$link['status']}] {$link['url']} (in: {$link['post']})");
            }
        }
        if (!empty($result['redirects'])) {
            \WP_CLI::log("\nRedirects:");
            foreach ($result['redirects'] as $link) {
                \WP_CLI::log("  ⚠ [{$link['status']}] {$link['url']} (in: {$link['post']})");
            }
        }
        if (empty($result['broken']) && empty($result['redirects'])) {
            \WP_CLI::success('No broken links found!');
        }
    }
}
// Note: webhook commands added via separate method injection would go here
