<?php declare(strict_types=1);
namespace Gratis\Fleet;

/** Manages the fleet of connected WordPress sites. */
final class SiteManager {

    private const OPTION = 'gratis_fleet_sites';

    public static function add(string $url, string $apiKey, string $label = ''): array {
        $sites = self::all();
        $id = md5($url);
        $sites[$id] = [
            'id'      => $id,
            'url'     => rtrim($url, '/'),
            'api_key' => $apiKey,
            'label'   => $label ?: wp_parse_url($url, PHP_URL_HOST),
            'added'   => gmdate('c'),
            'last_seen' => null,
            'status'  => 'unknown',
        ];
        update_option(self::OPTION, $sites);
        return $sites[$id];
    }

    public static function remove(string $id): bool {
        $sites = self::all();
        if (!isset($sites[$id])) return false;
        unset($sites[$id]);
        update_option(self::OPTION, $sites);
        return true;
    }

    public static function all(): array {
        return get_option(self::OPTION, []);
    }

    public static function get(string $id): ?array {
        return self::all()[$id] ?? null;
    }

    /** Ping a remote site's worker endpoint to get status. */
    public static function ping(array $site): array {
        $response = wp_remote_get($site['url'] . '/wp-json/gratis-fleet/v1/status', [
            'headers' => ['X-Fleet-Key' => $site['api_key']],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return ['online' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return ['online' => false, 'error' => "HTTP {$code}"];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        // Update last_seen
        $sites = self::all();
        if (isset($sites[$site['id']])) {
            $sites[$site['id']]['last_seen'] = gmdate('c');
            $sites[$site['id']]['status'] = 'online';
            update_option(self::OPTION, $sites);
        }

        return array_merge(['online' => true], $data ?? []);
    }

    /** Send a command to a remote site. */
    public static function command(array $site, string $action, array $params = []): array {
        $response = wp_remote_post($site['url'] . '/wp-json/gratis-fleet/v1/command', [
            'headers' => [
                'X-Fleet-Key'  => $site['api_key'],
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode(['action' => $action, 'params' => $params]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        return json_decode(wp_remote_retrieve_body($response), true) ?? ['success' => false];
    }
}
