<?php
/**
 * Standalone Read Time checks: settings resolution, sanitizing, the number
 * shown (minimum and override), markup, custom post types and site settings.
 *
 * Run: php tests/readtime.php
 */

define('ABSPATH', __DIR__);
define('DSME_PATH', dirname(__DIR__) . '/');
define('DSME_URL', 'https://example.test/');
define('DSME_MENU_SLUG', 'ds-multisite-enhancements');

$network_options = array();
$site_options    = array();
$post_meta       = array();
$posts           = array();
$current_post    = null;
$post_types      = array(
    'post'       => array('public' => true, 'viewable' => true),
    'page'       => array('public' => true, 'viewable' => true),
    'attachment' => array('public' => true, 'viewable' => true),
    // A custom post type registered without editor support.
    'portfolio'  => array('public' => true, 'viewable' => true),
    'private_cpt' => array('public' => false, 'viewable' => false),
);

class WP_Post
{
    public $ID;
    public $post_content = '';
    public $post_status  = 'publish';
    public $post_type    = 'post';

    public function __construct(array $props)
    {
        foreach ($props as $key => $value) {
            $this->$key = $value;
        }
    }
}
class WP_Block {}
class WP_Error {}

function add_action(...$args) {}
function add_filter(...$args) {}
function apply_filters($name, $value, ...$args)
{
    global $filters;
    return isset($filters[$name]) ? $filters[$name]($value, ...$args) : $value;
}
function __($text, $domain = '') { return $text; }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function wp_check_invalid_utf8($text) { return $text; }
function get_site_option($name, $default = false) { global $network_options; return $network_options[$name] ?? $default; }
function update_site_option($name, $value) { global $network_options; $network_options[$name] = $value; return true; }
function get_option($name, $default = false) { global $site_options; return $site_options[$name] ?? $default; }
function update_option($name, $value, $autoload = null) { global $site_options; $site_options[$name] = $value; return true; }
function delete_option($name) { global $site_options; unset($site_options[$name]); return true; }
function get_post($post = null)
{
    global $posts, $current_post;
    if ($post instanceof WP_Post) {
        return $post;
    }
    if (null === $post) {
        return $current_post;
    }
    return $posts[(int) $post] ?? null;
}
function get_post_meta($id, $key, $single = false) { global $post_meta; return $post_meta[$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { global $post_meta; $post_meta[$id][$key] = $value; return true; }
function delete_post_meta($id, $key) { global $post_meta; unset($post_meta[$id][$key]); return true; }
function post_type_exists($type) { global $post_types; return isset($post_types[$type]); }
function is_post_type_viewable($type) { global $post_types; return ! empty($post_types[$type]['viewable']); }
function get_post_types($args = array())
{
    global $post_types;
    $names = array();
    foreach ($post_types as $name => $type) {
        if (! isset($args['public']) || $type['public'] === $args['public']) {
            $names[$name] = $name;
        }
    }
    return $names;
}
// WordPress provides these in wp-includes/compat.php when mbstring is missing.
if (! function_exists('mb_substr')) {
    function mb_substr($text, $start, $length = null) { return implode('', array_slice(preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY), $start, $length)); }
    function mb_strlen($text) { return count(preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY)); }
}
function strip_shortcodes($content) { return preg_replace('/\[[^\]]*\]/', '', $content); }
function wp_strip_all_tags($text) { return trim(strip_tags($text)); }

foreach (array('display', 'docs', 'editor', 'readtime', 'settings', 'site-settings') as $file) {
    require dirname(__DIR__) . '/includes/readtime/' . $file . '.php';
}

function expect($condition, $message)
{
    if (! $condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
}

function add_post(int $id, int $words, array $props = array()): WP_Post
{
    global $posts;
    $posts[$id] = new WP_Post(array_merge(array('ID' => $id, 'post_content' => trim(str_repeat('word ', $words))), $props));
    return $posts[$id];
}

// Defaults.
$network = dsme_readtime_network_settings();
expect('Read Time: ' === $network['before'] && ' minutes' === $network['after'] && '' === $network['after_singular'], 'Default before/after text.');

// Singular: automatic from "minutes"/"mins", or the singular setting.
expect(' minute' === dsme_readtime_singularize(' minutes'), 'minutes -> minute.');
expect(' min' === dsme_readtime_singularize(' mins'), 'mins -> min.');
expect(' Minute read' === dsme_readtime_singularize(' Minutes read') && ' MIN' === dsme_readtime_singularize(' MINS'), 'Case is kept.');
expect(' hours' === dsme_readtime_singularize(' hours') && ' minutesque' === dsme_readtime_singularize(' minutesque'), 'Other words are unchanged.');
expect(' minute, minutes' === dsme_readtime_singularize(' minutes, minutes'), 'Only the first match changes.');
expect(' minute' === dsme_readtime_after_text(1, ' minutes', ''), 'Automatic singular for 1.');
expect(' minutes' === dsme_readtime_after_text(2, ' minutes', '') && ' minutes' === dsme_readtime_after_text(0, ' minutes', ''), 'Plural for other values.');
expect(' quick minute' === dsme_readtime_after_text(1, ' minutes', ' quick minute'), 'Singular setting wins for 1.');
expect(' minutes' === dsme_readtime_after_text('𝑥', ' minutes', ' minute'), 'Placeholder uses the plural.');
expect(0 === $network['minimum'] && ! $network['allow_override'] && ! $network['allow_sites'], 'Overrides and site settings are off by default.');

// Sanitizing keeps the separating spaces but strips tags and newlines.
expect('Read Time: ' === dsme_readtime_sanitize_text('Read Time: '), 'Trailing space is kept.');
expect(' minutes' === dsme_readtime_sanitize_text(' minutes'), 'Leading space is kept.');
expect('a b' === dsme_readtime_sanitize_text("<b>a</b>\nb"), 'Tags and newlines are removed.');
expect(DSME_READTIME_MAX_TEXT === mb_strlen(dsme_readtime_sanitize_text(str_repeat('x', 500))), 'Text is length-limited.');
expect('' === dsme_readtime_sanitize_text(array('x')), 'Non-scalar text is rejected.');

expect(0 === dsme_readtime_sanitize_minimum(''), 'Blank minimum is 0.');
expect(3 === dsme_readtime_sanitize_minimum(' 3 '), 'Numeric minimum.');
expect(null === dsme_readtime_sanitize_minimum('-1'), 'Negative minimum is invalid.');
expect(null === dsme_readtime_sanitize_minimum('2.5'), 'Fractional minimum is invalid.');
expect(null === dsme_readtime_sanitize_minimum('1000'), 'Minimum above the maximum is invalid.');

expect(7 === dsme_readtime_sanitize_override('7'), 'Override accepts whole numbers.');
expect(0 === dsme_readtime_sanitize_override(''), 'Blank override is none.');
expect(0 === dsme_readtime_sanitize_override('0') && 0 === dsme_readtime_sanitize_override('-2') && 0 === dsme_readtime_sanitize_override('1.5') && 0 === dsme_readtime_sanitize_override('abc'), 'Invalid overrides are none.');

// Network save validation: nothing is saved when a field is invalid.
expect('wpm' === dsme_readtime_save_network_settings(array('dsme_words_per_minute' => '0')), 'Invalid WPM is rejected.');
expect('minimum' === dsme_readtime_save_network_settings(array('dsme_words_per_minute' => '200', 'dsme_readtime_minimum' => 'x')), 'Invalid minimum is rejected.');
expect(! isset($network_options[DSME_OPTION_WPM]), 'Rejected saves change nothing.');

expect(true === dsme_readtime_save_network_settings(array(
    'dsme_words_per_minute' => '100',
    'dsme_readtime_before'  => 'Read Time: ',
    'dsme_readtime_after'   => ' minutes',
    'dsme_readtime_minimum' => '3',
)), 'Valid network settings save.');
expect(100 === dsme_get_words_per_minute(), 'WPM saved.');
expect('Read Time: ' === dsme_readtime_setting('before') && ' minutes' === dsme_readtime_setting('after') && 3 === dsme_readtime_setting('minimum'), 'Network values resolve.');

// Number shown: calculated, raised to the minimum.
add_post(1, 1000); // 10 minutes at 100 WPM.
add_post(2, 50);   // 1 minute, below the minimum of 3.
expect(10 === dsme_readtime_display_minutes(1), 'Calculated time above the minimum.');
expect(1 === dsme_get_readtime(2) && 3 === dsme_readtime_display_minutes(2), 'Minimum raises a short read time.');

// Overrides apply only while allowed, and win over the minimum.
dsme_readtime_save_override(2, '2');
expect(2 === dsme_get_readtime_override(2), 'Override saved.');
expect(3 === dsme_readtime_display_minutes(2), 'Overrides are ignored while not allowed.');
$network_options[DSME_OPTION_READTIME]['allow_override'] = true;
expect(2 === dsme_readtime_display_minutes(2), 'Allowed override wins, even below the minimum.');
dsme_readtime_save_override(2, '');
expect(! isset($post_meta[2][DSME_META_OVERRIDE]) && 3 === dsme_readtime_display_minutes(2), 'Clearing the override deletes it.');

// Display uses the singular after text for 1 (override of 1 here).
dsme_readtime_save_override(2, '1');
expect(false !== strpos(dsme_get_readtime_display(2), '<span class="dsme-readtime-after"> minute</span>'), 'Display singular for a read time of 1.');
dsme_readtime_save_override(2, '');

// Markup: escaped, spaces kept.
$html = dsme_get_readtime_display(1);
expect('<span class="dsme-readtime"><span class="dsme-readtime-before">Read Time: </span><span class="dsme-readtime-value">10</span><span class="dsme-readtime-after"> minutes</span></span>' === $html, 'Display markup: ' . $html);
expect(false !== strpos(dsme_readtime_markup(5, '<b>', ''), '&lt;b&gt;'), 'Before/after text is escaped.');
expect('' === dsme_get_readtime_display(999), 'Missing post displays nothing.');
$current_post = get_post(1);
ob_start();
dsme_the_readtime();
expect($html === ob_get_clean(), 'dsme_the_readtime() echoes the current post.');

// Custom post types: tracked without editor support; attachments and private types are not.
expect(array('post', 'page', 'portfolio') === dsme_readtime_post_types(), 'Tracked post types: ' . implode(', ', dsme_readtime_post_types()));
add_post(3, 300, array('post_type' => 'portfolio'));
expect(3 === dsme_get_readtime(3), 'Portfolio post read time.');

// Content filter for text kept outside post_content.
$filters['dsme_readtime_content'] = function ($content, $post) {
    return 4 === $post->ID ? trim(str_repeat('field ', 700)) : $content;
};
add_post(4, 0, array('post_type' => 'portfolio'));
expect(7 === dsme_get_readtime(4), 'dsme_readtime_content supplies the counted text.');
$filters = array();

// Drafts are calculated without saving; never-saved posts have no REST value.
add_post(5, 400, array('post_status' => 'draft'));
expect(4 === dsme_get_readtime(5) && ! isset($post_meta[5]), 'Drafts are not saved.');
add_post(6, 400, array('post_status' => 'auto-draft'));
expect(null === dsme_readtime_rest_get(array('id' => 6))['calculated'], 'New posts show the placeholder.');
expect(4 === dsme_readtime_rest_get(array('id' => 5))['calculated'], 'Saved drafts have a value.');

// Site settings are ignored until the network allows them.
expect(true === dsme_readtime_save_site_settings(array('dsme_readtime_before' => 'Reading: ', 'dsme_readtime_after' => ' minutes', 'dsme_readtime_minimum' => '3')), 'Site save.');
expect(array('before' => 'Reading: ') === $site_options[DSME_OPTION_READTIME_SITE], 'Only values that differ from the network are stored.');
dsme_readtime_save_site_settings(array('dsme_readtime_before' => 'Reading: ', 'dsme_readtime_after' => ' minutes', 'dsme_readtime_after_singular' => ' min', 'dsme_readtime_minimum' => '3'));
$network_options[DSME_OPTION_READTIME]['allow_sites'] = true;
expect(' min' === dsme_readtime_setting('after_singular'), 'Sites can override the singular text.');
$network_options[DSME_OPTION_READTIME]['allow_sites'] = false;
dsme_readtime_save_site_settings(array('dsme_readtime_before' => 'Reading: ', 'dsme_readtime_after' => ' minutes', 'dsme_readtime_minimum' => '3'));
expect('Read Time: ' === dsme_readtime_setting('before'), 'Site values are ignored while not allowed.');
$network_options[DSME_OPTION_READTIME]['allow_sites'] = true;
expect('Reading: ' === dsme_readtime_setting('before') && ' minutes' === dsme_readtime_setting('after'), 'Site value applies; others follow the network.');

// Un-customized fields follow later network changes.
$network_options[DSME_OPTION_READTIME]['after'] = ' min';
expect(' min' === dsme_readtime_setting('after'), 'Network change reaches sites that did not customize it.');

// A site can clear the network minimum and text.
dsme_readtime_save_site_settings(array('dsme_readtime_before' => '', 'dsme_readtime_after' => ' min', 'dsme_readtime_minimum' => ''));
expect(1 === dsme_readtime_display_minutes(2) && '' === dsme_readtime_setting('before'), 'Site can set no minimum and empty text.');
expect('minimum' === dsme_readtime_save_site_settings(array('dsme_readtime_minimum' => 'abc')), 'Invalid site minimum is rejected.');

// Sites cannot change network-only settings.
$site_options[DSME_OPTION_READTIME_SITE]['allow_override'] = false;
expect(true === dsme_readtime_setting('allow_override'), 'allow_override is network-only.');

// Reset Defaults: deleting the site option falls back to the network.
delete_option(DSME_OPTION_READTIME_SITE);
expect('Read Time: ' === dsme_readtime_setting('before') && 3 === dsme_readtime_setting('minimum'), 'Reset falls back to the network values.');

echo 'Read Time checks passed.' . PHP_EOL;
