<?php declare(strict_types=1);
namespace Gratis\Fleet\Admin;

use Gratis\Fleet\SiteManager;
use Gratis\Fleet\BulkUpdater;

/**
 * Fleet settings + management page.
 *
 * Lives as a submenu under the Fleet dashboard. Handles connecting/removing
 * worker sites, displaying & regenerating this site's worker API key, and
 * one-click fleet-wide bulk plugin updates. All side-effecting actions go
 * through nonce + capability-gated admin-post handlers.
 */
final class Settings {

    public const PAGE = 'gratis-fleet-settings';

    public static function registerMenu(): void {
        add_submenu_page(
            'gratis-fleet',
            __('Fleet Settings', 'gratis-fleet'),
            __('Settings', 'gratis-fleet'),
            'manage_options',
            self::PAGE,
            [__CLASS__, 'render']
        );
    }

    public static function registerActions(): void {
        add_action('admin_post_gratis_fleet_add_site', [__CLASS__, 'handleAddSite']);
        add_action('admin_post_gratis_fleet_remove_site', [__CLASS__, 'handleRemoveSite']);
        add_action('admin_post_gratis_fleet_regen_key', [__CLASS__, 'handleRegenKey']);
        add_action('admin_post_gratis_fleet_bulk_update', [__CLASS__, 'handleBulkUpdate']);
    }

    private static function guard(string $nonce): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'gratis-fleet'));
        }
        check_admin_referer($nonce);
    }

    private static function redirect(string $flag): void {
        wp_safe_redirect(add_query_arg('gratis_fleet_msg', $flag, admin_url('admin.php?page=' . self::PAGE)));
        exit;
    }

    public static function handleAddSite(): void {
        self::guard('gratis_fleet_add_site');
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified in self::guard() above.
        $url   = isset($_POST['site_url']) ? esc_url_raw(wp_unslash($_POST['site_url'])) : '';
        $key   = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
        $label = isset($_POST['label']) ? sanitize_text_field(wp_unslash($_POST['label'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ('' === $url || '' === $key) {
            self::redirect('missing');
        }
        SiteManager::add($url, $key, $label);
        self::redirect('added');
    }

    public static function handleRemoveSite(): void {
        self::guard('gratis_fleet_remove_site');
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in self::guard() above.
        $id = isset($_POST['site_id']) ? sanitize_text_field(wp_unslash($_POST['site_id'])) : '';
        SiteManager::remove($id);
        self::redirect('removed');
    }

    public static function handleRegenKey(): void {
        self::guard('gratis_fleet_regen_key');
        update_option('gratis_fleet_api_key', wp_generate_password(32, false));
        self::redirect('regen');
    }

    public static function handleBulkUpdate(): void {
        self::guard('gratis_fleet_bulk_update');
        $report = BulkUpdater::updateAll();
        set_transient('gratis_fleet_last_update', $report, HOUR_IN_SECONDS);
        self::redirect('updated');
    }

    private static function apiKey(): string {
        $key = (string) get_option('gratis_fleet_api_key', '');
        if ('' === $key) {
            $key = wp_generate_password(32, false);
            update_option('gratis_fleet_api_key', $key);
        }
        return $key;
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
        $msg = isset($_GET['gratis_fleet_msg']) ? sanitize_key(wp_unslash($_GET['gratis_fleet_msg'])) : '';

        echo '<div class="wrap" style="max-width:900px">';
        echo '<h1>' . esc_html__('Fleet Settings', 'gratis-fleet') . '</h1>';

        self::notice($msg);

        // Bulk update.
        echo '<h2>' . esc_html__('Bulk plugin updates', 'gratis-fleet') . '</h2>';
        echo '<p>' . sprintf(
            /* translators: %d: pending plugin updates on this site. */
            esc_html__('Update plugins across every connected site in one click. This site has %d pending update(s).', 'gratis-fleet'),
            absint(BulkUpdater::localPending())
        ) . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="gratis_fleet_bulk_update">';
        wp_nonce_field('gratis_fleet_bulk_update');
        submit_button(__('Update plugins fleet-wide', 'gratis-fleet'), 'primary', 'submit', false);
        echo '</form>';

        self::renderLastReport();

        // Connected sites.
        echo '<hr><h2>' . esc_html__('Connected sites', 'gratis-fleet') . '</h2>';
        $sites = SiteManager::all();
        if (empty($sites)) {
            echo '<p>' . esc_html__('No sites connected yet. Add one below.', 'gratis-fleet') . '</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr>';
            echo '<th>' . esc_html__('Label', 'gratis-fleet') . '</th><th>' . esc_html__('URL', 'gratis-fleet') . '</th><th>' . esc_html__('Status', 'gratis-fleet') . '</th><th></th>';
            echo '</tr></thead><tbody>';
            foreach ($sites as $site) {
                echo '<tr>';
                echo '<td>' . esc_html($site['label']) . '</td>';
                echo '<td>' . esc_html($site['url']) . '</td>';
                echo '<td>' . esc_html($site['status'] ?? 'unknown') . '</td>';
                echo '<td><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'' . esc_js(__('Remove this site from the fleet?', 'gratis-fleet')) . '\')">';
                echo '<input type="hidden" name="action" value="gratis_fleet_remove_site">';
                echo '<input type="hidden" name="site_id" value="' . esc_attr($site['id']) . '">';
                wp_nonce_field('gratis_fleet_remove_site');
                echo '<button type="submit" class="button-link delete">' . esc_html__('Remove', 'gratis-fleet') . '</button>';
                echo '</form></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        // Add site form.
        echo '<h3>' . esc_html__('Connect a site', 'gratis-fleet') . '</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="gratis_fleet_add_site">';
        wp_nonce_field('gratis_fleet_add_site');
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row"><label for="gf_site_url">' . esc_html__('Site URL', 'gratis-fleet') . '</label></th><td><input type="url" id="gf_site_url" name="site_url" class="regular-text" placeholder="https://client-site.com" required></td></tr>';
        echo '<tr><th scope="row"><label for="gf_api_key">' . esc_html__('Worker API key', 'gratis-fleet') . '</label></th><td><input type="text" id="gf_api_key" name="api_key" class="regular-text" required><p class="description">' . esc_html__('Run "wp gratis-fleet key" on the remote site to get its key.', 'gratis-fleet') . '</p></td></tr>';
        echo '<tr><th scope="row"><label for="gf_label">' . esc_html__('Label', 'gratis-fleet') . '</label></th><td><input type="text" id="gf_label" name="label" class="regular-text"></td></tr>';
        echo '</table>';
        submit_button(__('Connect site', 'gratis-fleet'));
        echo '</form>';

        // This site's worker key.
        echo '<hr><h2>' . esc_html__('This site as a worker', 'gratis-fleet') . '</h2>';
        echo '<p>' . esc_html__('Give this key to another Gratis Fleet controller so it can manage this site:', 'gratis-fleet') . '</p>';
        echo '<input type="text" class="large-text code" readonly value="' . esc_attr(self::apiKey()) . '" onclick="this.select()">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:12px">';
        echo '<input type="hidden" name="action" value="gratis_fleet_regen_key">';
        wp_nonce_field('gratis_fleet_regen_key');
        submit_button(__('Regenerate key', 'gratis-fleet'), 'secondary', 'submit', false);
        echo '</form>';

        echo '</div>';
    }

    private static function notice(string $msg): void {
        $map = [
            'added'   => ['success', __('Site connected.', 'gratis-fleet')],
            'removed' => ['success', __('Site removed.', 'gratis-fleet')],
            'regen'   => ['success', __('Worker API key regenerated.', 'gratis-fleet')],
            'updated' => ['success', __('Fleet-wide plugin update complete.', 'gratis-fleet')],
            'missing' => ['error', __('Site URL and API key are required.', 'gratis-fleet')],
        ];
        if (!isset($map[$msg])) {
            return;
        }
        [$type, $text] = $map[$msg];
        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($text) . '</p></div>';
    }

    private static function renderLastReport(): void {
        $report = get_transient('gratis_fleet_last_update');
        if (!is_array($report) || empty($report['sites'])) {
            return;
        }
        echo '<div class="notice notice-info inline" style="padding:8px 12px;margin:12px 0"><p><strong>' . sprintf(
            /* translators: %d: total plugins updated. */
            esc_html__('Last run: %d plugin(s) updated across the fleet.', 'gratis-fleet'),
            absint($report['total_updated'])
        ) . '</strong></p><ul style="list-style:disc;margin-left:20px">';
        foreach ($report['sites'] as $entry) {
            $ok   = !empty($entry['success']);
            $icon = $ok ? '✓' : '✗';
            $line = sprintf('%s %s — %d updated%s', $icon, $entry['label'] ?? $entry['url'] ?? '', (int) ($entry['updated'] ?? 0), empty($entry['error']) ? '' : ' (' . $entry['error'] . ')');
            echo '<li>' . esc_html($line) . '</li>';
        }
        echo '</ul></div>';
    }
}
