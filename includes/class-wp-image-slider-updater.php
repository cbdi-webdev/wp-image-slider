<?php
/** GitHub stable-release updates, using WordPress's native update API. */
defined('ABSPATH') || exit;

class WP_Image_Slider_Updater
{
    const DEFAULT_REPOSITORY = 'cbdi-webdev/wp-image-slider';
    private $plugin_file;

    public function __construct()
    {
        $this->plugin_file = plugin_basename(WP_IMAGE_SLIDER_FILE);
        add_filter('update_plugins_wp-image-slider.invalid', array($this, 'check_update'), 10, 4);
        add_filter('plugins_api', array($this, 'plugin_information'), 10, 3);
        add_filter('plugin_action_links_' . $this->plugin_file, array($this, 'action_links'));
        add_filter('network_admin_plugin_action_links_' . $this->plugin_file, array($this, 'action_links'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('network_admin_menu', array($this, 'admin_menu'));
        add_action('admin_post_wp_image_slider_check_updates', array($this, 'manual_check'));
        add_action('admin_post_wp_image_slider_save_repository', array($this, 'save_repository'));
        add_action('upgrader_process_complete', array($this, 'upgrade_complete'), 10, 2);
    }

    public function repository()
    {
        $repository = defined('WP_IMAGE_SLIDER_GITHUB_REPOSITORY')
            ? WP_IMAGE_SLIDER_GITHUB_REPOSITORY
            : get_site_option('wp_image_slider_github_repository', self::DEFAULT_REPOSITORY);
        return is_string($repository) && preg_match('#^[a-zA-Z0-9][a-zA-Z0-9-]*/[a-zA-Z0-9_.-]+$#D', $repository)
            ? $repository : '';
    }

    private function cache_key()
    {
        return 'wp_image_slider_release_' . md5($this->repository());
    }

    private function request_json($url)
    {
        $response = wp_safe_remote_get($url, array(
            'timeout' => 10,
            'headers' => array('Accept' => 'application/json', 'User-Agent' => 'WP-Image-Slider/' . WP_IMAGE_SLIDER_VERSION),
            'limit_response_size' => 262144,
        ));
        if (is_wp_error($response)) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code($response);
        if (200 !== $code) {
            return new WP_Error('release_http', sprintf(__('GitHub returned HTTP %d. Confirm the repository is public and has a published release with both required assets.', 'wp-image-slider'), $code));
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($data) ? $data : new WP_Error('release_json', __('The release response is not valid JSON.', 'wp-image-slider'));
    }

    /** Only accept our named assets from this exact repository and release. */
    private function asset_url($release, $name)
    {
        $expected = 'https://github.com/' . $this->repository() . '/releases/download/' . rawurlencode($release['tag_name']) . '/' . $name;
        foreach (isset($release['assets']) && is_array($release['assets']) ? $release['assets'] : array() as $asset) {
            if (is_array($asset) && isset($asset['name'], $asset['state'], $asset['browser_download_url'])
                && $asset['name'] === $name && $asset['state'] === 'uploaded'
                && $asset['browser_download_url'] === $expected) {
                return $expected;
            }
        }
        return '';
    }

    public function release($force = false)
    {
        $cached = get_site_transient($this->cache_key());
        if (! $force && is_array($cached)) {
            return $cached;
        }
        $state = array('checked_at' => time(), 'error' => '', 'release' => false);
        $repository = $this->repository();
        if (! $repository) {
            $state['error'] = __('Set a GitHub repository in owner/repository format.', 'wp-image-slider');
        } else {
            $release = $this->request_json('https://api.github.com/repos/' . $repository . '/releases/latest');
            if (is_wp_error($release)) {
                $state['error'] = $release->get_error_message();
            } elseif (! empty($release['draft']) || ! empty($release['prerelease'])
                || empty($release['tag_name']) || ! is_string($release['tag_name'])
                || ! preg_match('/^v?(\d+\.\d+\.\d+)$/D', $release['tag_name'], $match)) {
                $state['error'] = __('The latest release must be a stable tag such as v1.3.0.', 'wp-image-slider');
            } else {
                $package = $this->asset_url($release, 'wp-image-slider.zip');
                $manifest_url = $this->asset_url($release, 'wp-image-slider.json');
                if (! $package || ! $manifest_url) {
                    $state['error'] = __('The release needs wp-image-slider.zip and wp-image-slider.json assets. Publish it using the included release workflow.', 'wp-image-slider');
                } else {
                    $manifest = $this->request_json($manifest_url);
                    if (is_wp_error($manifest)) {
                        $state['error'] = $manifest->get_error_message();
                    } elseif (! isset($manifest['version'], $manifest['requires'], $manifest['requires_php'])
                        || $manifest['version'] !== $match[1]
                        || ! is_string($manifest['requires']) || ! preg_match('/^\d+\.\d+(\.\d+)?$/D', $manifest['requires'])
                        || ! is_string($manifest['requires_php']) || ! preg_match('/^\d+\.\d+(\.\d+)?$/D', $manifest['requires_php'])
                        || (isset($manifest['tested']) && (! is_string($manifest['tested']) || ! preg_match('/^\d+\.\d+(\.\d+)?$/D', $manifest['tested'])))) {
                        $state['error'] = __('Release metadata is invalid or does not match the release tag.', 'wp-image-slider');
                    } else {
                        $state['release'] = array(
                            'slug' => 'wp-image-slider',
                            'version' => $match[1],
                            'url' => 'https://github.com/' . $repository . '/releases/tag/' . rawurlencode($release['tag_name']),
                            'package' => $package,
                            'requires' => $manifest['requires'],
                            'requires_php' => $manifest['requires_php'],
                            'tested' => isset($manifest['tested']) ? $manifest['tested'] : '',
                            'notes' => isset($release['body']) && is_string($release['body']) ? $release['body'] : '',
                            'published_at' => isset($release['published_at']) && is_string($release['published_at']) ? $release['published_at'] : '',
                        );
                    }
                }
            }
        }
        set_site_transient($this->cache_key(), $state, $state['error'] ? 15 * MINUTE_IN_SECONDS : 6 * HOUR_IN_SECONDS);
        return $state;
    }

    public function check_update($update, $plugin_data, $plugin_file, $locales)
    {
        if ($plugin_file !== $this->plugin_file) {
            return $update;
        }
        $state = $this->release();
        if (! $state['release']) {
            return false;
        }
        $release = $state['release'];
        unset($release['notes'], $release['published_at']);
        // Core compares versions and places the result in response or no_update.
        return $release;
    }

    public function plugin_information($result, $action, $args)
    {
        if ('plugin_information' !== $action || ! isset($args->slug) || 'wp-image-slider' !== $args->slug) {
            return $result;
        }
        $state = $this->release();
        if (! $state['release']) {
            return $result;
        }
        $release = $state['release'];
        return (object) array(
            'name' => 'WP Image Slider', 'slug' => 'wp-image-slider', 'version' => $release['version'],
            'author' => 'Kaia Homes', 'homepage' => $release['url'],
            'requires' => $release['requires'], 'requires_php' => $release['requires_php'],
            'tested' => $release['tested'], 'last_updated' => $release['published_at'],
            'download_link' => $release['package'],
            'sections' => array(
                'description' => '<p>' . esc_html__('Responsive image sliders using [wp_image_slider], media IDs, image URLs, or recent post images.', 'wp-image-slider') . '</p>',
                'changelog' => '<pre style="white-space:pre-wrap">' . esc_html($release['notes']) . '</pre>',
            ),
        );
    }

    private function settings_url()
    {
        return is_multisite() ? network_admin_url('settings.php?page=wp-image-slider-updates') : admin_url('tools.php?page=wp-image-slider-updates');
    }

    public function action_links($links)
    {
        if (current_user_can('update_plugins')) {
            $links[] = '<a href="' . esc_url($this->settings_url()) . '">' . esc_html__('Updates', 'wp-image-slider') . '</a>';
        }
        return $links;
    }

    public function admin_menu()
    {
        if (is_multisite() && ! is_network_admin()) {
            return;
        }
        add_submenu_page(is_network_admin() ? 'settings.php' : 'tools.php', 'WP Image Slider Updates', 'WP Image Slider Updates', 'update_plugins', 'wp-image-slider-updates', array($this, 'settings_page'));
    }

    public function settings_page()
    {
        if (! current_user_can('update_plugins')) {
            return;
        }
        $state = $this->release();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('WP Image Slider Updates', 'wp-image-slider'); ?></h1>
            <p><?php echo esc_html(sprintf(__('Installed version: %s', 'wp-image-slider'), WP_IMAGE_SLIDER_VERSION)); ?></p>
            <p><?php esc_html_e('Updates come from stable GitHub releases. The repository must be public and include the ZIP and JSON assets built by this plugin’s release workflow.', 'wp-image-slider'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="wp_image_slider_save_repository">
                <?php wp_nonce_field('wp_image_slider_save_repository'); ?>
                <p><label for="wp-image-slider-repository"><?php esc_html_e('GitHub repository (owner/repository)', 'wp-image-slider'); ?></label></p>
                <input id="wp-image-slider-repository" name="repository" type="text" class="regular-text" value="<?php echo esc_attr($this->repository()); ?>" <?php disabled(defined('WP_IMAGE_SLIDER_GITHUB_REPOSITORY')); ?> required>
                <?php if (defined('WP_IMAGE_SLIDER_GITHUB_REPOSITORY')) : ?>
                    <p><?php esc_html_e('The repository is managed by WP_IMAGE_SLIDER_GITHUB_REPOSITORY in wp-config.php.', 'wp-image-slider'); ?></p>
                <?php else : submit_button(__('Save repository', 'wp-image-slider')); endif; ?>
            </form>
            <?php if ($state['error']) : ?>
                <div class="notice notice-warning inline"><p><?php echo esc_html($state['error']); ?></p></div>
            <?php elseif (version_compare($state['release']['version'], WP_IMAGE_SLIDER_VERSION, '>')) : ?>
                <p><?php echo esc_html(sprintf(__('Version %s is available. Open Dashboard → Updates to install it.', 'wp-image-slider'), $state['release']['version'])); ?></p>
            <?php else : ?>
                <p><?php esc_html_e('You are running the latest published version.', 'wp-image-slider'); ?></p>
            <?php endif; ?>
            <p><?php echo esc_html(sprintf(__('Last checked (UTC): %s', 'wp-image-slider'), gmdate('Y-m-d H:i:s', $state['checked_at']))); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="wp_image_slider_check_updates">
                <?php wp_nonce_field('wp_image_slider_check_updates'); ?>
                <?php submit_button(__('Check for updates now', 'wp-image-slider'), 'secondary'); ?>
            </form>
        </div>
        <?php
    }

    private function authorize($action)
    {
        if (! current_user_can('update_plugins')) {
            wp_die(esc_html__('You are not allowed to manage plugin updates.', 'wp-image-slider'), '', array('response' => 403));
        }
        check_admin_referer($action);
    }

    public function save_repository()
    {
        $this->authorize('wp_image_slider_save_repository');
        if (! defined('WP_IMAGE_SLIDER_GITHUB_REPOSITORY')) {
            $repository = isset($_POST['repository']) && is_string($_POST['repository']) ? trim(wp_unslash($_POST['repository'])) : '';
            if (! preg_match('#^[a-zA-Z0-9][a-zA-Z0-9-]*/[a-zA-Z0-9_.-]+$#D', $repository)) {
                wp_die(esc_html__('Use owner/repository format.', 'wp-image-slider'), '', array('response' => 400));
            }
            delete_site_transient($this->cache_key());
            update_site_option('wp_image_slider_github_repository', $repository);
        }
        $this->refresh();
    }

    public function manual_check()
    {
        $this->authorize('wp_image_slider_check_updates');
        $this->refresh();
    }

    private function refresh()
    {
        $this->release(true);
        wp_clean_plugins_cache(true);
        wp_update_plugins();
        wp_safe_redirect($this->settings_url());
        exit;
    }

    public function upgrade_complete($upgrader, $options)
    {
        if (isset($options['type']) && 'plugin' === $options['type']
            && (in_array($this->plugin_file, isset($options['plugins']) ? (array) $options['plugins'] : array(), true)
                || (isset($options['plugin']) && $options['plugin'] === $this->plugin_file))) {
            delete_site_transient($this->cache_key());
        }
    }
}
