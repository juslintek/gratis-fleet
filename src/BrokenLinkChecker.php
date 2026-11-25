<?php declare(strict_types=1);
namespace Gratis\Fleet;

defined('ABSPATH') || exit;
/**
 * Broken link checker — what ManageWP charges extra for. Free in Gratis.
 * Scans post content for links and checks their HTTP status.
 */
final class BrokenLinkChecker {

    /** Scan all published posts for broken links. */
    public static function scan(int $limit = 50): array {
        $posts = get_posts([
            'post_type'      => ['post', 'page'],
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
        ]);

        $results = ['checked' => 0, 'broken' => [], 'redirects' => []];

        foreach ($posts as $post) {
            $links = self::extractLinks($post->post_content);
            foreach ($links as $url) {
                ++$results['checked'];
                $status = self::checkUrl($url);

                if ($status === 0 || $status >= 400) {
                    $results['broken'][] = [
                        'url'     => $url,
                        'status'  => $status,
                        'post_id' => $post->ID,
                        'post'    => $post->post_title,
                    ];
                } elseif ($status >= 300 && $status < 400) {
                    $results['redirects'][] = [
                        'url'     => $url,
                        'status'  => $status,
                        'post_id' => $post->ID,
                        'post'    => $post->post_title,
                    ];
                }
            }
        }

        return $results;
    }

    private static function extractLinks(string $content): array {
        preg_match_all('/href=["\']([^"\']+)["\']/', $content, $matches);
        $links = $matches[1] ?? [];
        // Filter to only external + internal http links
        return array_filter($links, fn($url) => str_starts_with($url, 'http'));
    }

    private static function checkUrl(string $url): int {
        $response = wp_remote_head($url, [
            'timeout'     => 5,
            'redirection' => 0,
        ]);
        if (is_wp_error($response)) return 0;
        return (int) wp_remote_retrieve_response_code($response);
    }
}
