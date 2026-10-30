<?php
/** Build installable assets without Git data, tests, or site files. */
if (PHP_SAPI !== 'cli') {
    exit;
}
$root = dirname(__DIR__);
$main = file_get_contents($root . '/wp-image-slider.php');
$readme = file_get_contents($root . '/readme.txt');
$headers = array();
foreach (array('Version', 'Requires at least', 'Requires PHP') as $header) {
    if (! preg_match('/^' . preg_quote($header, '/') . ':\s*(\S+)/m', $main, $match)) {
        fwrite(STDERR, "Missing plugin header: $header\n");
        exit(1);
    }
    $headers[$header] = $match[1];
}
$version = $headers['Version'];
if (! preg_match('/^\d+\.\d+\.\d+$/D', $version)
    || strpos($main, "define('WP_IMAGE_SLIDER_VERSION', '$version')") === false
    || ! preg_match('/^Stable tag:\s*' . preg_quote($version, '/') . '\s*$/m', $readme)
    || (isset($argv[2]) && ltrim($argv[2], 'v') !== $version)) {
    fwrite(STDERR, "Plugin header, version constant, readme stable tag, and Git release tag must match.\n");
    exit(1);
}
if (! class_exists('ZipArchive')) {
    fwrite(STDERR, "The PHP zip extension is required to build a release.\n");
    exit(1);
}
$output = isset($argv[1]) ? $argv[1] : $root . '/dist';
if (! is_dir($output) && ! mkdir($output, 0775, true)) {
    throw new RuntimeException('Could not create output directory.');
}
$zip_path = $output . '/wp-image-slider.zip';
$zip = new ZipArchive();
if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Could not create ZIP.');
}
$files = array('wp-image-slider.php', 'index.php', 'README.md', 'readme.txt', 'CHANGELOG.md');
foreach (array('assets', 'includes') as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        }
    }
}
sort($files);
foreach ($files as $file) {
    if (! $zip->addFile($root . '/' . $file, 'wp-image-slider/' . $file)) {
        throw new RuntimeException('Could not add file: ' . $file);
    }
}
if (! $zip->close()) {
    throw new RuntimeException('Could not finish ZIP.');
}
$manifest = array('version' => $version, 'requires' => $headers['Requires at least'], 'requires_php' => $headers['Requires PHP']);
if (preg_match('/^Tested up to:\s*(\S+)/m', $readme, $match)) {
    $manifest['tested'] = $match[1];
}
if (file_put_contents($output . '/wp-image-slider.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n") === false) {
    throw new RuntimeException('Could not write update manifest.');
}
echo "Built WP Image Slider $version: $zip_path\n";
