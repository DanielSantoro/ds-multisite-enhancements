<?php
/**
 * Plugin Name: DS Multisite Enhancements
 * Description: Network-level enhancements for WordPress Multisite. Calculates each post's read time from a network-wide words-per-minute setting, stores it on the post, and serves it through the existing [rt_reading_time] shortcode.
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
define('DSME_PATH', plugin_dir_path(__FILE__));

require_once DSME_PATH . 'includes/settings.php';
require_once DSME_PATH . 'includes/readtime.php';
