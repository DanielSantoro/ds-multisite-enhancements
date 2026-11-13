<?php
/**
 * Network Admin menu and network-wide feature switches.
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
 * Features listed on the Overview page, keyed by their include file or folder name.
 */
function dsme_get_features(): array
{
    return (array) apply_filters('dsme_features', array(
        'readtime' => array(
            'title'       => __('Post Read Time Calculator', 'ds-multisite-enhancements'),
            'description' => __('Calculates an estimated reading time for posts, pages and custom post types using a network-wide reading speed. Display it with a block, a shortcode or a theme function. Site administrators can customize the text and minimum time when allowed, and posts can override their time when allowed.', 'ds-multisite-enhancements'),
            'settings'    => 'dsme-read-time',
            'menu_title'  => __('Read Time', 'ds-multisite-enhancements'),
        ),
        'network-use-checker' => array(
            'title'       => __('Network Use Checker', 'ds-multisite-enhancements'),
            'description' => __('Shows which sites are using which plugins and themes across the network to reduce unused plugins and themes, increasing security and performance. Adds Plugin Use Check under Plugins and Theme Use Check under Themes. This is only available on the network admin.', 'ds-multisite-enhancements'),
        ),
    ));
}

function dsme_feature_enabled(string $feature): bool
{
    $enabled = get_site_option('dsme_enabled_features', array());

    return is_array($enabled) && ! empty($enabled[$feature]);
}

function dsme_save_feature_toggle(): bool
{
    if (! current_user_can('manage_network_options')) {
        return false;
    }

    $feature = isset($_POST['feature']) && is_string($_POST['feature']) ? sanitize_key(wp_unslash($_POST['feature'])) : '';
    $features = dsme_get_features();
    if (! isset($features[$feature])) {
        return false;
    }

    $enabled = get_site_option('dsme_enabled_features', array());
    $enabled = is_array($enabled) ? $enabled : array();
    if (isset($_POST['enabled']) && '1' === $_POST['enabled']) {
        $enabled[$feature] = true;
    } else {
        unset($enabled[$feature]);
    }

    update_site_option('dsme_enabled_features', $enabled);

    return get_site_option('dsme_enabled_features', array()) === $enabled;
}

add_action('wp_ajax_dsme_toggle_feature', 'dsme_ajax_toggle_feature');
function dsme_ajax_toggle_feature(): void
{
    check_ajax_referer('dsme_toggle_feature');

    if (! dsme_save_feature_toggle()) {
        wp_send_json_error(array('message' => __('Could not save the feature setting.', 'ds-multisite-enhancements')), 400);
    }

    wp_send_json_success();
}

add_action('admin_post_dsme_toggle_feature', 'dsme_post_toggle_feature');
function dsme_post_toggle_feature(): void
{
    check_admin_referer('dsme_toggle_feature');
    $saved = dsme_save_feature_toggle();
    $redirect = add_query_arg(array(
        'page' => DSME_MENU_SLUG,
        $saved ? 'updated' : 'dsme_error' => '1',
    ), network_admin_url('admin.php'));
    wp_safe_redirect($redirect);
    exit;
}

function dsme_render_overview_page(): void
{
    if (! current_user_can('manage_network_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'ds-multisite-enhancements'));
    }
    ?>
    <div class="wrap dsme-wrap">
        <h1><?php esc_html_e('Multisite Enhancements', 'ds-multisite-enhancements'); ?></h1>
        <?php if (isset($_GET['updated'])) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Feature setting saved.', 'ds-multisite-enhancements'); ?></p></div>
        <?php elseif (isset($_GET['dsme_error'])) : ?>
            <div class="notice notice-error is-dismissible"><p><?php esc_html_e('Could not save the feature setting.', 'ds-multisite-enhancements'); ?></p></div>
        <?php endif; ?>

        <?php foreach (dsme_get_features() as $id => $feature) : ?>
            <?php $enabled = dsme_feature_enabled($id); ?>
            <section class="dsme-feature">
                <h2><?php echo esc_html($feature['title']); ?></h2>
                <p><?php echo esc_html($feature['description']); ?></p>
                <form class="dsme-feature-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
                    <?php wp_nonce_field('dsme_toggle_feature'); ?>
                    <input type="hidden" name="action" value="dsme_toggle_feature">
                    <input type="hidden" name="feature" value="<?php echo esc_attr($id); ?>">
                    <?php if (! empty($feature['settings'])) : ?>
                        <a class="dsme-feature-settings" href="<?php echo esc_url(add_query_arg('page', $feature['settings'], network_admin_url('admin.php'))); ?>" data-menu-title="<?php echo esc_attr($feature['menu_title'] ?? $feature['title']); ?>" <?php echo $enabled ? '' : 'hidden'; ?>><?php esc_html_e('Settings', 'ds-multisite-enhancements'); ?></a>
                    <?php endif; ?>
                    <span class="dsme-feature-status" role="status" aria-live="polite" data-enabled="<?php esc_attr_e('Enabled.', 'ds-multisite-enhancements'); ?>" data-disabled="<?php esc_attr_e('Disabled.', 'ds-multisite-enhancements'); ?>" data-error="<?php esc_attr_e('Could not save the feature setting.', 'ds-multisite-enhancements'); ?>"></span>
                    <label class="dsme-switch">
                        <input type="checkbox" name="enabled" value="1" <?php checked($enabled); ?> aria-label="<?php echo esc_attr(sprintf(__('Enable %s', 'ds-multisite-enhancements'), $feature['title'])); ?>">
                        <span class="dsme-switch-track" aria-hidden="true"></span>
                    </label>
                    <button class="button dsme-feature-submit" type="submit"><?php esc_html_e('Save', 'ds-multisite-enhancements'); ?></button>
                </form>
            </section>
        <?php endforeach; ?>
    </div>
    <?php
}
