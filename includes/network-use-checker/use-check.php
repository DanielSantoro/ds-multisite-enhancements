<?php
/**
 * Network use checker: shared helpers that find which sites use each plugin and theme.
 *
 * Pages: Network Admin > Plugins > Plugin Use Check and Themes > Theme Use Check.
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * IDs of every site in the network.
 *
 * get_sites() returns only 100 sites by default, which would silently
 * truncate the report on a large network, so the limit is lifted.
 *
 * @return int[]
 */
function dsme_use_check_site_ids(): array
{
    return array_map('intval', get_sites(array('fields' => 'ids', 'number' => 0)));
}

/**
 * Sites that activated each plugin on their own, excluding network-activated plugins.
 *
 * @param array $network_used Network-activated plugin files as keys.
 * @return array<string, string[]> Plugin file => site names.
 */
function dsme_use_check_plugin_sites(array $network_used): array
{
    $sites = array();

    foreach (dsme_use_check_site_ids() as $blog_id) {
        switch_to_blog($blog_id);
        $active    = (array) get_option('active_plugins', array());
        $blog_name = get_bloginfo('name');
        restore_current_blog();

        foreach ($active as $file) {
            if (empty($network_used[$file])) {
                $sites[$file][] = $blog_name;
            }
        }
    }

    return $sites;
}

/**
 * Sites using each theme as their active theme.
 *
 * @return array<string, string[]> Theme slug => site names.
 */
function dsme_use_check_theme_sites(): array
{
    $sites = array();

    foreach (dsme_use_check_site_ids() as $blog_id) {
        switch_to_blog($blog_id);
        $theme_slug = get_option('stylesheet');
        $blog_name  = get_bloginfo('name');
        restore_current_blog();

        if ($theme_slug) {
            $sites[$theme_slug][] = $blog_name;
        }
    }

    return $sites;
}

/**
 * Yes/no marker for the "In Use?" column, with a text alternative for screen readers.
 */
function dsme_use_check_symbol(bool $in_use): string
{
    return sprintf(
        '<span aria-hidden="true">%s</span><span class="screen-reader-text">%s</span>',
        $in_use ? '✅' : '❌',
        esc_html($in_use ? __('Yes', 'ds-multisite-enhancements') : __('No', 'ds-multisite-enhancements'))
    );
}
