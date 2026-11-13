<?php
/**
 * Editor integration for every tracked post type, custom post types included:
 *
 * - A "dsme_readtime" REST field (edit context) with the calculated time and
 *   the post's override, used by the block and the sidebar panel. A REST field
 *   is used rather than registered meta because meta only appears in the REST
 *   API for post types that support "custom-fields".
 * - Block editor: a sidebar panel (js/readtime-panel.js) to set the override.
 * - Classic editor: the same field as a side meta box.
 *
 * The panel and meta box only appear while per-post overrides are allowed.
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

/** REST field name on tracked post types. */
define('DSME_READTIME_REST_FIELD', 'dsme_readtime');

add_action('rest_api_init', 'dsme_readtime_register_rest_field');
function dsme_readtime_register_rest_field(): void
{
    register_rest_field(dsme_readtime_post_types(), DSME_READTIME_REST_FIELD, array(
        'get_callback'    => 'dsme_readtime_rest_get',
        'update_callback' => 'dsme_readtime_rest_update',
        'schema'          => array(
            'description' => __('Read time in minutes.', 'ds-multisite-enhancements'),
            'type'        => 'object',
            'context'     => array('edit'),
            'properties'  => array(
                'calculated' => array(
                    'description' => __('Calculated read time with the minimum time applied, or null for a post that has never been saved.', 'ds-multisite-enhancements'),
                    'type'        => array('integer', 'null'),
                    'readonly'    => true,
                ),
                'override'   => array(
                    'description' => __('Per-post override in minutes; 0 for none.', 'ds-multisite-enhancements'),
                    'type'        => 'integer',
                    'minimum'     => 0,
                    'maximum'     => DSME_READTIME_MAX_OVERRIDE,
                ),
            ),
        ),
    ));
}

/**
 * @param array $data Post data prepared for the response.
 */
function dsme_readtime_rest_get(array $data): array
{
    $post = get_post((int) $data['id']);
    $new  = ! $post || 'auto-draft' === $post->post_status;

    return array(
        'calculated' => $new ? null : dsme_readtime_apply_minimum(dsme_get_readtime($post)),
        'override'   => $post ? dsme_get_readtime_override($post) : 0,
    );
}

/**
 * Save the override sent by the block editor. "calculated" is read-only and ignored.
 *
 * @param mixed   $value
 * @param WP_Post $post
 * @return true|WP_Error
 */
function dsme_readtime_rest_update($value, $post)
{
    if (! is_array($value) || ! array_key_exists('override', $value)) {
        return true;
    }

    if (! current_user_can('edit_post', $post->ID)) {
        return new WP_Error('rest_cannot_edit', __('Sorry, you are not allowed to edit this post.', 'ds-multisite-enhancements'), array('status' => rest_authorization_required_code()));
    }

    dsme_readtime_save_override((int) $post->ID, $value['override']);

    return true;
}

/**
 * Store a post's override, or delete it when blank/0/invalid.
 *
 * @param mixed $value
 */
function dsme_readtime_save_override(int $post_id, $value): void
{
    $override = dsme_readtime_sanitize_override($value);

    if ($override > 0) {
        update_post_meta($post_id, DSME_META_OVERRIDE, $override);
    } else {
        delete_post_meta($post_id, DSME_META_OVERRIDE);
    }
}

/**
 * Block editor sidebar panel, on tracked post types while overrides are allowed.
 */
add_action('enqueue_block_editor_assets', 'dsme_readtime_enqueue_panel');
function dsme_readtime_enqueue_panel(): void
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (! $screen || 'post' !== $screen->base || ! dsme_tracks_post_type((string) $screen->post_type) || ! dsme_readtime_setting('allow_override')) {
        return;
    }

    $relative = 'js/readtime-panel.js';
    $path     = DSME_PATH . $relative;
    if (! file_exists($path)) {
        return;
    }

    wp_enqueue_script(
        'dsme-readtime-panel',
        DSME_URL . $relative,
        array('wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n'),
        (string) filemtime($path),
        true
    );
    wp_set_script_translations('dsme-readtime-panel', 'ds-multisite-enhancements');
}

/**
 * Classic editor meta box. "__back_compat_meta_box" hides it in the block
 * editor, which has the sidebar panel instead.
 */
add_action('add_meta_boxes', 'dsme_readtime_add_meta_box', 10, 2);
function dsme_readtime_add_meta_box($post_type, $post = null): void
{
    if (! is_string($post_type) || ! dsme_tracks_post_type($post_type) || ! dsme_readtime_setting('allow_override')) {
        return;
    }

    add_meta_box(
        'dsme-readtime-override',
        __('Read Time', 'ds-multisite-enhancements'),
        'dsme_readtime_render_meta_box',
        $post_type,
        'side',
        'default',
        array('__back_compat_meta_box' => true)
    );
}

function dsme_readtime_render_meta_box(WP_Post $post): void
{
    $override = dsme_get_readtime_override($post);
    wp_nonce_field('dsme_readtime_override', 'dsme_readtime_override_nonce');
    ?>
    <p>
        <label for="dsme_readtime_override"><?php esc_html_e('Override calculated read time to show:', 'ds-multisite-enhancements'); ?></label>
        <input type="number" name="dsme_readtime_override" id="dsme_readtime_override" class="small-text"
            value="<?php echo esc_attr($override ? $override : ''); ?>" min="1" max="<?php echo esc_attr(DSME_READTIME_MAX_OVERRIDE); ?>" step="1">
    </p>
    <p class="description">
        <?php
        if ('auto-draft' === $post->post_status) {
            esc_html_e('Leave blank to use the calculated time, available after the post is saved.', 'ds-multisite-enhancements');
        } else {
            /* translators: %d: calculated read time in minutes. */
            echo esc_html(sprintf(__('Leave blank to use the calculated time (currently %d).', 'ds-multisite-enhancements'), dsme_readtime_apply_minimum(dsme_get_readtime($post))));
        }
        ?>
    </p>
    <?php
}

add_action('save_post', 'dsme_readtime_save_meta_box', 10, 2);
function dsme_readtime_save_meta_box($post_id, $post): void
{
    if (! isset($_POST['dsme_readtime_override_nonce']) || ! wp_verify_nonce(sanitize_key(wp_unslash($_POST['dsme_readtime_override_nonce'])), 'dsme_readtime_override')) {
        return;
    }

    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || ! current_user_can('edit_post', $post_id)) {
        return;
    }

    dsme_readtime_save_override((int) $post_id, isset($_POST['dsme_readtime_override']) ? wp_unslash($_POST['dsme_readtime_override']) : '');
}
