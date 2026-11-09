<?php
/**
 * Read time: calculate on publish/update, store as post meta, display via shortcode.
 *
 * Multisite: post meta lives in each site's own tables, so values are always
 * written and read in the post's own blog context. Network listings in the
 * theme switch_to_blog() to the post's site before calling the shortcode.
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

/** Read time in whole minutes. */
define('DSME_META_READTIME', 'dsme-readtime');

/** Word count the read time was calculated from (lets a WPM change be applied without re-parsing content). */
define('DSME_META_WORDCOUNT', 'dsme-wordcount');

/** Words-per-minute value the stored read time was calculated with. */
define('DSME_META_WPM', 'dsme-readtime-wpm');

/**
 * Count the readable words in post content.
 *
 * Block comments, HTML and shortcodes are removed first. A "word" is a run of
 * letters or digits in any script, optionally joined by an apostrophe or
 * hyphen (so "don't" and "split-complementary" count once). Languages written
 * without spaces (Chinese, Japanese, Thai) are undercounted.
 */
function dsme_count_words(string $content): int
{
    $text = wp_strip_all_tags(strip_shortcodes($content));
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    if ('' === trim($text)) {
        return 0;
    }

    return (int) preg_match_all('/[\p{L}\p{N}]+(?:[\'’\-][\p{L}\p{N}]+)*/u', $text);
}

/**
 * Words divided by words-per-minute, rounded up to the nearest whole minute,
 * with a minimum of 1 (posts with no countable words, such as ones built only
 * from dynamic blocks, still show "1 MIN READ" rather than 0).
 */
function dsme_calculate_readtime(int $words, int $wpm): int
{
    if ($words <= 0 || $wpm <= 0) {
        return 1;
    }

    return max(1, (int) ceil($words / $wpm));
}

/**
 * Post types that get a read time: viewable types with an editor.
 */
function dsme_tracks_post_type(string $post_type): bool
{
    $tracked = post_type_supports($post_type, 'editor') && is_post_type_viewable($post_type);

    return (bool) apply_filters('dsme_readtime_tracks_post_type', $tracked, $post_type);
}

/**
 * Calculate and store the read time for a post. Returns the minutes.
 */
function dsme_update_post_readtime(int $post_id): int
{
    $post = get_post($post_id);
    if (! $post) {
        return 0;
    }

    $wpm      = dsme_get_words_per_minute();
    $words    = dsme_count_words((string) $post->post_content);
    $readtime = dsme_calculate_readtime($words, $wpm);

    update_post_meta($post_id, DSME_META_WORDCOUNT, $words);
    update_post_meta($post_id, DSME_META_WPM, $wpm);
    update_post_meta($post_id, DSME_META_READTIME, $readtime);

    return $readtime;
}

/**
 * On publish, and on every save of an already published post.
 * wp_after_insert_post runs once per save, after meta and terms are written,
 * including when a scheduled post is published by cron.
 */
add_action('wp_after_insert_post', 'dsme_on_save_post', 10, 3);
function dsme_on_save_post($post_id, $post, $update): void
{
    if (! $post instanceof WP_Post || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if ('publish' !== $post->post_status || ! dsme_tracks_post_type($post->post_type)) {
        return;
    }

    dsme_update_post_readtime((int) $post_id);
}

/**
 * The read time to display, in minutes.
 *
 * Uses the stored "dsme-readtime" value. If it is missing (a post published
 * before this plugin was active) or was calculated with a different
 * words-per-minute setting, it is recalculated and, for published posts, saved.
 *
 * @param int|WP_Post|null $post Post ID or object; defaults to the current post.
 */
function dsme_get_readtime($post = null): int
{
    $post = get_post($post);
    if (! $post) {
        return 0;
    }

    $wpm    = dsme_get_words_per_minute();
    $stored = get_post_meta($post->ID, DSME_META_READTIME, true);

    // A stored 0 predates the 1-minute minimum, so it is recalculated below.
    if ('' !== $stored && (int) $stored >= 1 && (int) get_post_meta($post->ID, DSME_META_WPM, true) === $wpm) {
        return (int) $stored;
    }

    if ('publish' === $post->post_status) {
        $words = get_post_meta($post->ID, DSME_META_WORDCOUNT, true);

        if ('' === $words) {
            return dsme_update_post_readtime((int) $post->ID);
        }

        // Only the WPM changed: recalculate from the stored word count.
        $readtime = dsme_calculate_readtime((int) $words, $wpm);
        update_post_meta($post->ID, DSME_META_WPM, $wpm);
        update_post_meta($post->ID, DSME_META_READTIME, $readtime);

        return $readtime;
    }

    // Drafts and previews: calculate without saving.
    return dsme_calculate_readtime(dsme_count_words((string) $post->post_content), $wpm);
}

/**
 * Shortcodes.
 *
 * [dsme_readtime] is this plugin's own tag. The existing [rt_reading_time]
 * tag (Reading Time WP), used throughout the theme, is taken over so every
 * call site shows the stored value with no template changes. Attributes and
 * HTML match Reading Time WP so existing styling still applies.
 * Disable the takeover with: add_filter('dsme_replace_rt_reading_time', '__return_false');
 */
add_action('init', 'dsme_register_shortcodes', 20);
function dsme_register_shortcodes(): void
{
    add_shortcode('dsme_readtime', 'dsme_readtime_shortcode');

    if (apply_filters('dsme_replace_rt_reading_time', true)) {
        remove_shortcode('rt_reading_time');
        add_shortcode('rt_reading_time', 'dsme_readtime_shortcode');
    }
}

/**
 * @param array|string $atts label, postfix, postfix_singular, post_id.
 */
function dsme_readtime_shortcode($atts = array(), $content = null, $tag = 'dsme_readtime'): string
{
    $atts = shortcode_atts(
        array(
            'label'            => '',
            'postfix'          => '',
            'postfix_singular' => '',
            'post_id'          => '',
        ),
        $atts,
        $tag
    );

    $post_id = absint($atts['post_id']);
    if (! $post_id || ! get_post_status($post_id)) {
        $post_id = get_the_ID();
    }

    return dsme_readtime_html($post_id, $atts);
}

/**
 * Read-time markup for templates (tiles, bylines).
 *
 * Call it in the post's own blog context: on network listings, after
 * switch_to_blog() to the post's site. The HTML matches Reading Time WP's
 * output so existing CSS applies.
 *
 * @param int|WP_Post|null $post Post ID or object; defaults to the current post.
 * @param array            $args label, postfix (plural), postfix_singular.
 */
function dsme_readtime_html($post = null, array $args = array()): string
{
    $args = wp_parse_args($args, array(
        'label'            => '',
        'postfix'          => 'MIN READ',
        'postfix_singular' => 'MIN READ',
    ));

    $post    = get_post($post);
    $post_id = $post ? (int) $post->ID : 0;
    $minutes = dsme_get_readtime($post);
    $postfix = ($minutes > 1) ? $args['postfix'] : $args['postfix_singular'];

    $output = '<span class="span-reading-time rt-reading-time"><span class="rt-label rt-prefix">' . wp_kses_post($args['label'])
        . '</span> <span class="rt-time"> ' . esc_html((string) $minutes) . '</span> <span class="rt-label rt-postfix">'
        . wp_kses_post($postfix) . '</span></span>';

    return (string) apply_filters('dsme_readtime_output', $output, $minutes, $args, $post_id);
}
