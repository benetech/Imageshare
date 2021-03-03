<?php
/*
Plugin Name: Imageshare
Description: Benetech Imageshare resource management
Plugin URI: https://imageshare.benetech.org
Author: Prime Access Consulting
Version: 0.0.1
Author URI: https://pac.bz
 */

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

require_once('vendor/autoload.php');

error_log('Loading Imageshare plugin');

define('IMAGESHARE_PLUGIN_FILE', __FILE__);
define('IMAGESHARE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('IMAGESHARE_VERSION', '0.0.1');

define('IMAGESHARE_TEMPLATE_PATH', IMAGESHARE_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'templates');
define('IMAGESHARE_TEMPLATE_CACHE_PATH', IMAGESHARE_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'template_cache');

function imageshare_php_file(string $path) {
    return _imageshare_file('php', $path);
}

function imageshare_asset_file(string $path) {
    return _imageshare_file('assets', $path);
}

function imageshare_asset_url(string $path) {
    return plugin_dir_url(__FILE__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . $path;
}

function _imageshare_file(string $type, string $path) {
    return IMAGESHARE_PLUGIN_PATH . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $path;
}

require_once imageshare_php_file('classes/class.plugin.php');

use ImageShare\Plugin;

global $imageshare;
$imageshare = new Plugin(IMAGESHARE_PLUGIN_FILE, IMAGESHARE_VERSION, is_admin());

