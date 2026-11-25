<?php declare(strict_types=1);
namespace Gratis\Fleet;

defined('ABSPATH') || exit;
/**
 * Integration hub: webhooks + pre-built notifications.
 * Replaces Zapier for WordPress-to-external-service connections.
 */
final class WebhookHub {

    private const OPTION = 'gratis_fleet_webhooks';

    public static function init(): void {
        $webhooks = self::all();
        foreach ($webhooks as $wh) {
            if (!($wh['active'] ?? true)) continue;
            add_action($wh['hook'], function () use ($wh) {
                self::fire($wh, func_get_args());
            }, 99);
        }

        // Register receiver endpoint
        register_rest_route('gratis-fleet/v1', '/webhook/receive', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'receive'],
            'permission_callback' => [__CLASS__, 'authenticateReceiver'],
        ]);
    }

    public static function add(string $hook, string $url, string $label = '', string $format = 'json'): void {
        $all = self::all();
        $all[] = ['hook' => $hook, 'url' => $url, 'label' => $label ?: $hook, 'format' => $format, 'active' => true, 'created' => gmdate('c')];
        update_option(self::OPTION, $all, false);
    }

    public static function remove(int $index): bool {
        $all = self::all();
        if (!isset($all[$index])) return false;
        array_splice($all, $index, 1);
        update_option(self::OPTION, $all, false);
        return true;
    }

    public static function all(): array {
 return get_option(self::OPTION, []); }

    public static function fire(array $webhook, array $args): void {
        $payload = [
            'hook'      => $webhook['hook'],
            'site'      => get_site_url(),
            'timestamp' => gmdate('c'),
            'data'      => $args,
        ];

        $url = $webhook['url'];

        // Pre-built integrations
        if (str_contains($url, 'hooks.slack.com')) {
            $payload = ['text' => "[{$webhook['label']}] triggered on " . get_site_url()];
        } elseif (str_contains($url, 'discord.com/api/webhooks')) {
            $payload = ['content' => "[{$webhook['label']}] triggered on " . get_site_url()];
        }

        wp_safe_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($payload),
            'timeout' => 5,
            'blocking' => false,
        ]);
    }

    public static function receive(\WP_REST_Request $request): \WP_REST_Response {
        $data  = $request->get_json_params();
        $data  = is_array($data) ? $data : [];
        $event = sanitize_key((string) ($data['action'] ?? 'webhook'));
        do_action('gratis_fleet_webhook_received', $event, $data);
        return new \WP_REST_Response(['received' => true, 'event' => $event]);
    }

    public static function authenticateReceiver(\WP_REST_Request $request): bool {
        $key    = $request->get_header('X-Webhook-Key');
        $stored = get_option('gratis_fleet_api_key', '');
        return is_string($stored) && '' !== $stored && is_string($key) && '' !== $key && hash_equals($stored, $key);
    }
}
