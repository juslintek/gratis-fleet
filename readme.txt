=== Gratis Fleet ===
Contributors: juslintek
Tags: management, maintenance, monitoring, updates, fleet
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Multi-WordPress fleet management: bulk updates, uptime monitoring, and security scans.

== Description ==

Multi-WordPress fleet management: bulk updates, uptime monitoring, and security scans.

Part of the Gratis suite — a collection of free, no-upsell WordPress plugins.

**Features:**

* Manage unlimited sites from one dashboard
* Fleet-wide bulk plugin updates
* Uptime monitoring
* Security and SEO scanning

**Philosophy:**

Everything is free. No "Pro" version, no locked features, no nag screens. Optional managed cloud services may be offered separately to cover infrastructure costs.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Configure it from its settings screen in the WordPress admin.

== Frequently Asked Questions ==

= Is this plugin really free? =

Yes. Everything is free — no "Pro" version, no locked features, and no nag screens. Optional managed cloud services may be offered separately to cover infrastructure costs.

= Does this plugin make external HTTP requests? =

Only to the remote WordPress sites and optional webhook URLs that you explicitly configure for fleet management and alerts.

= Where do I get support? =

Open an issue on the plugin's GitHub repository.

== Third-Party Services ==

This plugin connects only to endpoints you configure:

= Managed WordPress Sites =

When you register child sites in the Fleet dashboard, the plugin sends authenticated HTTP requests to those sites' REST APIs for health checks, uptime monitoring, SEO/security audits, and bulk updates.

= Optional Webhook Alerts (Slack / Discord / Custom) =

When you add webhook URLs in the settings, notifications are sent to the configured URL (such as Slack or Discord incoming webhooks).

* Slack: [Terms of Service](https://slack.com/terms-of-service) | [Privacy Policy](https://slack.com/privacy-policy)
* Discord: [Terms of Service](https://discord.com/terms) | [Privacy Policy](https://discord.com/privacy)

No external requests are made unless you configure managed sites or webhooks.

== Changelog ==

= 1.0.0 =
* Initial public release.

== Upgrade Notice ==

= 1.0.0 =
Initial public release.
