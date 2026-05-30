<?php declare(strict_types=1);
namespace Gratis\Fleet\Admin;

use Gratis\Fleet\SiteManager;

final class Dashboard
{
    public static function render(): void
    {
        $sites = SiteManager::all();
        echo '<div class="wrap"><h1>Fleet Manager</h1>';
        echo '<p>Managing ' . count($sites) . ' site(s). Use <code>wp gratis-fleet</code> CLI for full control.</p>';

        if (empty($sites)) {
            echo '<div class="notice notice-info"><p>No sites added yet. Run: <code>wp gratis-fleet add &lt;url&gt; &lt;api-key&gt;</code></p></div>';
            echo '</div>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
        echo '<th>Site</th><th>Status</th><th>Last Seen</th><th>Actions</th>';
        echo '</tr></thead><tbody>';

        foreach ($sites as $site) {
            $status = $site['status'] === 'online'
                ? '<span style="color:#22c55e">● Online</span>'
                : '<span style="color:#94a3b8">● Unknown</span>';
            $lastSeen = $site['last_seen'] ? human_time_diff(strtotime($site['last_seen'])) . ' ago' : 'Never';

            echo "<tr>";
            echo "<td><strong>{$site['label']}</strong><br><small>{$site['url']}</small></td>";
            echo "<td>{$status}</td>";
            echo "<td>{$lastSeen}</td>";
            echo "<td><a href='{$site['url']}/wp-admin/' target='_blank'>Open Admin ↗</a></td>";
            echo "</tr>";
        }

        echo '</tbody></table></div>';
    }
}
