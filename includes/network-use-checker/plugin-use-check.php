<?php
/**
 * Network Admin > Plugins > Plugin Use Check.
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

/** Plugin Use Check page slug. */
define('DSME_PLUGIN_USE_SLUG', 'dsme-plugin-use-check');

add_action('network_admin_menu', 'dsme_register_plugin_use_check_page');
function dsme_register_plugin_use_check_page(): void
{
    add_submenu_page(
        'plugins.php',
        __('Plugin Use Check', 'ds-multisite-enhancements'),
        __('Plugin Use Check', 'ds-multisite-enhancements'),
        'manage_network_plugins',
        DSME_PLUGIN_USE_SLUG,
        'dsme_render_plugin_use_check_page'
    );
}

function dsme_render_plugin_use_check_page(): void
{
    if (! current_user_can('manage_network_plugins')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'ds-multisite-enhancements'));
    }

    if (! function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $network_used = array_fill_keys(array_keys((array) get_site_option('active_sitewide_plugins', array())), true);
    $site_used    = dsme_use_check_plugin_sites($network_used);
    ?>
    <div class="wrap dsme-wrap">
        <h1><?php esc_html_e('Plugin Use Check', 'ds-multisite-enhancements'); ?></h1>
        <table class="widefat fixed dsme-use-table">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Plugin', 'ds-multisite-enhancements'); ?></th>
                    <th scope="col" class="dsme-use-status"><?php esc_html_e('In Use?', 'ds-multisite-enhancements'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (get_plugins() as $file => $data) : ?>
                    <?php
                    $in_network = isset($network_used[$file]);
                    $in_site    = isset($site_used[$file]);
                    ?>
                    <tr>
                        <td>
                            <?php echo esc_html($data['Name']); ?>
                            <?php if ($in_network) : ?>
                                <br><span class="dsme-use-detail"><strong><?php esc_html_e('Network Activated', 'ds-multisite-enhancements'); ?></strong></span>
                            <?php elseif ($in_site) : ?>
                                <br><span class="dsme-use-detail"><strong><?php esc_html_e('Site Activated:', 'ds-multisite-enhancements'); ?></strong> <em><?php echo esc_html(implode(', ', $site_used[$file])); ?></em></span>
                            <?php endif; ?>
                        </td>
                        <td class="dsme-use-status"><?php echo dsme_use_check_symbol($in_network || $in_site); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in helper. ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
