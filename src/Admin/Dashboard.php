<?php declare(strict_types=1);
namespace Gratis\Fleet\Admin;

use Gratis\Fleet\SiteManager;
use Gratis\Fleet\SecurityScanner;
use Gratis\Fleet\SEOAuditor;

final class Dashboard {

    public static function render(): void {
        $sites = SiteManager::all();
        $localSecurity = SecurityScanner::scan();
        $localSEO = SEOAuditor::audit(home_url('/'));

        echo '<div class="wrap" style="max-width:1200px">';
        echo '<h1 style="margin-bottom:24px">⚡ Fleet Manager</h1>';

        // Local site card
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(350px,1fr));gap:20px;margin-bottom:32px">';
        self::renderLocalCard($localSecurity, $localSEO);

        // Remote site cards
        foreach ($sites as $site) {
            self::renderSiteCard($site);
        }
        echo '</div>';

        // Quick actions
        echo '<div style="background:#f8f9fa;border:1px solid #e2e8f0;border-radius:8px;padding:20px;margin-top:24px">';
        echo '<h3 style="margin:0 0 12px">Quick Actions</h3>';
        echo '<p style="margin:0 0 12px"><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=' . Settings::PAGE)) . '">' . esc_html__('Fleet Settings &amp; Bulk Updates', 'gratis-fleet') . '</a></p>';
        echo '<p style="margin:0;color:#64748b">Or use WP-CLI for fleet management:</p>';
        echo '<pre style="background:#1e293b;color:#e2e8f0;padding:16px;border-radius:6px;margin-top:12px;overflow-x:auto">';
        echo "wp gratis-fleet status          # Check all sites\n";
        echo "wp gratis-fleet update-all       # Update plugins everywhere\n";
        echo "wp gratis-fleet purge-all        # Purge cache everywhere\n";
        echo "wp gratis-fleet security         # Security scan\n";
        echo "wp gratis-fleet seo              # SEO audit\n";
        echo "wp gratis-fleet broken-links     # Find broken links\n";
        echo "wp gratis-fleet uptime           # Check uptime\n";
        echo '</pre></div>';

        echo '</div>';
    }

    private static function renderLocalCard(array $security, array $seo): void {
        $score = $security['score'] ?? 0;
        $seoScore = $seo['score'] ?? 0;
        $scoreColor = $score >= 80 ? '#22c55e' : ($score >= 50 ? '#f59e0b' : '#ef4444');
        $seoColor = $seoScore >= 80 ? '#22c55e' : ($seoScore >= 50 ? '#f59e0b' : '#ef4444');

        echo '<div style="background:#fff;border:2px solid #22c55e;border-radius:12px;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,0.04)">';
        echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">';
        echo '<div><strong style="font-size:16px">' . esc_html(get_bloginfo('name')) . '</strong>';
        echo '<br><small style="color:#64748b">' . esc_html(home_url()) . '</small></div>';
        echo '<span style="background:#dcfce7;color:#166534;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600">This Site</span>';
        echo '</div>';

        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">';
        echo '<div style="background:#f8fafc;padding:12px;border-radius:8px;text-align:center">';
        echo '<div style="font-size:24px;font-weight:700;color:' . esc_attr($scoreColor) . '">' . absint($score) . '</div>';
        echo '<div style="font-size:11px;color:#64748b;text-transform:uppercase">Security</div></div>';
        echo '<div style="background:#f8fafc;padding:12px;border-radius:8px;text-align:center">';
        echo '<div style="font-size:24px;font-weight:700;color:' . esc_attr($seoColor) . '">' . absint($seoScore) . '</div>';
        echo '<div style="font-size:11px;color:#64748b;text-transform:uppercase">SEO</div></div>';
        echo '</div>';

        echo '<div style="margin-top:12px;font-size:13px;color:#64748b">';
        echo 'WP ' . esc_html(get_bloginfo('version')) . ' · PHP ' . esc_html(PHP_VERSION) . ' · ' . absint(count(get_option('active_plugins', []))) . ' plugins';
        echo '</div></div>';
    }

    private static function renderSiteCard(array $site): void {
        $status = ($site['status'] ?? '') === 'online';
        $borderColor = $status ? '#e2e8f0' : '#fecaca';
        $statusBadge = $status
            ? '<span style="background:#dcfce7;color:#166534;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600">Online</span>'
            : '<span style="background:#fef2f2;color:#991b1b;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600">Unknown</span>';

        echo '<div style="background:#fff;border:1px solid ' . esc_attr($borderColor) . ';border-radius:12px;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,0.04)">';
        echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">';
        echo '<div><strong style="font-size:16px">' . esc_html($site['label']) . '</strong>';
        echo '<br><small style="color:#64748b">' . esc_html($site['url']) . '</small></div>';
        echo wp_kses_post($statusBadge);
        echo '</div>';

        $lastSeen = $site['last_seen'] ? human_time_diff(strtotime($site['last_seen'])) . ' ago' : 'Never checked';
        echo '<div style="font-size:13px;color:#64748b">Last seen: ' . esc_html($lastSeen) . '</div>';
        echo '<div style="margin-top:12px"><a href="' . esc_url($site['url'] . '/wp-admin/') . '" target="_blank" style="font-size:13px">Open Admin ↗</a></div>';
        echo '</div>';
    }
}
