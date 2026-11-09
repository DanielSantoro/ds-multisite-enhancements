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

/** Top-level menu slug (the Overview page). */
define('DSME_MENU_SLUG', 'ds-multisite-enhancements');

/** Read Time settings page slug. */
define('DSME_READTIME_SLUG', 'dsme-read-time');

/**
 * Network Admin menu: Multisite Enhancements (top level, below Settings)
 *   > Overview
 *   > Read Time
 *
 * WordPress hides a submenu that has a single item pointing at the parent
 * page, so the parent has its own Overview page and each feature gets its own
 * submenu page and slug. Add future features to dsme_get_features() and
 * register their page here.
 */
add_action('network_admin_menu', 'dsme_register_settings_page');
function dsme_register_settings_page(): void
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

    add_submenu_page(
        DSME_MENU_SLUG,
        __('Read Time', 'ds-multisite-enhancements'),
        __('Read Time', 'ds-multisite-enhancements'),
        'manage_network_options',
        DSME_READTIME_SLUG,
        'dsme_render_settings_page'
    );
}

/**
 * Features listed on the Overview page: slug => [title, description].
 */
function dsme_get_features(): array
{
    return array(
        DSME_READTIME_SLUG => array(
            __('Read Time', 'ds-multisite-enhancements'),
            __('Calculates each post\'s read time from a network-wide words-per-minute setting when it is published or updated, and shows it wherever the theme displays read time.', 'ds-multisite-enhancements'),
        ),
    );
}

function dsme_render_overview_page(): void
{
    if (! current_user_can('manage_network_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'ds-multisite-enhancements'));
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Multisite Enhancements', 'ds-multisite-enhancements'); ?></h1>
        <p><?php esc_html_e('Network-wide features for every site in this network. Choose a feature to configure it.', 'ds-multisite-enhancements'); ?></p>

        <table class="widefat striped" style="max-width:900px">
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

function dsme_render_settings_page(): void
{
    if (! current_user_can('manage_network_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'ds-multisite-enhancements'));
    }


    $wpm = dsme_get_words_per_minute();
    ?>
    <div class="wrap">
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
