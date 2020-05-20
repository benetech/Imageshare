<?php

namespace Imageshare\Views;

require_once imageshare_php_file('classes/class.logger.php');
require_once imageshare_php_file('classes/views/class.view.php');

use Imageshare\Logger;

class PluginSettings extends View {
    public static function render() {
        $nonce = wp_create_nonce('imageshare-settings');

        $template = self::load('settings.twig');
        return $template->render(['wp_nonce' => $nonce]);
    }
}
 
