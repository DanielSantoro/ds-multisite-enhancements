<?php
/**
 * Network Admin menu shell: Multisite Enhancements (top level, below Settings) > Overview.
 *
 * Features add their own settings page as a submenu of DSME_MENU_SLUG and list
 * themselves on the Overview page through the 'dsme_features' filter:
 *
 *     add_action('network_admin_menu', function () {
 *         add_submenu_page(DSME_MENU_SLUG, 'My Feature', 'My Feature', 'manage_network_options', 'dsme-my-feature', 'render_cb');
 *     }, 20);
 *
 *     add_filter('dsme_features', function (array $features) {
 *         $features['dsme-my-feature'] = array('My Feature', 'What it does.');
 *         return $features;
 *     });
 *
 * Use a network_admin_menu priority above 10 so the parent menu exists first.
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

/** Top-level menu slug (the Overview page). */
define('DSME_MENU_SLUG', 'ds-multisite-enhancements');

/**
 * WordPress hides a submenu that has a single item pointing at the parent
 * page, so the parent has its own Overview page and each feature gets its own
 * submenu page and slug.
 */
add_action('network_admin_menu', 'dsme_register_admin_menu', 10);
function dsme_register_admin_menu(): void
{
    add_menu_page(
        __('Multisite Enhancements', 'ds-multisite-enhancements'),
        __('Multisite Enhancements', 'ds-multisite-enhancements'),
        'manage_network_options',
        DSME_MENU_SLUG,
        'dsme_render_overview_page',
        'dashicons-networking',
        26 // Network Admin Settings is 25; core uses nothing else before 99.
    );

    add_submenu_page(
        DSME_MENU_SLUG,
        __('Multisite Enhancements', 'ds-multisite-enhancements'),
        __('Overview', 'ds-multisite-enhancements'),
        'manage_network_options',
        DSME_MENU_SLUG,
        'dsme_render_overview_page'
    );
}

/**
 * Features listed on the Overview page: page slug => [title, description].
 */
function dsme_get_features(): array
{
    return (array) apply_filters('dsme_features', array());
}

function dsme_render_overview_page(): void
{
    if (! current_user_can('manage_network_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'ds-multisite-enhancements'));
    }
    ?>
    <div class="wrap dsme-wrap">
        <h1><?php esc_html_e('Multisite Enhancements', 'ds-multisite-enhancements'); ?></h1>
        <p><?php esc_html_e('Network-wide features for every site in this network. Choose a feature to configure it.', 'ds-multisite-enhancements'); ?></p>

        <table class="widefat striped dsme-overview-table">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Feature', 'ds-multisite-enhancements'); ?></th>
                    <th scope="col"><?php esc_html_e('Description', 'ds-multisite-enhancements'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (dsme_get_features() as $slug => $feature) : ?>
                    <tr>
                        <td><strong><a href="<?php echo esc_url(add_query_arg('page', $slug, network_admin_url('admin.php'))); ?>"><?php echo esc_html($feature[0]); ?></a></strong></td>
                        <td><?php echo esc_html($feature[1]); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
