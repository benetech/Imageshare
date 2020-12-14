<?php

namespace Imageshare\Controllers;

use Imageshare\Logger;

class JSONAPI {
    const api_controllers = [
        'types',
        'subjects',
        'accommodations',
        'sources',
        'collections'
    ];

    public function __construct() {
        add_rewrite_rule('json-api/([a-z]+)[/]?$', 'index.php?btis_api=$matches[1]', 'top');
        add_filter('query_vars', [$this, 'filter_query_vars']);
        add_filter('template_include', [$this, 'filter_template_include']);
    }

    public function filter_query_vars($query_vars) {
        $query_vars[] = 'btis_api';
        return $query_vars;
    }

    public function filter_template_include($template) {
        $controller = get_query_var('btis_api');

        if (!isset($controller) || !in_array($controller, self::api_controllers)) {
            return $template;
        }

        return imageshare_php_file("classes/controllers/json_api/dispatch.php");
    }
}

