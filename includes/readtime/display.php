<?php
/**
 * Read time display: the number shown, the markup, the theme functions, the
 * [dsme-readtime] shortcode and the dsme/readtime block.
 *
 * The number shown is the post's override (when per-post overrides are
 * allowed and the post has one), otherwise the calculated read time raised to
 * the minimum time. The text before and after it comes from the site's
 * settings, falling back to the network's.
 *
 * Call these in the post's own blog context: on network listings, after
 * switch_to_blog() to the post's site.
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

/** Per-post override, in whole minutes. Absent (or 0) means "use the calculated time". */
define('DSME_META_OVERRIDE', 'dsme-readtime-override');

/** Highest accepted per-post override, in minutes. */
define('DSME_READTIME_MAX_OVERRIDE', 9999);

/**
 * A post's override in minutes, or 0 when it has none. Returned whether or not
 * overrides are currently allowed; dsme_readtime_display_minutes() checks that.
 *
 * @param int|WP_Post|null $post
 */
function dsme_get_readtime_override($post = null): int
{
    $post = get_post($post);

    return $post ? dsme_readtime_sanitize_override(get_post_meta($post->ID, DSME_META_OVERRIDE, true)) : 0;
}

/**
 * Whole minutes from 1 to DSME_READTIME_MAX_OVERRIDE, or 0 for blank/invalid.
 *
 * @param mixed $value
 */
function dsme_readtime_sanitize_override($value): int
{
    if (is_string($value)) {
        $value = trim($value);
    }
    if (! is_numeric($value) || (int) $value != $value) {
        return 0;
    }

    $value = (int) $value;

    return ($value >= 1 && $value <= DSME_READTIME_MAX_OVERRIDE) ? $value : 0;
}

/**
 * Raise a calculated read time to the site's minimum time.
 */
function dsme_readtime_apply_minimum(int $minutes): int
{
    return max($minutes, (int) dsme_readtime_setting('minimum'));
}

/**
 * The number to display for a post, in minutes: its override when overrides
 * are allowed and it has one, otherwise the calculated time raised to the
 * minimum. 0 when there is no post.
 *
 * @param int|WP_Post|null $post Post ID or object; defaults to the current post.
 */
function dsme_readtime_display_minutes($post = null): int
{
    $post = get_post($post);
    if (! $post) {
        return 0;
    }

    if (dsme_readtime_setting('allow_override')) {
        $override = dsme_get_readtime_override($post);
        if ($override > 0) {
            return $override;
        }
    }

    return dsme_readtime_apply_minimum(dsme_get_readtime($post));
}

/**
 * The read-time markup. The before/after text is output exactly as entered
 * (escaped), so any spaces in it are kept:
 *
 *     <span class="dsme-readtime"><span class="dsme-readtime-before">Read Time: </span><span class="dsme-readtime-value">5</span><span class="dsme-readtime-after"> minutes</span></span>
 *
 * @param int|string $value The number, or a placeholder such as "𝑥".
 */
function dsme_readtime_markup($value, string $before, string $after): string
{
    return '<span class="dsme-readtime">'
        . '<span class="dsme-readtime-before">' . esc_html($before) . '</span>'
        . '<span class="dsme-readtime-value">' . esc_html((string) $value) . '</span>'
        . '<span class="dsme-readtime-after">' . esc_html($after) . '</span>'
        . '</span>';
}

/**
 * The read time for a post as HTML, using the site's before/after text
 * (the singular after text when the number is 1).
 * Returns an empty string when there is no post.
 *
 * Filter the result with "dsme_readtime_output" ($html, $minutes, $post_id).
 *
 * @param int|WP_Post|null $post Post ID or object; defaults to the current post.
 */
function dsme_get_readtime_display($post = null): string
{
    $post = get_post($post);
    if (! $post) {
        return '';
    }

    $minutes = dsme_readtime_display_minutes($post);
    $after   = dsme_readtime_after_text($minutes, (string) dsme_readtime_setting('after'), (string) dsme_readtime_setting('after_singular'));
    $html    = dsme_readtime_markup($minutes, (string) dsme_readtime_setting('before'), $after);

    return (string) apply_filters('dsme_readtime_output', $html, $minutes, (int) $post->ID);
}

/**
 * Echo the read time for a post. For use in theme templates:
 *
 *     <?php if ( function_exists( 'dsme_the_readtime' ) ) { dsme_the_readtime(); } ?>
 *
 * @param int|WP_Post|null $post Post ID or object; defaults to the current post.
 */
function dsme_the_readtime($post = null): void
{
    echo dsme_get_readtime_display($post); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in dsme_readtime_markup().
}

/**
 * [dsme-readtime] shows the current post's read time; [dsme-readtime post_id="123"]
 * shows another post's on the same site. [dsme_readtime] is an alias.
 */
add_action('init', 'dsme_register_shortcodes', 20);
function dsme_register_shortcodes(): void
{
    add_shortcode('dsme-readtime', 'dsme_readtime_shortcode');
    add_shortcode('dsme_readtime', 'dsme_readtime_shortcode');
}

/**
 * @param array|string $atts post_id.
 */
function dsme_readtime_shortcode($atts = array(), $content = null, $tag = 'dsme-readtime'): string
{
    $atts = shortcode_atts(array('post_id' => ''), $atts, $tag);

    $post_id = absint($atts['post_id']);
    if (! $post_id || ! get_post_status($post_id)) {
        $post_id = get_the_ID();
    }

    return $post_id ? dsme_get_readtime_display($post_id) : '';
}

/**
 * The dsme/readtime block. It has no settings; it renders on the server so it
 * always shows the current value, including inside Query Loop blocks.
 * Its editor script is js/readtime-block.js.
 */
add_action('init', 'dsme_register_readtime_block');
function dsme_register_readtime_block(): void
{
    $relative = 'js/readtime-block.js';
    $path     = DSME_PATH . $relative;
    if (! file_exists($path) || ! function_exists('register_block_type')) {
        return;
    }

    wp_register_script(
        'dsme-readtime-block',
        DSME_URL . $relative,
        array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-data', 'wp-core-data', 'wp-i18n'),
        (string) filemtime($path),
        true
    );
    wp_add_inline_script('dsme-readtime-block', 'window.dsmeReadtime = ' . wp_json_encode(array(
        'before'        => (string) dsme_readtime_setting('before'),
        'after'         => (string) dsme_readtime_setting('after'),
        'afterSingular' => dsme_readtime_after_text(1, (string) dsme_readtime_setting('after'), (string) dsme_readtime_setting('after_singular')),
        'allowOverride' => (bool) dsme_readtime_setting('allow_override'),
    )) . ';', 'before');
    wp_set_script_translations('dsme-readtime-block', 'ds-multisite-enhancements');

    register_block_type(DSME_PATH . 'includes/readtime/block', array(
        'render_callback' => 'dsme_render_readtime_block',
    ));
}

/**
 * @param array    $attributes Unused; the block has no settings.
 * @param string   $content    Unused.
 * @param WP_Block $block      Provides the postId context in Query Loops.
 */
function dsme_render_readtime_block($attributes = array(), $content = '', $block = null): string
{
    $post_id = ($block instanceof WP_Block && ! empty($block->context['postId'])) ? (int) $block->context['postId'] : get_the_ID();
    $html    = $post_id ? dsme_get_readtime_display($post_id) : '';

    if ('' === $html) {
        return '';
    }

    return sprintf('<div %s>%s</div>', get_block_wrapper_attributes(), $html);
}
