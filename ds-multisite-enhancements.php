<?php
/**
 * Plugin Name: DS Multisite Enhancements
 * Description: A toolkit of network-level enhancements for WordPress Multisite networks at scale.
 * Version:     0.0.1
 * Author:      Daniel Santoro
 * Network:     true
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License:     GPL2
 * Text Domain: ds-multisite-enhancements
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

define('DSME_VERSION', '0.0.1');
define('DSME_FILE', __FILE__);
define('DSME_PATH', plugin_dir_path(__FILE__));
define('DSME_URL', plugin_dir_url(__FILE__));

/**
 * Enabled features live in includes/ and are loaded automatically.
 *
 * - A single-file feature is includes/<feature>.php.
 * - A multi-file feature is a folder, includes/<feature>/, and every *.php file directly
 *   inside it is loaded. Subfolders are not loaded, so a feature can keep templates or
 *   partials in e.g. includes/<feature>/templates/ and require them itself.
 * - Files and folders are loaded in natural order (natsort), so prefix names to control
 *   order (e.g. 00-foo.php, 10-bar/). Files inside a folder are ordered the same way.
 * - Names starting with "_" are skipped (e.g. _disabled-feature/ or _helper.php that a
 *   feature requires itself). The 00-core folder is always loaded; other entries
 *   load only when enabled in the network option dsme_enabled_features.
 */
add_action('plugins_loaded', 'dsme_load_includes', 1);
function dsme_load_includes(): void
{
    $dir = DSME_PATH . 'includes';
    if (! is_dir($dir)) {
        return;
    }

    $failed = array();

    foreach (dsme_list_includes($dir) as $file) {
        try {
            require_once $file;
        } catch (\Throwable $e) {
            $relative = ltrim(str_replace('\\', '/', substr($file, strlen($dir))), '/');
            $failed[] = $relative;
            error_log(sprintf(
                '[DS Multisite Enhancements] Failed to load %s: %s in %s:%d',
                $relative,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
        }
    }

    if (! empty($failed)) {
        $notice = function () use ($failed): void {
            if (! current_user_can('manage_network_options')) {
                return;
            }
            printf(
                '<div class="notice notice-error is-dismissible"><p><strong>DS Multisite Enhancements:</strong> %s %s</p></div>',
                esc_html__('The following include file(s) failed to load and have been skipped:', 'ds-multisite-enhancements'),
                esc_html(implode(', ', $failed)) . '. ' . esc_html__('Check the PHP error log for details.', 'ds-multisite-enhancements')
            );
        };
        add_action('network_admin_notices', $notice);
        add_action('admin_notices', $notice);
    }
}

/**
 * The PHP files to load from includes/, in load order: top-level *.php files and
 * feature folders sorted together, with each folder expanded to its *.php files.
 *
 * @return string[] Absolute file paths.
 */
function dsme_list_includes(string $dir): array
{
    $entries = array_merge(
        glob($dir . '/*.php') ?: array(),
        glob($dir . '/*', GLOB_ONLYDIR) ?: array()
    );
    natsort($entries);

    $files = array();
    $enabled_features = get_site_option('dsme_enabled_features', array());
    $enabled_features = is_array($enabled_features) ? $enabled_features : array();

    foreach ($entries as $entry) {
        if (dsme_skip_include($entry)) {
            continue;
        }

        $feature = is_dir($entry) ? basename($entry) : pathinfo($entry, PATHINFO_FILENAME);
        if ('00-core' !== $feature && empty($enabled_features[$feature])) {
            continue;
        }

        if (! is_dir($entry)) {
            $files[] = $entry;
            continue;
        }

        $feature_files = glob($entry . '/*.php') ?: array();
        natsort($feature_files);

        foreach ($feature_files as $file) {
            if (! dsme_skip_include($file)) {
                $files[] = $file;
            }
        }
    }

    return $files;
}

/**
 * Whether the loader skips a file or folder: "_"-prefixed names, and the
 * index.php directory-listing guards.
 */
function dsme_skip_include(string $path): bool
{
    $name = basename($path);

    return 0 === strpos($name, '_') || 'index.php' === $name;
}
