# DS Multisite Enhancements

A toolkit of network-level enhancements for WordPress Multisite networks at scale. Network-activate it.

## Structure

```
ds-multisite-enhancements.php   Bootstrap: loads everything in includes/
includes/
  00-core/                      Shared: Network Admin menu shell, stylesheet enqueue
  readtime/                     Read Time feature
scss/                           Sass sources
css/                            Compiled CSS (committed; built from scss/)
```

### Adding a feature

Drop it into `includes/`. Nothing else needs editing; the bootstrap loads it on `plugins_loaded` (priority 1).

- **Single file:** `includes/<feature>.php`
- **Several files:** a folder, `includes/<feature>/`. Every `*.php` directly inside it is loaded. Subfolders are *not* loaded, so a feature can keep templates or partials in `includes/<feature>/templates/` and `require` them itself.
- **Order:** files and folders load together in natural order (`natsort`), and so do the files inside a folder. Prefix names to control order (`00-core/`, `10-foo.php`). Features that only hook into WordPress actions rarely need this.
- **Skipped:** names starting with `_` (`_helper.php`, `_retired-feature/`) and `index.php` guard files. To turn a feature off, delete it or prefix it with `_`.
- **Failures:** if an include throws while loading, it is skipped, logged to the PHP error log, and listed in an admin notice for network admins. The other features keep loading.

Each PHP file starts with `defined('ABSPATH') || exit;`. Prefix functions and constants with `dsme_` / `DSME_`.

A feature with network settings adds a submenu under **Network Admin → Multisite Enhancements** and lists itself on the Overview page (see `includes/00-core/admin-menu.php`):

```php
add_action('network_admin_menu', function () {
    add_submenu_page(DSME_MENU_SLUG, 'My Feature', 'My Feature', 'manage_network_options', 'dsme-my-feature', 'dsme_my_feature_render');
}, 20); // After the parent menu (priority 10).

add_filter('dsme_features', function (array $features) {
    $features['dsme-my-feature'] = array(__('My Feature', 'ds-multisite-enhancements'), __('What it does.', 'ds-multisite-enhancements'));
    return $features;
});
```

Constants available to features: `DSME_VERSION`, `DSME_FILE`, `DSME_PATH`, `DSME_URL`, `DSME_MENU_SLUG`, `DSME_ADMIN_STYLE`.

### Styles

Edit the Sass in `scss/`, never `css/` directly. `scss/ds-multisite-enhancements.scss` compiles to `css/ds-multisite-enhancements.css`, which is enqueued on every admin screen, network admin included, under the handle `dsme-admin`. Put each feature's styles in a partial (`scss/<feature>/_<name>.scss`) and `@use` it from the main file. Files whose names start with `_` are partials and produce no CSS file of their own.

```
npm install
npm run build   # compressed, no source map: run before committing
npm run watch   # expanded with source maps, rebuilds on save
```

Commit the compiled `css/`, because the plugin is installed straight from the repository and there is no build step on install.

## Installing with Composer

The package is `danielsantoro/ds-multisite-enhancements` (type `wordpress-plugin`, so `composer/installers` places it in `wp-content/plugins/ds-multisite-enhancements`). Until it is on Packagist, add the GitHub repository:

```json
"repositories": {
    "dsme": { "type": "vcs", "url": "https://github.com/DanielSantoro/ds-multisite-enhancements" }
},
"require": {
    "danielsantoro/ds-multisite-enhancements": "~0.0.1"
}
```

Versions come from git tags (`v0.0.1`, ...). Before 1.0, `~0.0.1` accepts any `0.0.x` patch release but not `0.1.0`.

## Read time

The plugin adds a top-level **Multisite Enhancements** menu to Network Admin, directly below Settings. It opens an **Overview** page listing the features, and each feature has its own submenu settings page (currently **Read Time**). Its code is in `includes/readtime/`.

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
