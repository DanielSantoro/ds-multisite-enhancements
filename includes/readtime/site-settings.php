<?php
/**
 * Site settings page: Settings > Read Time on each site, when the network
 * allows sites to customize (Network Admin > Multisite Enhancements > Read Time).
 *
 * A site may override the text before/after the number (plural and singular)
 * and the minimum time.
 * Only values that differ from the network are stored, so a site follows later
 * network changes for anything it has not customized. "Reset Defaults" deletes
 * the site's values.
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

add_action('admin_menu', 'dsme_readtime_register_site_settings_page');
function dsme_readtime_register_site_settings_page(): void
{
    if (! dsme_readtime_network_settings()['allow_sites']) {
        return;
    }

    $hook = add_options_page(
        __('Read Time', 'ds-multisite-enhancements'),
        __('Read Time', 'ds-multisite-enhancements'),
        'manage_options',
        DSME_READTIME_SLUG,
        'dsme_render_site_settings_page'
    );

    if ($hook) {
        add_action('load-' . $hook, 'dsme_readtime_load_settings_page');
    }
}

function dsme_render_site_settings_page(): void
{
    if (! current_user_can('manage_options') || ! dsme_readtime_network_settings()['allow_sites']) {
        wp_die(esc_html__('You do not have permission to access this page.', 'ds-multisite-enhancements'));
    }

    $network    = dsme_readtime_network_settings();
    $customized = dsme_readtime_site_settings();
    $values     = array();
    foreach (dsme_readtime_site_keys() as $key) {
        $values[$key] = dsme_readtime_setting($key);
    }
    ?>
    <div class="wrap dsme-wrap">
        <h1><?php esc_html_e('Read Time', 'ds-multisite-enhancements'); ?></h1>

        <?php dsme_readtime_render_notices(); ?>

        <p>
            <?php
            echo esc_html(empty($customized)
                ? __('This site uses the network values. Change a field below to customize it for this site only.', 'ds-multisite-enhancements')
                : __('This site has customized values. Fields you have not changed still follow the network.', 'ds-multisite-enhancements'));
            ?>
        </p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('dsme_readtime_site_settings'); ?>
            <input type="hidden" name="action" value="dsme_readtime_site_settings">

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Words per minute', 'ds-multisite-enhancements'); ?></th>
                    <td>
                        <?php echo esc_html((string) dsme_get_words_per_minute()); ?>
                        <p class="description"><?php esc_html_e('Set by the network administrator for every site.', 'ds-multisite-enhancements'); ?></p>
                    </td>
                </tr>
                <?php dsme_readtime_render_display_fields($values, $network); ?>
                <tr>
                    <th scope="row"><?php esc_html_e('Per-post override', 'ds-multisite-enhancements'); ?></th>
                    <td>
                        <?php
                        echo esc_html($network['allow_override']
                            ? __('Allowed: posts can override the calculated time from the editor sidebar.', 'ds-multisite-enhancements')
                            : __('Not allowed.', 'ds-multisite-enhancements'));
                        ?>
                        <p class="description"><?php esc_html_e('Set by the network administrator for every site.', 'ds-multisite-enhancements'); ?></p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" name="dsme_readtime_do" value="save" class="button button-primary"><?php esc_html_e('Save Changes', 'ds-multisite-enhancements'); ?></button>
                <button type="submit" name="dsme_readtime_do" value="reset" class="button" <?php disabled(empty($customized)); ?>><?php esc_html_e('Reset Defaults', 'ds-multisite-enhancements'); ?></button>
            </p>
        </form>

        <?php dsme_readtime_render_docs(); ?>
    </div>
    <?php
}

add_action('admin_post_dsme_readtime_site_settings', 'dsme_readtime_handle_site_settings');
function dsme_readtime_handle_site_settings(): void
{
    check_admin_referer('dsme_readtime_site_settings');

    if (! current_user_can('manage_options') || ! dsme_readtime_network_settings()['allow_sites']) {
        wp_die(esc_html__('You do not have permission to change these settings.', 'ds-multisite-enhancements'));
    }

    $redirect = add_query_arg(array('page' => DSME_READTIME_SLUG), admin_url('options-general.php'));

    if (isset($_POST['dsme_readtime_do']) && 'reset' === $_POST['dsme_readtime_do']) {
        delete_option(DSME_OPTION_READTIME_SITE);
        wp_safe_redirect(add_query_arg('reset', 'true', $redirect));
        exit;
    }

    $result = dsme_readtime_save_site_settings(wp_unslash($_POST));
    wp_safe_redirect(add_query_arg(true === $result ? array('updated' => 'true') : array('dsme_error' => $result), $redirect));
    exit;
}

/**
 * Validate and save the current site's values. Only values that differ from
 * the network are stored; nothing is saved if a field is invalid.
 *
 * @param array $input Unslashed form input.
 * @return true|string True, or "minimum" when the minimum time is invalid.
 */
function dsme_readtime_save_site_settings(array $input)
{
    $minimum = dsme_readtime_sanitize_minimum($input['dsme_readtime_minimum'] ?? '');
    if (null === $minimum) {
        return 'minimum';
    }

    $submitted = array(
        'before'         => dsme_readtime_sanitize_text($input['dsme_readtime_before'] ?? ''),
        'after'          => dsme_readtime_sanitize_text($input['dsme_readtime_after'] ?? ''),
        'after_singular' => dsme_readtime_sanitize_text($input['dsme_readtime_after_singular'] ?? ''),
        'minimum'        => $minimum,
    );

    $network = dsme_readtime_network_settings();
    $custom  = array();
    foreach ($submitted as $key => $value) {
        if ($value !== $network[$key]) {
            $custom[$key] = $value;
        }
    }

    if (empty($custom)) {
        delete_option(DSME_OPTION_READTIME_SITE);
    } else {
        update_option(DSME_OPTION_READTIME_SITE, $custom, false);
    }

    return true;
}
