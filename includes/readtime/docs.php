<?php
/**
 * "Displaying the read time" documentation, shown below the settings on the
 * network and site Read Time pages.
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * A code sample with a Copy button (handled by js/readtime-settings.js).
 */
function dsme_readtime_render_snippet(string $id, string $code): void
{
    ?>
    <div class="dsme-snippet">
        <pre><code id="<?php echo esc_attr($id); ?>"><?php echo esc_html($code); ?></code></pre>
        <button type="button" class="button dsme-copy" data-copy-target="<?php echo esc_attr($id); ?>"
            data-copied="<?php esc_attr_e('Copied.', 'ds-multisite-enhancements'); ?>" data-failed="<?php esc_attr_e('Press Ctrl+C (Cmd+C) to copy.', 'ds-multisite-enhancements'); ?>">
            <?php esc_html_e('Copy', 'ds-multisite-enhancements'); ?>
        </button>
        <span class="dsme-copy-status" role="status" aria-live="polite"></span>
    </div>
    <?php
}

function dsme_readtime_render_docs(): void
{
    ?>
    <div class="dsme-docs">
        <h2><?php esc_html_e('Displaying the read time', 'ds-multisite-enhancements'); ?></h2>
        <p><?php esc_html_e('There are three ways to show a post\'s read time. All three output the same markup, using the text before and after the number from the settings above.', 'ds-multisite-enhancements'); ?></p>

        <h3><?php esc_html_e('Block', 'ds-multisite-enhancements'); ?></h3>
        <p><?php esc_html_e('In the block editor, add the "Read Time" block (in the Theme category, or search for "read time"). It has no settings. In the editor it shows 𝑥 in place of the number until the post has been saved; on the site it always shows the current value. It also works inside a Query Loop and in block theme templates.', 'ds-multisite-enhancements'); ?></p>

        <h3><?php esc_html_e('Shortcode', 'ds-multisite-enhancements'); ?></h3>
        <p><?php esc_html_e('Add this to post content, a Shortcode block or a widget to show the current post\'s read time:', 'ds-multisite-enhancements'); ?></p>
        <?php dsme_readtime_render_snippet('dsme-snippet-shortcode', '[dsme-readtime]'); ?>
        <p><?php esc_html_e('To show another post\'s read time from the same site, add its ID:', 'ds-multisite-enhancements'); ?></p>
        <?php dsme_readtime_render_snippet('dsme-snippet-shortcode-id', '[dsme-readtime post_id="123"]'); ?>

        <h3><?php esc_html_e('PHP (themes)', 'ds-multisite-enhancements'); ?></h3>
        <p><?php esc_html_e('Paste this into a theme template inside The Loop, for example in single.php or a template part for post tiles. The function_exists() check keeps the theme working if the feature or plugin is turned off.', 'ds-multisite-enhancements'); ?></p>
        <?php dsme_readtime_render_snippet('dsme-snippet-php', "<?php if ( function_exists( 'dsme_the_readtime' ) ) { dsme_the_readtime(); } ?>"); ?>
        <p><?php esc_html_e('Other functions:', 'ds-multisite-enhancements'); ?></p>
        <ul class="dsme-docs-list">
            <li><?php echo wp_kses(__('<code>dsme_the_readtime( $post )</code> echoes the read time for a post ID or object; without an argument it uses the current post.', 'ds-multisite-enhancements'), array('code' => array())); ?></li>
            <li><?php echo wp_kses(__('<code>dsme_get_readtime_display( $post )</code> returns the same HTML instead of echoing it.', 'ds-multisite-enhancements'), array('code' => array())); ?></li>
            <li><?php echo wp_kses(__('<code>dsme_readtime_display_minutes( $post )</code> returns just the number that would be displayed.', 'ds-multisite-enhancements'), array('code' => array())); ?></li>
        </ul>
        <p><?php echo wp_kses(__('On network-wide listings, call these after <code>switch_to_blog()</code> to the post\'s own site: read times are stored per site.', 'ds-multisite-enhancements'), array('code' => array())); ?></p>

        <h3><?php esc_html_e('Which number is shown', 'ds-multisite-enhancements'); ?></h3>
        <ol class="dsme-docs-list">
            <li><?php esc_html_e('If per-post overrides are allowed and the post has an "Override calculated read time to show" value, that number is shown.', 'ds-multisite-enhancements'); ?></li>
            <li><?php esc_html_e('Otherwise the calculated time is shown: the post\'s word count divided by the words per minute, rounded up, and never less than 1.', 'ds-multisite-enhancements'); ?></li>
            <li><?php esc_html_e('If that is below the minimum time, the minimum time is shown instead.', 'ds-multisite-enhancements'); ?></li>
        </ol>
        <p><?php esc_html_e('Read times are calculated for every public post type, including custom post types such as a portfolio. The text counted is the post\'s main content.', 'ds-multisite-enhancements'); ?></p>

        <h3><?php esc_html_e('Styling', 'ds-multisite-enhancements'); ?></h3>
        <p><?php esc_html_e('The output is inline and can be styled with these classes:', 'ds-multisite-enhancements'); ?></p>
        <pre class="dsme-docs-code"><code><?php echo esc_html('<span class="dsme-readtime"><span class="dsme-readtime-before">…</span><span class="dsme-readtime-value">5</span><span class="dsme-readtime-after">…</span></span>'); ?></code></pre>
        <p><?php echo wp_kses(__('The block wraps it in <code>&lt;div class="wp-block-dsme-readtime"&gt;</code>.', 'ds-multisite-enhancements'), array('code' => array())); ?></p>

        <h3><?php esc_html_e('Developer filters', 'ds-multisite-enhancements'); ?></h3>
        <ul class="dsme-docs-list">
            <li><?php echo wp_kses(__('<code>dsme_readtime_content</code> ( $content, $post ): the text that is counted. Use it for post types that keep their text in custom fields.', 'ds-multisite-enhancements'), array('code' => array())); ?></li>
            <li><?php echo wp_kses(__('<code>dsme_readtime_tracks_post_type</code> ( $tracked, $post_type ): include or exclude a post type.', 'ds-multisite-enhancements'), array('code' => array())); ?></li>
            <li><?php echo wp_kses(__('<code>dsme_readtime_output</code> ( $html, $minutes, $post_id ): change the final HTML.', 'ds-multisite-enhancements'), array('code' => array())); ?></li>
        </ul>
    </div>
    <?php
}
