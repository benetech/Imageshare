<?php

namespace Imageshare\Views;

require_once imageshare_php_file('classes/class.logger.php');

use Imageshare\Logger;

class PluginSettings {
    public static function render() {
        return "settings";
    }
}
 
