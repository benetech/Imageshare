<?php

namespace Imageshare\Views;

require_once imageshare_php_file('classes/class.logger.php');

class View {
    public static function load($name) {
        $loader = new \Twig\Loader\FilesystemLoader(IMAGESHARE_TEMPLATE_PATH);
        $twig = new \Twig\Environment($loader, ['cache' => false]); 
        return $twig->load($name);
    }
}
 
