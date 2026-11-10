<?php
/**
 * Network settings page: Network Admin > Multisite Enhancements > Read Time.
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

/** Network option holding the words-per-minute reading speed. */
define('DSME_OPTION_WPM', 'dsme_words_per_minute');

/** Used until a network admin saves a value. */
define('DSME_DEFAULT_WPM', 250);

/** Accepted range for the words-per-minute field. */
define('DSME_MIN_WPM', 1);
define('DSME_MAX_WPM', 2000);

/**
 * Network-wide reading speed in words per minute.
 */
function dsme_get_words_per_minute(): int
{
    $wpm = (int) get_site_option(DSME_OPTION_WPM, DSME_DEFAULT_WPM);

    return ($wpm >= DSME_MIN_WPM && $wpm <= DSME_MAX_WPM) ? $wpm : DSME_DEFAULT_WPM;
}

/** Read Time settings page slug. */
define('DSME_READTIME_SLUG', 'dsme-read-time');

/** Submenu page under Network Admin > Multisite Enhancements. */
add_action('network_admin_menu', 'dsme_readtime_register_settings_page', 20);
function dsme_readtime_register_settings_page(): void
{
    add_submenu_page(
        DSME_MENU_SLUG,
        __('Read Time', 'ds-multisite-enhancements'),
        __('Read Time', 'ds-multisite-enhancements'),
        'manage_network_options',
        DSME_READTIME_SLUG,
        'dsme_render_settings_page'
    );
}

/** Overview page listing. */
add_filter('dsme_features', 'dsme_readtime_register_feature');
function dsme_readtime_register_feature(array $features): array
{
    $features[DSME_READTIME_SLUG] = array(
        __('Read Time', 'ds-multisite-enhancements'),
        __('Calculates each post\'s read time from a network-wide words-per-minute setting when it is published or updated, and shows it wherever the theme displays read time.', 'ds-multisite-enhancements'),
    );

    return $features;
}

function dsme_render_settings_page(): void
{
    if (! current_user_can('manage_network_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'ds-multisite-enhancements'));
    }

    $wpm = dsme_get_words_per_minute();
    ?>
    <div class="wrap dsme-wrap">
        <h1><?php esc_html_e('Read Time', 'ds-multisite-enhancements'); ?></h1>

        <?php if (isset($_GET['updated'])) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'ds-multisite-enhancements'); ?></p></div>
        <?php elseif (isset($_GET['dsme_error'])) : ?>
            <div class="notice notice-error is-dismissible"><p>
                <?php
                /* translators: 1: minimum, 2: maximum */
                printf(esc_html__('Words per minute must be a whole number between %1$d and %2$d.', 'ds-multisite-enhancements'), DSME_MIN_WPM, DSME_MAX_WPM);
                ?>
            </p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=dsme_save_settings')); ?>">
            <?php wp_nonce_field('dsme_save_settings'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="dsme_words_per_minute"><?php esc_html_e('Words per minute', 'ds-multisite-enhancements'); ?></label></th>
                    <td>
                        <input type="number" name="dsme_words_per_minute" id="dsme_words_per_minute" class="small-text"
                            value="<?php echo esc_attr($wpm); ?>" min="<?php echo esc_attr(DSME_MIN_WPM); ?>" max="<?php echo esc_attr(DSME_MAX_WPM); ?>" step="1" required>
                        <p class="description">
                            <?php esc_html_e('Applies to every site in the network. When a post is published or updated, its words are counted, divided by this number and rounded up to the nearest whole minute. The result is stored on the post as "dsme-readtime".', 'ds-multisite-enhancements'); ?>
                        </p>
                        <p class="description">
                            <?php esc_html_e('After you change this value, each post is recalculated automatically the next time its read time is displayed.', 'ds-multisite-enhancements'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

add_action('network_admin_edit_dsme_save_settings', 'dsme_save_settings');
function dsme_save_settings(): void
{
    check_admin_referer('dsme_save_settings');

    if (! current_user_can('manage_network_options')) {
        wp_die(esc_html__('You do not have permission to change these settings.', 'ds-multisite-enhancements'));
    }

    $redirect = add_query_arg(array('page' => DSME_READTIME_SLUG), network_admin_url('admin.php'));
    $raw      = isset($_POST['dsme_words_per_minute']) ? trim(wp_unslash($_POST['dsme_words_per_minute'])) : '';

    if (! ctype_digit($raw) || (int) $raw < DSME_MIN_WPM || (int) $raw > DSME_MAX_WPM) {
        wp_safe_redirect(add_query_arg('dsme_error', '1', $redirect));
        exit;
    }

    update_site_option(DSME_OPTION_WPM, (int) $raw);

    wp_safe_redirect(add_query_arg('updated', 'true', $redirect));
    exit;
}
