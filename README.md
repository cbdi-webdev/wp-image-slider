# WP Image Slider

Standalone WordPress plugin, version **1.3.0**. Requires WordPress 5.8+ and PHP 7.4+. No theme, page builder, Composer, npm, or frontend library dependency.

Repository: https://github.com/cbdi-webdev/wp-image-slider

## Install on another website

1. Download **wp-image-slider.zip** from the latest GitHub Release. Use the release asset rather than the automatically generated source archive.
2. Open **Plugins → Add New → Upload Plugin**, upload the ZIP, and activate **WP Image Slider**.
3. Add `[wp_image_slider ids="123,456"]` to a Shortcode block using attachment IDs from that site's Media Library. IDs belong to each website and do not transfer with the plugin.
4. Open **Tools → WP Image Slider Updates** to check the release source. On multisite, use **Network Admin → Settings → WP Image Slider Updates**.

The ZIP contains one `wp-image-slider/` directory. Keep that name so updates replace the same plugin directory.

## Shortcodes

```text
[wp_image_slider]
[wp_image_slider ids="123,456,789"]
[wp_image_slider urls="https://example.com/image-1.jpg,https://example.com/image-2.jpg"]
[wp_image_slider ids="123,456" urls="https://example.com/extra.jpg" limit="5"]
[wp_image_slider show_buttons="no"]
[wp_image_slider show_arrows="no" show_slideshow="yes" active_color="#2f80ed"]
```

`[image_slider]`, `[news_slider]`, and `[kaia_news_slider]` are aliases. Existing shortcode content continues to work.

| Attribute | Default | Behavior |
| --- | --- | --- |
| `ids` | empty | Comma-separated media attachment IDs; used first. |
| `urls` | empty | Comma-separated HTTP(S) image URLs; used after IDs. |
| `limit` | 8, or 12 for custom sources | Maximum 12 slides. |
| `show_buttons` | yes | Show arrow and fullscreen controls. |
| `show_arrows` | yes | Show previous/next arrows. |
| `show_slideshow` | yes | Show fullscreen control. |
| `active_color` | #e93a8b | Active pagination dot color. |

Without custom sources, the plugin uses featured images from recent published posts. It returns no markup if there are no images. With `ids` or `urls`, it uses only valid supplied images. No site content is bundled.

Features include swipe/drag, three-second autoplay respecting reduced motion, pagination, a counter, fullscreen viewing, and modal keyboard navigation. Assets load only when a slider renders. Existing `kaia-news-slider` CSS classes and data attributes remain stable for custom styles.

## Updates

The default source is `cbdi-webdev/wp-image-slider`. WordPress's scheduled plugin checks consult the latest stable GitHub Release and display newer versions under **Plugins** and **Dashboard → Updates**. Install with **Update now**, or opt into WordPress's normal auto-update setting. This plugin does not enable auto-updates automatically.

**Tools → WP Image Slider Updates → Check for updates now** bypasses the release cache and refreshes WordPress's update data. Successful checks are cached network-wide for six hours; failures for fifteen minutes. Errors appear on this page rather than as “up to date.” No GitHub requests occur during shortcode rendering.

Each release must contain **wp-image-slider.zip** and **wp-image-slider.json**, generated together by the build script. The checker rejects drafts, prereleases, missing assets, external asset URLs, malformed metadata, and tag/manifest mismatches. Release-specific WordPress/PHP requirements let WordPress block incompatible upgrades.

The repository and releases must be **public** for this unauthenticated updater. Private downloads are not supported; no GitHub token is stored on websites.

To use a fork, change the repository on the Updates page, or lock it in `wp-config.php`:

```php
define('WP_IMAGE_SLIDER_GITHUB_REPOSITORY', 'your-account/wp-image-slider');
```

Only users who can update plugins may change the repository or force checks; both forms require a nonce. Configure only repositories you trust to supply executable plugin code.

The `Update URI: https://wp-image-slider.invalid` header is a reserved identity, not a network endpoint. It prevents WordPress.org from replacing this plugin with a similarly named plugin. Its native hostname hook uses the GitHub API; no requests go to `.invalid`. See [WordPress's update hook documentation](https://developer.wordpress.org/reference/hooks/update_plugins_hostname/) and [GitHub's releases API](https://docs.github.com/en/rest/releases/releases).

## Versioning and publishing

This directory is an independent Git repository with remote `https://github.com/cbdi-webdev/wp-image-slider.git`. The surrounding website may also track an installed copy; perform plugin releases from this repository.

For every release:

1. Update `Version` and `WP_IMAGE_SLIDER_VERSION` in `wp-image-slider.php`, `Stable tag` in `readme.txt`, and `CHANGELOG.md`. Update minimum requirements when needed. Add `Tested up to` to `readme.txt` only after testing that WordPress version.
2. Run `php tests/updater-test.php` and `php tools/build-release.php`.
3. Commit and push a matching stable tag:

```sh
git add .
git commit -m "Release 1.3.1"
git push origin main
git tag v1.3.1
git push origin v1.3.1
```

GitHub Actions validates versions, runs checks, builds both assets, and publishes a release. Enable Actions and allow `contents: write` in repository/organization settings. The initial release is `v1.3.0`.

To build manually (PHP CLI with the zip extension), run `php tools/build-release.php` and upload **both** files in `dist/` to the matching GitHub Release. The packaging allowlist excludes Git data, tests, workflows, build tools, credentials, and site-specific integrations.

## Existing Kaia site

The old plugin path was `news-slider/news-slider.php`. After renaming, replace that entry with `wp-image-slider/wp-image-slider.php` in the active plugin list, or activate WP Image Slider in WordPress. Keep the old directory removed to avoid duplicate shortcode registration.

Kaia's fallback images now live in `wp-content/mu-plugins/kaia-image-slider-defaults.php` in the website repository. That integration is excluded from releases and is not needed on other sites. Other sites can supply defaults through `wp_image_slider_fallback_slides`, returning arrays containing `url` and `alt`.
