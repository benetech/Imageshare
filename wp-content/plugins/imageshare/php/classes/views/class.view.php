<?php

namespace Imageshare\Views;

require_once imageshare_php_file('classes/class.logger.php');

class View {
    public static function load($name) {
        $loader = new \Twig\Loader\FilesystemLoader(IMAGESHARE_TEMPLATE_PATH);
        $twig = new \Twig\Environment($loader, [
            'cache' => false,
            'strict_variables' => true,
            'debug' => true
        ]);

        $twig->addExtension(new \Twig\Extension\DebugExtension());

        $filter = new \Twig\TwigFilter('i18n', function ($string) {
            return __($string, 'imageshare');
        });

        $twig->addFilter($filter);

        return $twig->load($name);
    }

}
 
