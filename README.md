# DS Multisite Enhancements

Network-level enhancements for WordPress Multisite. Network-activate it.

## Installing with Composer

The package is `danielsantoro/ds-multisite-enhancements` (type `wordpress-plugin`, so `composer/installers` places it in `wp-content/plugins/ds-multisite-enhancements`). Until it is on Packagist, add the GitHub repository:

```json
"repositories": {
    "dsme": { "type": "vcs", "url": "https://github.com/<owner>/ds-multisite-enhancements" }
},
"require": {
    "danielsantoro/ds-multisite-enhancements": "~0.0.1"
}
```

Versions come from git tags (`v0.0.1`, ...). Before 1.0, `~0.0.1` accepts any `0.0.x` patch release but not `0.1.0`.

## Read time

The plugin adds a top-level **Multisite Enhancements** menu to Network Admin, directly below Settings. It opens an **Overview** page listing the features, and each feature has its own submenu settings page (currently **Read Time**). To add a feature, register its submenu page in `dsme_register_settings_page()` and list it in `dsme_get_features()`.

**Setting:** Network Admin → Multisite Enhancements → Read Time → *Words per minute* (default 250, stored as the network option `dsme_words_per_minute`).

**Calculation:** when a post is published, or a published post is updated, the plugin counts the words in its content (HTML, block markup and shortcodes removed), divides by the words-per-minute setting and rounds up to a whole minute. It stores, in the post's own site:

| Meta key | Value |
|---|---|
| `dsme-readtime` | Read time in minutes |
| `dsme-wordcount` | Word count it was calculated from |
| `dsme-readtime-wpm` | Words-per-minute value used |

Applies to every viewable post type with an editor (filter: `dsme_readtime_tracks_post_type`).

**Display:** the plugin takes over the `[rt_reading_time]` shortcode used throughout the theme, with the same attributes (`label`, `postfix`, `postfix_singular`, `post_id`) and the same HTML as Reading Time WP, so no template changes are needed. `[dsme_readtime]` and `dsme_get_readtime( $post )` are also available. To stop the takeover: `add_filter( 'dsme_replace_rt_reading_time', '__return_false' );`

**Existing posts and setting changes:** posts published before the plugin was active, or stored with a different words-per-minute value, are recalculated and saved the first time their read time is displayed. Drafts and previews are calculated on the fly and not saved.

**Multisite:** meta is per site, so the read time must be read in the post's own blog context. The theme's network listings already `switch_to_blog()` before rendering each tile.

**Word counting:** a word is a run of letters or digits in any script, optionally joined by an apostrophe or hyphen. Languages written without spaces (Chinese, Japanese, Thai) are undercounted.
