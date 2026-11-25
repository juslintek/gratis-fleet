<?php declare(strict_types=1);
namespace Gratis\Fleet;

defined('ABSPATH') || exit;

/**
 * SEO auditor — meta tags, headings, schema, sitemap check.
 */
final class SEOAuditor {

    /** Audit a URL for SEO issues. */
    public static function audit(string $url): array {
        $response = wp_remote_get($url, ['timeout' => 15]);
        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $html = wp_remote_retrieve_body($response);
        $issues = [];
        $score = 100;

        // Title
        preg_match('/<title>([^<]*)<\/title>/', $html, $titleMatch);
        $title = $titleMatch[1] ?? '';
        if (empty($title)) {
            $issues[] = ['severity' => 'high', 'detail' => 'Missing <title> tag'];
            $score -= 20;
        } elseif (strlen($title) > 60) {
            $issues[] = ['severity' => 'medium', 'detail' => 'Title too long (' . strlen($title) . ' chars, max 60)'];
            $score -= 5;
        }

        // Meta description
        preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\']/', $html, $descMatch);
        $desc = $descMatch[1] ?? '';
        if (empty($desc)) {
            $issues[] = ['severity' => 'high', 'detail' => 'Missing meta description'];
            $score -= 15;
        } elseif (strlen($desc) > 160) {
            $issues[] = ['severity' => 'low', 'detail' => 'Meta description too long (' . strlen($desc) . ' chars, max 160)'];
            $score -= 3;
        }

        // H1
        preg_match_all('/<h1[^>]*>/', $html, $h1s);
        $h1Count = count($h1s[0]);
        if ($h1Count === 0) {
            $issues[] = ['severity' => 'high', 'detail' => 'No H1 heading found'];
            $score -= 15;
        } elseif ($h1Count > 1) {
            $issues[] = ['severity' => 'medium', 'detail' => "Multiple H1 tags ({$h1Count})"];
            $score -= 5;
        }

        // Open Graph
        if (!str_contains($html, 'og:title')) {
            $issues[] = ['severity' => 'medium', 'detail' => 'Missing Open Graph tags'];
            $score -= 5;
        }

        // Canonical
        if (!preg_match('/<link[^>]+rel=["\']canonical["\']/', $html)) {
            $issues[] = ['severity' => 'medium', 'detail' => 'Missing canonical URL'];
            $score -= 5;
        }

        // Images without alt
        preg_match_all('/<img[^>]*>/', $html, $imgs);
        $noAlt = 0;
        foreach ($imgs[0] as $img) {
            if (!str_contains($img, 'alt=') || preg_match('/alt=["\']["\']/', $img)) $noAlt++;
        }
        if ($noAlt > 0) {
            $issues[] = ['severity' => 'medium', 'detail' => "{$noAlt} image(s) missing alt text"];
            $score -= min(10, $noAlt * 2);
        }

        // Schema/structured data
        if (!str_contains($html, 'application/ld+json') && !str_contains($html, 'itemtype')) {
            $issues[] = ['severity' => 'low', 'detail' => 'No structured data (Schema.org) found'];
            $score -= 3;
        }

        // Sitemap check
        $sitemapUrl = rtrim($url, '/') . '/sitemap.xml';
        $sitemapResp = wp_remote_head($sitemapUrl, ['timeout' => 5]);
        if (is_wp_error($sitemapResp) || wp_remote_retrieve_response_code($sitemapResp) !== 200) {
            $issues[] = ['severity' => 'medium', 'detail' => 'Sitemap not found at /sitemap.xml'];
            $score -= 5;
        }

        return [
            'url'    => $url,
            'score'  => max(0, $score),
            'title'  => $title,
            'desc'   => $desc,
            'issues' => $issues,
        ];
    }

    /** Audit the entire site (homepage + sample pages). */
    public static function auditSite(): array {
        $urls = [home_url('/')];

        // Add a few published pages
        $pages = get_posts(['post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 5]);
        foreach ($pages as $page) {
            $urls[] = get_permalink($page);
        }

        $results = [];
        foreach ($urls as $url) {
            $results[] = self::audit($url);
        }

        $avgScore = count($results) > 0
            ? round(array_sum(array_column($results, 'score')) / count($results))
            : 0;

        return ['average_score' => $avgScore, 'pages' => $results];
    }
}
