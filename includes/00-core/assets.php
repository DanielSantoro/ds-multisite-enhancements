<?php
/**
 * Plugin stylesheet. Source is scss/ds-multisite-enhancements.scss, compiled to
 * css/ds-multisite-enhancements.css with `npm run build` (see README).
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

/** Admin stylesheet handle, for features that need to depend on it. */
define('DSME_ADMIN_STYLE', 'dsme-admin');

add_action('admin_enqueue_scripts', 'dsme_enqueue_admin_styles');
function dsme_enqueue_admin_styles(): void
{
    $relative = 'css/ds-multisite-enhancements.css';
    $path     = DSME_PATH . $relative;

    if (! file_exists($path)) {
        return;
    }

    // filemtime busts caches whenever the compiled CSS changes, without a version bump.
    wp_enqueue_style(DSME_ADMIN_STYLE, DSME_URL . $relative, array(), (string) filemtime($path));
}
