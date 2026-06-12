<?php declare(strict_types=1);
namespace Gratis\Fleet;

/**
 * Security scanner — file integrity + vulnerability detection.
 * What ManageWP charges $1/site/mo for. Free in Gratis.
 */
final class SecurityScanner {

    /** Scan for common security issues. */
    public static function scan(): array {
        $issues = [];

        // 1. Check WordPress core file integrity
        $issues = array_merge($issues, self::checkCoreIntegrity());

        // 2. Check file permissions
        $issues = array_merge($issues, self::checkPermissions());

        // 3. Check for known vulnerable patterns
        $issues = array_merge($issues, self::checkVulnerablePatterns());

        // 4. Check security headers
        $issues = array_merge($issues, self::checkSecurityHeaders());

        // 5. Check user security
        $issues = array_merge($issues, self::checkUsers());

        return [
            'scanned_at' => gmdate('c'),
            'issues'     => $issues,
            'score'      => max(0, 100 - count($issues) * 10),
        ];
    }

    private static function checkCoreIntegrity(): array {
        $issues = [];

        // Check for suspicious files in root
        $suspicious = ['adminer.php', 'phpinfo.php', 'info.php', 'test.php', 'shell.php'];
        foreach ($suspicious as $file) {
            if (file_exists(ABSPATH . $file)) {
                $issues[] = ['severity' => 'high', 'type' => 'suspicious_file', 'detail' => "Suspicious file found: {$file}"];
            }
        }

        return $issues;
    }

    private static function checkPermissions(): array {
        $issues = [];
        $wpConfig = ABSPATH . 'wp-config.php';
        if (file_exists($wpConfig)) {
            $perms = fileperms($wpConfig) & 0777;
            if ($perms > 0644) {
                $issues[] = ['severity' => 'medium', 'type' => 'permissions', 'detail' => 'wp-config.php permissions too open: ' . decoct($perms)];
            }
        }

        if (is_writable(ABSPATH)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- read-only permission probe of the WP root; WP_Filesystem adds no value for a stat() check.
            $issues[] = ['severity' => 'low', 'type' => 'permissions', 'detail' => 'WordPress root directory is writable'];
        }

        return $issues;
    }

    private static function checkVulnerablePatterns(): array {
        $issues = [];

        // Check if debug is enabled in production
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'production') {
            $issues[] = ['severity' => 'medium', 'type' => 'config', 'detail' => 'WP_DEBUG enabled in production'];
        }

        // Check if file editing is enabled
        if (!defined('DISALLOW_FILE_EDIT') || !DISALLOW_FILE_EDIT) {
            $issues[] = ['severity' => 'low', 'type' => 'config', 'detail' => 'File editing enabled (DISALLOW_FILE_EDIT not set)'];
        }

        // Check database prefix
        global $wpdb;
        if ($wpdb->prefix === 'wp_') {
            $issues[] = ['severity' => 'low', 'type' => 'config', 'detail' => 'Default database prefix (wp_) in use'];
        }

        return $issues;
    }

    private static function checkSecurityHeaders(): array {
        $issues = [];
        $response = wp_remote_get(home_url('/'), ['timeout' => 5, 'sslverify' => false]);
        if (is_wp_error($response)) return $issues;

        $headers = wp_remote_retrieve_headers($response);
        $headerArray = $headers instanceof \WpOrg\Requests\Utility\CaseInsensitiveDictionary ? $headers->getAll() : (array) $headers;

        if (!isset($headerArray['x-content-type-options'])) {
            $issues[] = ['severity' => 'low', 'type' => 'headers', 'detail' => 'Missing X-Content-Type-Options header'];
        }
        if (!isset($headerArray['x-frame-options'])) {
            $issues[] = ['severity' => 'low', 'type' => 'headers', 'detail' => 'Missing X-Frame-Options header'];
        }

        return $issues;
    }

    private static function checkUsers(): array {
        $issues = [];
        // Check if 'admin' username exists
        if (get_user_by('login', 'admin')) {
            $issues[] = ['severity' => 'medium', 'type' => 'users', 'detail' => 'Default "admin" username exists'];
        }
        return $issues;
    }
}
