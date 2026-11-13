# DS Multisite Enhancements

A toolkit of network-level enhancements for WordPress Multisite networks at scale. Network-activate it.

## Structure

```
ds-multisite-enhancements.php   Bootstrap: loads core and enabled features
includes/
    00-core/                      Shared: feature registry, Network Admin menu, assets
  readtime/                     Read Time feature
  network-use-checker/          Network Use Checker feature
js/                             Admin and editor scripts (plain JS, no build step)
tests/                          Standalone feature toggle checks
scss/                           Sass sources
css/                            Compiled CSS (committed; built from scss/)
```

### Adding a feature

Add the feature to `includes/` and register its overview details in `dsme_get_features()` in `includes/00-core/admin-menu.php`. The registry must be available even when the feature itself is disabled. Features start disabled and are loaded on `plugins_loaded` (priority 1) only after a network administrator enables them on the overview page.

- **Single file:** `includes/<feature>.php`
- **Several files:** a folder, `includes/<feature>/`. Every `*.php` directly inside it is loaded. Subfolders are *not* loaded, so a feature can keep templates or partials in `includes/<feature>/templates/` and `require` them itself.
- **Order:** files and folders load together in natural order (`natsort`), and so do the files inside a folder. Prefix names to control order (`00-core/`, `10-foo.php`). Features that only hook into WordPress actions rarely need this.
- **Skipped:** names starting with `_` (`_helper.php`, `_retired-feature/`) and `index.php` guard files. The `00-core` folder always loads; all other entries require an enabled switch.
- **Failures:** if an include throws while loading, it is skipped, logged to the PHP error log, and listed in an admin notice for network admins. The other features keep loading.

Each PHP file starts with `defined('ABSPATH') || exit;`. Prefix functions and constants with `dsme_` / `DSME_`.

A registry entry is keyed by the feature's folder name or PHP filename without the extension. `settings` and `menu_title` are optional; when supplied, the feature should register the matching submenu while enabled:

```php
$features['my-feature'] = array(
    'title'       => __('My Feature', 'ds-multisite-enhancements'),
    'description' => __('What it does.', 'ds-multisite-enhancements'),
    'settings'    => 'dsme-my-feature',
    'menu_title'  => __('My Feature', 'ds-multisite-enhancements'),
);

add_action('network_admin_menu', function () {
    add_submenu_page(DSME_MENU_SLUG, 'My Feature', 'My Feature', 'manage_network_options', 'dsme-my-feature', 'dsme_my_feature_render');
}, 20); // After the parent menu (priority 10).
```

Constants available to features: `DSME_VERSION`, `DSME_FILE`, `DSME_PATH`, `DSME_URL`, `DSME_MENU_SLUG`, `DSME_ADMIN_STYLE`.

Run the standalone checks with `php tests/feature-toggle.php`, `php tests/readtime.php` and `node --test tests/overview.test.js tests/readtime.test.js`.

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

The plugin adds a top-level **Multisite Enhancements** menu to Network Admin, directly below Settings. Its **Overview** page enables and disables features network-wide. Read Time is disabled by default; enable it to use its **Read Time** submenu and functionality. Disabling it retains its settings and previously stored post metadata. Its code is in `includes/readtime/`.

**Network settings** (Network Admin → Multisite Enhancements → Read Time):

| Setting | Stored as | Sites can override |
|---|---|---|
| Words per minute (default 250) | network option `dsme_words_per_minute` | No |
| Text before number value (default `Read Time: `) | network option `dsme_readtime_settings[before]` | Yes |
| Text after number value (default ` minutes`) | `dsme_readtime_settings[after]` | Yes |
| Text after number value (singular), used when the number is 1 (blank = automatic: the first `minutes`/`mins` loses its `s`, so ` minutes` → ` minute`, ` mins` → ` min`) | `dsme_readtime_settings[after_singular]` | Yes |
| Minimum Time (blank = none) | `dsme_readtime_settings[minimum]` | Yes |
| Allow individual posts, pages, etc. to override the calculated time | `dsme_readtime_settings[allow_override]` | No |
| Allow sites to customize… | `dsme_readtime_settings[allow_sites]` | No |

Before/after text is stored exactly as entered, leading and trailing spaces included, so users add their own separating spaces (`Read Time: ` + `5` + ` minutes`). The page shows a live preview.

**Site settings** (Settings → Read Time on each site, only while *Allow sites to customize* is on): the before/after text and minimum time. Only values that differ from the network are saved, in the site option `dsme_readtime_site_settings`, so un-customized fields follow later network changes. *Reset Defaults* deletes the option. Turning the network switch off hides the page and ignores (but keeps) site values. Settings are resolved by `dsme_readtime_setting( $key )`.

**Calculation:** when a post is published, or a published post is updated, the plugin counts the words in its content (HTML, block markup and shortcodes removed), divides by the words-per-minute setting and rounds up to a whole minute (at least 1). It stores, in the post's own site:

| Meta key | Value |
|---|---|
| `dsme-readtime` | Read time in minutes |
| `dsme-wordcount` | Word count it was calculated from |
| `dsme-readtime-wpm` | Words-per-minute value used |
| `dsme-readtime-override` | Per-post override in minutes (only when set) |

Applies to every public, viewable post type except attachments, including custom post types registered without editor support (filter: `dsme_readtime_tracks_post_type`). The text counted is `post_content`; for post types that keep their text in custom fields, supply it with `add_filter( 'dsme_readtime_content', fn( $content, $post ) => ..., 10, 2 );`.

**Number shown:** the post's override if overrides are allowed and it has one; otherwise the calculated time, raised to the minimum time (`dsme_readtime_display_minutes( $post )`). The override is set in the block editor's sidebar (*Read Time* panel, `js/readtime-panel.js`) or the classic editor's *Read Time* meta box; both appear only while overrides are allowed. The block editor reads and writes it through the `dsme_readtime` REST field (`{ calculated, override }`, edit context), which works for custom post types without `custom-fields` support.

**Display:** all three output `<span class="dsme-readtime"><span class="dsme-readtime-before">…</span><span class="dsme-readtime-value">5</span><span class="dsme-readtime-after">…</span></span>` (filter: `dsme_readtime_output`).

- **PHP:** `<?php if ( function_exists( 'dsme_the_readtime' ) ) { dsme_the_readtime(); } ?>`. `dsme_get_readtime_display( $post )` returns the HTML; `dsme_get_readtime( $post )` returns the calculated minutes without the minimum or override.
- **Shortcode:** `[dsme-readtime]` or `[dsme-readtime post_id="123"]` (`[dsme_readtime]` is an alias).
- **Block:** *Read Time* (`dsme/readtime`, `includes/readtime/block/block.json`, editor script `js/readtime-block.js`). No settings; rendered on the server; supports Query Loop context. In the editor it shows `𝑥` in place of the number until the post has been saved.

The settings pages document all of this under *Displaying the read time*, with copy buttons for the snippets (`js/readtime-settings.js`).

**Existing posts and setting changes:** posts published before the plugin was active, or stored with a different words-per-minute value, are recalculated and saved the first time their read time is displayed. Drafts and previews are calculated on the fly and not saved.

**Multisite:** meta is per site, so the read time must be read in the post's own blog context. The theme's network listings already `switch_to_blog()` before rendering each tile.

**Word counting:** a word is a run of letters or digits in any script, optionally joined by an apostrophe or hyphen. Languages written without spaces (Chinese, Japanese, Thai) are undercounted.

## Network use checker

Disabled by default; enable it on the Overview page. Its code is in `includes/network-use-checker/`. Needs no settings, and stores no data.

- **Plugins → Plugin Use Check** (Network Admin; requires `manage_network_plugins`): lists every installed plugin, marks it *Network Activated* or *Site Activated* (with the names of the sites), and shows whether it is in use.
- **Themes → Theme Use Check** (Network Admin; requires `manage_network_themes`): lists every installed theme, the sites where it is active, and *Child theme of …* for child themes (do not delete parent themes).

Each page visits every site in the network on load, so very large networks may need a longer PHP time limit. Migrated from the standalone Network Use Checker plugin; deactivate that plugin once this feature is enabled, since both register menu items.
