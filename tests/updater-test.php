<?php
/** Run with php tests/updater-test.php. No WordPress/database/network required. */
if (PHP_SAPI !== 'cli') { exit; }
define('ABSPATH', __DIR__ . '/');
define('WP_IMAGE_SLIDER_FILE', dirname(__DIR__) . '/wp-image-slider.php');
define('WP_IMAGE_SLIDER_VERSION', '1.3.0');
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
$cache = array();
$responses = array();
$calls = array();
$filters = array();
$repository = 'cbdi-webdev/wp-image-slider';
class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function add_filter($hook, $callback, $priority = 10, $args = 1) { $GLOBALS['filters'][$hook][] = $callback; }
function add_action($hook, $callback, $priority = 10, $args = 1) {}
function plugin_basename($file) { return 'wp-image-slider/' . basename($file); }
function get_site_option($key, $default) { return $GLOBALS['repository']; }
function get_site_transient($key) { return isset($GLOBALS['cache'][$key]) ? $GLOBALS['cache'][$key]['value'] : false; }
function set_site_transient($key, $value, $ttl) { $GLOBALS['cache'][$key] = array('value' => $value, 'ttl' => $ttl); }
function delete_site_transient($key) { unset($GLOBALS['cache'][$key]); }
function wp_safe_remote_get($url, $args) {
    $GLOBALS['calls'][] = $url;
    if (! isset($GLOBALS['responses'][$url])) { throw new RuntimeException('Unexpected network request: ' . $url); }
    return $GLOBALS['responses'][$url];
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code($value) { return $value['code']; }
function wp_remote_retrieve_body($value) { return $value['body']; }
function __($text, $domain = '') { return $text; }
function esc_html__($text, $domain = '') { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
require dirname(__DIR__) . '/includes/class-wp-image-slider-updater.php';
$updater = new WP_Image_Slider_Updater();
$assertions = 0;
function verify($condition, $message) {
    $GLOBALS['assertions']++;
    if (! $condition) { throw new RuntimeException($message); }
}
function fixture($version = '1.4.0') {
    $GLOBALS['cache'] = array();
    $GLOBALS['calls'] = array();
    $repo = $GLOBALS['repository'];
    $base = 'https://github.com/' . $repo . '/releases/download/v' . $version . '/';
    $release = array('tag_name' => 'v' . $version, 'draft' => false, 'prerelease' => false,
        'body' => '<script>alert(1)</script>', 'published_at' => '2026-10-30T00:00:00Z',
        'assets' => array(
            array('name' => 'wp-image-slider.zip', 'state' => 'uploaded', 'browser_download_url' => $base . 'wp-image-slider.zip'),
            array('name' => 'wp-image-slider.json', 'state' => 'uploaded', 'browser_download_url' => $base . 'wp-image-slider.json'),
        ));
    $GLOBALS['responses'] = array(
        'https://api.github.com/repos/' . $repo . '/releases/latest' => array('code' => 200, 'body' => json_encode($release)),
        $base . 'wp-image-slider.json' => array('code' => 200, 'body' => json_encode(array('version' => $version, 'requires' => '6.0', 'requires_php' => '8.0'))),
    );
    return $release;
}
function replace_release($release) {
    $GLOBALS['responses']['https://api.github.com/repos/' . $GLOBALS['repository'] . '/releases/latest']['body'] = json_encode($release);
}
fixture();
$update = $updater->check_update(false, array(), 'wp-image-slider/wp-image-slider.php', array());
verify($update['version'] === '1.4.0', 'New release detected');
verify($update['requires'] === '6.0' && $update['requires_php'] === '8.0', 'Remote compatibility preserved');
verify(substr($update['package'], -19) === 'wp-image-slider.zip', 'Named installable asset selected');
verify(count($calls) === 2, 'Only API and manifest requested');
$updater->release();
verify(count($calls) === 2, 'Successful result cached');
verify(reset($cache)['ttl'] === 21600, 'Six-hour success cache');
$updater->release(true);
verify(count($calls) === 4, 'Manual check bypasses cache');
$unrelated = array('version' => '9.0');
verify($updater->check_update($unrelated, array(), 'other/plugin.php', array()) === $unrelated, 'Other plugins unaffected');
$info = $updater->plugin_information(false, 'plugin_information', (object) array('slug' => 'wp-image-slider'));
verify(strpos($info->sections['changelog'], '<script>') === false && strpos($info->sections['changelog'], '&lt;script&gt;') !== false, 'Release notes escaped');
verify($updater->plugin_information('original', 'query_plugins', (object) array('slug' => 'wp-image-slider')) === 'original', 'Other API actions unaffected');
foreach (array('1.3.0', '1.2.0') as $version) {
    fixture($version);
    $candidate = $updater->check_update(false, array(), 'wp-image-slider/wp-image-slider.php', array());
    verify(! version_compare($candidate['version'], WP_IMAGE_SLIDER_VERSION, '>'), 'Core will not update to same/older version');
}
foreach (array('draft', 'prerelease') as $flag) {
    $release = fixture(); $release[$flag] = true; replace_release($release);
    verify($updater->release()['release'] === false, 'Unstable release rejected: ' . $flag);
}
$release = fixture(); $release['tag_name'] = 'v1.4.0-beta.1'; replace_release($release);
verify($updater->release()['release'] === false, 'Prerelease tag rejected');
$release = fixture(); array_pop($release['assets']); replace_release($release);
verify($updater->release()['release'] === false, 'Missing metadata asset rejected');
$release = fixture(); $release['assets'][0]['browser_download_url'] = 'https://evil.example/plugin.zip'; replace_release($release);
verify($updater->release()['release'] === false, 'Untrusted package URL rejected');
$release = fixture(); $release['assets'][0]['browser_download_url'] = str_replace('cbdi-webdev/', 'other/', $release['assets'][0]['browser_download_url']); replace_release($release);
verify($updater->release()['release'] === false, 'Other repository package rejected');
fixture();
$manifest_url = 'https://github.com/' . $repository . '/releases/download/v1.4.0/wp-image-slider.json';
$responses[$manifest_url]['body'] = '{"version":"1.5.0","requires":"5.8","requires_php":"7.4"}';
verify($updater->release()['release'] === false, 'Tag/manifest mismatch rejected');
fixture(); $responses[$manifest_url]['body'] = '{"version":"1.4.0","requires":[],"requires_php":"7.4"}';
verify($updater->release()['release'] === false, 'Malformed compatibility metadata rejected');
foreach (array(403, 404, 429, 500) as $code) {
    fixture(); $responses['https://api.github.com/repos/' . $repository . '/releases/latest'] = array('code' => $code, 'body' => '{}');
    $state = $updater->release();
    verify($state['release'] === false && strpos($state['error'], (string) $code) !== false, 'HTTP failure is visible: ' . $code);
    verify(reset($cache)['ttl'] === 900, 'Failure cache is fifteen minutes');
}
fixture(); $responses['https://api.github.com/repos/' . $repository . '/releases/latest'] = new WP_Error('timeout', 'Timed out');
verify($updater->release()['error'] === 'Timed out', 'Transport failure handled');
fixture(); $responses['https://api.github.com/repos/' . $repository . '/releases/latest']['body'] = 'not json';
verify($updater->release()['release'] === false, 'Invalid API JSON rejected');
$repository = '../../invalid'; $cache = array(); $calls = array();
verify($updater->release()['release'] === false && count($calls) === 0, 'Invalid repository makes no requests');
verify(isset($filters['update_plugins_wp-image-slider.invalid']), 'Native Update URI hook registered');
echo "Passed $assertions updater assertions.\n";
