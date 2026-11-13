<?php
define('ABSPATH', __DIR__);
$options = array();
$allowed = true;
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_dir_url($file) { return 'https://example.test/'; }
function add_action(...$args) {}
function get_site_option($name, $default = false) { global $options; return $options[$name] ?? $default; }
function update_site_option($name, $value) { global $options; $options[$name] = $value; return true; }
function apply_filters($name, $value) { return $value; }
function __($text, $domain) { return $text; }
function current_user_can($capability) { global $allowed; return $allowed; }
function wp_unslash($value) { return $value; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)); }

require dirname(__DIR__) . '/ds-multisite-enhancements.php';
require dirname(__DIR__) . '/includes/00-core/admin-menu.php';

function expect($condition, $message)
{
    if (! $condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$dir = dirname(__DIR__) . '/includes';
$readtime = $dir . '/readtime/readtime.php';
expect(! in_array($readtime, dsme_list_includes($dir), true), 'Read Time must be disabled by default.');
expect(in_array($dir . '/00-core/admin-menu.php', dsme_list_includes($dir), true), 'Core must always load.');

$_POST = array('feature' => 'readtime', 'enabled' => '1');
expect(dsme_save_feature_toggle(), 'Enabling Read Time failed.');
expect(dsme_feature_enabled('readtime'), 'Read Time must be enabled.');
expect(in_array($readtime, dsme_list_includes($dir), true), 'Enabled Read Time must load.');

$allowed = false;
$_POST = array('feature' => 'readtime');
expect(! dsme_save_feature_toggle(), 'Unauthorized changes must fail.');
expect(dsme_feature_enabled('readtime'), 'Unauthorized changes must not change the option.');

$allowed = true;
$_POST = array('feature' => 'unknown', 'enabled' => '1');
expect(! dsme_save_feature_toggle(), 'Unknown features must be rejected.');
$_POST = array('feature' => 'readtime');
expect(dsme_save_feature_toggle(), 'Disabling Read Time failed.');
expect(! dsme_feature_enabled('readtime'), 'Read Time must be disabled.');
expect(! in_array($readtime, dsme_list_includes($dir), true), 'Disabled Read Time must not load.');

$checker = $dir . '/network-use-checker/plugin-use-check.php';
expect(! in_array($checker, dsme_list_includes($dir), true), 'Network Use Checker must be disabled by default.');
$_POST = array('feature' => 'network-use-checker', 'enabled' => '1');
expect(dsme_save_feature_toggle(), 'Enabling Network Use Checker failed.');
expect(in_array($checker, dsme_list_includes($dir), true), 'Enabled Network Use Checker must load.');
expect(in_array($dir . '/network-use-checker/theme-use-check.php', dsme_list_includes($dir), true), 'Theme Use Check must load.');
expect(! in_array($dir . '/network-use-checker/index.php', dsme_list_includes($dir), true), 'index.php guard must not load.');

echo 'Feature toggle checks passed.' . PHP_EOL;