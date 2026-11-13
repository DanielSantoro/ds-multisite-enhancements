<?php
/**
 * Read Time settings: the network values, per-site overrides, and the
 * network settings page (Network Admin > Multisite Enhancements > Read Time).
 *
 * Network-only: words per minute, per-post overrides, and whether sites may
 * customize. Sites may override the text before/after the number and the
 * minimum time when the network allows it (see site-settings.php).
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

/** Network option holding the display settings (before, after, after_singular, minimum, allow_override, allow_sites). */
define('DSME_OPTION_READTIME', 'dsme_readtime_settings');

/** Per-site option holding only the values a site has customized (before, after, after_singular, minimum). */
define('DSME_OPTION_READTIME_SITE', 'dsme_readtime_site_settings');

/** Highest accepted minimum time, in minutes. */
define('DSME_READTIME_MAX_MINIMUM', 999);

/** Longest accepted text before or after the number, in characters. */
define('DSME_READTIME_MAX_TEXT', 100);

/** Read Time settings page slug, used by both the network and site pages. */
define('DSME_READTIME_SLUG', 'dsme-read-time');

/**
 * Network-wide reading speed in words per minute.
 */
function dsme_get_words_per_minute(): int
{
    $wpm = (int) get_site_option(DSME_OPTION_WPM, DSME_DEFAULT_WPM);

    return ($wpm >= DSME_MIN_WPM && $wpm <= DSME_MAX_WPM) ? $wpm : DSME_DEFAULT_WPM;
}

/**
 * Settings a site may override when the network allows it.
 *
 * @return string[]
 */
function dsme_readtime_site_keys(): array
{
    return array('before', 'after', 'after_singular', 'minimum');
}

/**
 * Network display settings, with defaults for anything not saved yet.
 *
 * @return array{before: string, after: string, after_singular: string, minimum: int, allow_override: bool, allow_sites: bool}
 */
function dsme_readtime_network_settings(): array
{
    $saved = get_site_option(DSME_OPTION_READTIME, array());
    $saved = is_array($saved) ? $saved : array();

    return array(
        'before'         => isset($saved['before']) ? dsme_readtime_sanitize_text($saved['before']) : 'Read Time: ',
        'after'          => isset($saved['after']) ? dsme_readtime_sanitize_text($saved['after']) : ' minutes',
        // Blank: worked out from "after" (see dsme_readtime_singularize()).
        'after_singular' => isset($saved['after_singular']) ? dsme_readtime_sanitize_text($saved['after_singular']) : '',
        'minimum'        => isset($saved['minimum']) ? (int) dsme_readtime_sanitize_minimum($saved['minimum']) : 0,
        'allow_override' => ! empty($saved['allow_override']),
        'allow_sites'    => ! empty($saved['allow_sites']),
    );
}

/**
 * The current site's customized values. Empty when the network does not allow
 * site settings, so saved values are kept but ignored until it does.
 */
function dsme_readtime_site_settings(): array
{
    if (! dsme_readtime_network_settings()['allow_sites']) {
        return array();
    }

    $saved = get_option(DSME_OPTION_READTIME_SITE, array());

    return is_array($saved) ? array_intersect_key($saved, array_flip(dsme_readtime_site_keys())) : array();
}

/**
 * The effective value of a display setting on the current site: the site's own
 * value when it has one (and sites may customize), otherwise the network value.
 *
 * @return string|int|bool
 */
function dsme_readtime_setting(string $key)
{
    $network = dsme_readtime_network_settings();
    $site    = dsme_readtime_site_settings();

    if (array_key_exists($key, $site)) {
        return 'minimum' === $key ? (int) dsme_readtime_sanitize_minimum($site[$key]) : dsme_readtime_sanitize_text($site[$key]);
    }

    return $network[$key] ?? null;
}

/**
 * The singular form of the text after the number, for a read time of 1:
 * the first "minutes" or "mins" (any case) loses its "s", so " minutes"
 * becomes " minute" and " mins" becomes " min". Other text is unchanged.
 */
function dsme_readtime_singularize(string $after): string
{
    return (string) preg_replace('/\b(min(?:ute)?)s\b/iu', '$1', $after, 1);
}

/**
 * The text after the number for a given value: for 1, the "after_singular"
 * setting (or the automatic singular when that is blank); otherwise the plural.
 *
 * @param int|string $value The number, or a placeholder such as "𝑥" (plural).
 */
function dsme_readtime_after_text($value, string $after, string $after_singular): string
{
    if ('1' !== (string) $value) {
        return $after;
    }

    return '' !== $after_singular ? $after_singular : dsme_readtime_singularize($after);
}

/**
 * Plain text for before/after the number. Unlike sanitize_text_field(), leading
 * and trailing spaces are kept, since they separate the text from the number.
 *
 * @param mixed $value
 */
function dsme_readtime_sanitize_text($value): string
{
    if (! is_scalar($value)) {
        return '';
    }

    $text = wp_check_invalid_utf8((string) $value);
    $text = strip_tags($text);
    $text = preg_replace('/[\r\n\t]+/', ' ', $text);

    return mb_substr($text, 0, DSME_READTIME_MAX_TEXT);
}

/**
 * Minimum time in whole minutes. Blank is 0 (no minimum). Returns null when the
 * value is not a whole number in range.
 *
 * @param mixed $value
 */
function dsme_readtime_sanitize_minimum($value): ?int
{
    if (is_int($value)) {
        $value = (string) $value;
    }
    if (! is_string($value)) {
        return null;
    }

    $value = trim($value);
    if ('' === $value) {
        return 0;
    }

    if (! ctype_digit($value) || (int) $value > DSME_READTIME_MAX_MINIMUM) {
        return null;
    }

    return (int) $value;
}

/** Submenu page under Network Admin > Multisite Enhancements. */
add_action('network_admin_menu', 'dsme_readtime_register_settings_page', 20);
function dsme_readtime_register_settings_page(): void
{
    $hook = add_submenu_page(
        DSME_MENU_SLUG,
        __('Read Time', 'ds-multisite-enhancements'),
        __('Read Time', 'ds-multisite-enhancements'),
        'manage_network_options',
        DSME_READTIME_SLUG,
        'dsme_render_settings_page'
    );

    if ($hook) {
        add_action('load-' . $hook, 'dsme_readtime_load_settings_page');
    }
}

/**
 * Settings-page script (live preview, copy buttons). Hooked from load-{page}
 * on both the network and site pages so it is only loaded there.
 */
function dsme_readtime_load_settings_page(): void
{
    add_action('admin_enqueue_scripts', function (): void {
        $relative = 'js/readtime-settings.js';
        $path     = DSME_PATH . $relative;
        if (file_exists($path)) {
            wp_enqueue_script('dsme-readtime-settings', DSME_URL . $relative, array(), (string) filemtime($path), true);
        }
    });
}

function dsme_render_settings_page(): void
{
    if (! current_user_can('manage_network_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'ds-multisite-enhancements'));
    }

    $wpm      = dsme_get_words_per_minute();
    $settings = dsme_readtime_network_settings();
    ?>
    <div class="wrap dsme-wrap">
        <h1><?php esc_html_e('Read Time', 'ds-multisite-enhancements'); ?></h1>

        <?php dsme_readtime_render_notices(); ?>

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
                <?php dsme_readtime_render_display_fields($settings); ?>
                <tr>
                    <th scope="row"><?php esc_html_e('Per-post override', 'ds-multisite-enhancements'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="dsme_readtime_allow_override" value="1" <?php checked($settings['allow_override']); ?>>
                            <?php esc_html_e('Allow individual posts, pages, etc. to override the calculated time?', 'ds-multisite-enhancements'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Adds an "Override calculated read time to show" field to the post editor sidebar (block and classic editors). When a post has a value there, that number is shown instead of the calculated time, and the minimum time does not apply. Turning this off keeps saved overrides but ignores them.', 'ds-multisite-enhancements'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Site settings', 'ds-multisite-enhancements'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="dsme_readtime_allow_sites" value="1" <?php checked($settings['allow_sites']); ?>>
                            <?php esc_html_e('Allow sites to customize the text before and after the number and the minimum time', 'ds-multisite-enhancements'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Adds Settings → Read Time to each site. Sites keep the network values above until they change them, and can reset to the network values at any time. When this is off, every site uses the network values; values a site saved are kept and apply again if you turn this back on.', 'ds-multisite-enhancements'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

        <?php dsme_readtime_render_docs(); ?>
    </div>
    <?php
}

/**
 * Success/error notices shared by the network and site pages.
 */
function dsme_readtime_render_notices(): void
{
    $error = isset($_GET['dsme_error']) ? sanitize_key(wp_unslash($_GET['dsme_error'])) : '';

    if (isset($_GET['reset'])) {
        $message = __('Settings reset to the network values.', 'ds-multisite-enhancements');
    } elseif (isset($_GET['updated'])) {
        $message = __('Settings saved.', 'ds-multisite-enhancements');
    }

    if (! empty($message)) {
        printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html($message));
    }

    if ('wpm' === $error) {
        /* translators: 1: minimum, 2: maximum */
        $message = sprintf(__('Words per minute must be a whole number between %1$d and %2$d.', 'ds-multisite-enhancements'), DSME_MIN_WPM, DSME_MAX_WPM);
    } elseif ('minimum' === $error) {
        /* translators: %d: maximum */
        $message = sprintf(__('Minimum time must be blank or a whole number up to %d.', 'ds-multisite-enhancements'), DSME_READTIME_MAX_MINIMUM);
    } else {
        return;
    }

    printf('<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html($message) . ' ' . esc_html__('No changes were saved.', 'ds-multisite-enhancements'));
}

/**
 * Before/after text, live preview and minimum time rows, shared by the network
 * and site pages.
 *
 * @param array      $values  Values to show in the fields.
 * @param array|null $network Network values to show as "Network value" hints (site page only).
 */
function dsme_readtime_render_display_fields(array $values, ?array $network = null): void
{
    $hint = function (string $key) use ($network): void {
        if (null === $network) {
            return;
        }
        if ('minimum' === $key) {
            $value = $network['minimum'] ? (string) $network['minimum'] : __('none', 'ds-multisite-enhancements');
        } elseif ('after_singular' === $key && '' === $network[$key]) {
            /* translators: %s: the automatic singular text, e.g. " minute". */
            $value = sprintf(__('automatic (%s)', 'ds-multisite-enhancements'), dsme_readtime_singularize($network['after']));
        } else {
            $value = $network[$key];
        }
        printf(
            '<p class="description">%s <code class="dsme-readtime-literal">%s</code></p>',
            esc_html__('Network value:', 'ds-multisite-enhancements'),
            esc_html('' === $value ? __('(empty)', 'ds-multisite-enhancements') : $value)
        );
    };
    ?>
    <tr>
        <th scope="row"><label for="dsme_readtime_before"><?php esc_html_e('Text before number value', 'ds-multisite-enhancements'); ?></label></th>
        <td>
            <input type="text" name="dsme_readtime_before" id="dsme_readtime_before" class="regular-text"
                value="<?php echo esc_attr($values['before']); ?>" maxlength="<?php echo esc_attr(DSME_READTIME_MAX_TEXT); ?>">
            <p class="description">
                <?php esc_html_e('Shown before the number. Add a space at the end if you want one between this text and the number, for example "Read Time: ".', 'ds-multisite-enhancements'); ?>
            </p>
            <?php $hint('before'); ?>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="dsme_readtime_after"><?php esc_html_e('Text after number value', 'ds-multisite-enhancements'); ?></label></th>
        <td>
            <input type="text" name="dsme_readtime_after" id="dsme_readtime_after" class="regular-text"
                value="<?php echo esc_attr($values['after']); ?>" maxlength="<?php echo esc_attr(DSME_READTIME_MAX_TEXT); ?>">
            <p class="description">
                <?php esc_html_e('Shown after the number. Add a space at the start if you want one between the number and this text, for example " minutes".', 'ds-multisite-enhancements'); ?>
            </p>
            <?php $hint('after'); ?>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="dsme_readtime_after_singular"><?php esc_html_e('Text after number value (singular)', 'ds-multisite-enhancements'); ?></label></th>
        <td>
            <input type="text" name="dsme_readtime_after_singular" id="dsme_readtime_after_singular" class="regular-text"
                value="<?php echo esc_attr($values['after_singular']); ?>" maxlength="<?php echo esc_attr(DSME_READTIME_MAX_TEXT); ?>"
                placeholder="<?php echo esc_attr(dsme_readtime_singularize($values['after'])); ?>">
            <p class="description">
                <?php esc_html_e('Shown after the number when it is 1. Add a space at the start if you want one, for example " minute". Leave blank to use the text above with "minutes" changed to "minute" and "mins" changed to "min".', 'ds-multisite-enhancements'); ?>
            </p>
            <?php $hint('after_singular'); ?>
        </td>
    </tr>
    <tr>
        <th scope="row"><?php esc_html_e('Preview', 'ds-multisite-enhancements'); ?></th>
        <td>
            <?php
            // Rendered by the same functions the front end uses; js/readtime-settings.js updates them as you type.
            $examples = array('singular' => 1, 'plural' => max(5, (int) $values['minimum']));
            foreach ($examples as $form => $value) :
                ?>
                <p class="dsme-readtime-preview" data-preview="<?php echo esc_attr($form); ?>" aria-live="polite"><?php
                    echo dsme_readtime_markup($value, $values['before'], dsme_readtime_after_text($value, $values['after'], $values['after_singular'])); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in dsme_readtime_markup().
                ?></p>
            <?php endforeach; ?>
            <p class="description"><?php esc_html_e('Examples for a 1-minute and a 5-minute read (or the minimum time, if higher). Spaces are shown exactly as they will appear.', 'ds-multisite-enhancements'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="dsme_readtime_minimum"><?php esc_html_e('Minimum Time', 'ds-multisite-enhancements'); ?></label></th>
        <td>
            <input type="number" name="dsme_readtime_minimum" id="dsme_readtime_minimum" class="small-text"
                value="<?php echo esc_attr($values['minimum'] ? $values['minimum'] : ''); ?>" min="0" max="<?php echo esc_attr(DSME_READTIME_MAX_MINIMUM); ?>" step="1">
            <?php esc_html_e('minutes', 'ds-multisite-enhancements'); ?>
            <p class="description">
                <?php esc_html_e('The lowest number the read time will display. A post that calculates to less shows this number instead. Leave blank for no minimum (read times are always at least 1).', 'ds-multisite-enhancements'); ?>
            </p>
            <?php $hint('minimum'); ?>
        </td>
    </tr>
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
    $result   = dsme_readtime_save_network_settings(wp_unslash($_POST));

    wp_safe_redirect(add_query_arg(true === $result ? array('updated' => 'true') : array('dsme_error' => $result), $redirect));
    exit;
}

/**
 * Validate and save the network settings. Nothing is saved if any field is invalid.
 *
 * @param array $input Unslashed form input.
 * @return true|string True, or the key of the invalid field ("wpm", "minimum").
 */
function dsme_readtime_save_network_settings(array $input)
{
    $wpm = isset($input['dsme_words_per_minute']) && is_string($input['dsme_words_per_minute']) ? trim($input['dsme_words_per_minute']) : '';
    if (! ctype_digit($wpm) || (int) $wpm < DSME_MIN_WPM || (int) $wpm > DSME_MAX_WPM) {
        return 'wpm';
    }

    $minimum = dsme_readtime_sanitize_minimum($input['dsme_readtime_minimum'] ?? '');
    if (null === $minimum) {
        return 'minimum';
    }

    update_site_option(DSME_OPTION_WPM, (int) $wpm);
    update_site_option(DSME_OPTION_READTIME, array(
        'before'         => dsme_readtime_sanitize_text($input['dsme_readtime_before'] ?? ''),
        'after'          => dsme_readtime_sanitize_text($input['dsme_readtime_after'] ?? ''),
        'after_singular' => dsme_readtime_sanitize_text($input['dsme_readtime_after_singular'] ?? ''),
        'minimum'        => $minimum,
        'allow_override' => ! empty($input['dsme_readtime_allow_override']),
        'allow_sites'    => ! empty($input['dsme_readtime_allow_sites']),
    ));

    return true;
}
