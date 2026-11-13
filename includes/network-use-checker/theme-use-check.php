<?php
/**
 * Network Admin > Themes > Theme Use Check.
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

/** Theme Use Check page slug. */
define('DSME_THEME_USE_SLUG', 'dsme-theme-use-check');

add_action('network_admin_menu', 'dsme_register_theme_use_check_page');
function dsme_register_theme_use_check_page(): void
{
    add_submenu_page(
        'themes.php',
        __('Theme Use Check', 'ds-multisite-enhancements'),
        __('Theme Use Check', 'ds-multisite-enhancements'),
        'manage_network_themes',
        DSME_THEME_USE_SLUG,
        'dsme_render_theme_use_check_page'
    );
}

function dsme_render_theme_use_check_page(): void
{
    if (! current_user_can('manage_network_themes')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'ds-multisite-enhancements'));
    }

    $site_used = dsme_use_check_theme_sites();
    ?>
    <div class="wrap dsme-wrap">
        <h1><?php esc_html_e('Theme Use Check', 'ds-multisite-enhancements'); ?></h1>
        <table class="widefat fixed dsme-use-table">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Theme', 'ds-multisite-enhancements'); ?></th>
                    <th scope="col" class="dsme-use-status"><?php esc_html_e('In Use?', 'ds-multisite-enhancements'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (wp_get_themes() as $slug => $theme) : ?>
                    <?php
                    $in_site = isset($site_used[$slug]);
                    $parent  = $theme->parent();
                    ?>
                    <tr>
                        <td>
                            <?php echo esc_html($theme->get('Name')); ?>
                            <?php if ($parent) : ?>
                                <br><span class="dsme-use-detail">
                                    <?php
                                    /* translators: %s: parent theme name. */
                                    echo esc_html(sprintf(__('Child theme of %s', 'ds-multisite-enhancements'), $parent->get('Name')));
                                    ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($in_site) : ?>
                                <br><span class="dsme-use-detail"><strong><?php esc_html_e('Active on:', 'ds-multisite-enhancements'); ?></strong> <em><?php echo esc_html(implode(', ', $site_used[$slug])); ?></em></span>
                            <?php endif; ?>
                        </td>
                        <td class="dsme-use-status"><?php echo dsme_use_check_symbol($in_site); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in helper. ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
