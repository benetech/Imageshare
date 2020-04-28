<?php
/*
Plugin Name: Imageshare
Description: Benetech Imageshare resource management
Plugin URI: https://imageshare.benetech.com
Author: Job van Achterberg & Prime Access Consulting
Version: 0.0.1
Author URI: https://pac.bz
 */

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

error_log('Loading Imageshare plugin');

define('IMAGESHARE_PLUGIN_FILE', __FILE__);
define('IMAGESHARE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('IMAGESHARE_VERSION', '0.0.1');

function imageshare_php_file(string $path) {
    return _imageshare_file('php', $path);
}

function _imageshare_file(string $type, string $path) {
    return IMAGESHARE_PLUGIN_PATH . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $path;
}

require_once imageshare_php_file('classes/class.plugin.php');

use ImageShare\Plugin;

global $imageshare_plugin;
$imageshare_plugin = new Plugin(IMAGESHARE_PLUGIN_FILE, IMAGESHARE_VERSION, is_admin());

