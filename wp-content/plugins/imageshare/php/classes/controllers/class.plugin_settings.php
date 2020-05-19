<?php

namespace Imageshare\Controllers;

require_once imageshare_php_file('classes/class.logger.php');
require_once imageshare_php_file('classes/views/class.plugin_settings.php');

use Imageshare\Logger;
use ImageShare\Views\PluginSettings as View;

class PluginSettings {
    const i18n_ns    = 'imageshare';
    const capability = 'manage_options';
    const page_slug  = 'imageshare_settings';

    public function __construct() {
        add_action('admin_init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    public function init() {
    }

    public function add_admin_menu() {
        $page_title = __('Imageshare settings', self::i18n_ns);
        $menu_title = __('Imageshare', self::i18n_ns);

        add_submenu_page(
            'options-general.php',
            $page_title,
            $menu_title,
            self::capability,
            self::page_slug,
            array($this, 'render_settings_page')
        );
    }

    public function render_settings_page() {
        $rendered = View::render();
        echo $rendered;
    }
}
