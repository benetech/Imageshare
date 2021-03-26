<?php

namespace Imageshare\Controllers;

use Imageshare\Logger;

class JSONAPI {
    const api_controllers = [
        'types',
        'formats',
        'subjects',
        'accommodations',
        'sources',
        'collections',
        'resources',
        'keywords'
    ];

    public function __construct() {
        add_rewrite_rule('json-api/(resources|collections)/filter[/]?(.*)$', 'index.php?btis_api_search=1&btis_api=$matches[1]$matches[2]', 'top');

        // /json-api/resources/page/n
        add_rewrite_rule('json-api/resources/page/([0-9]+)[/]?$', 'index.php?btis_api=resources&btis_api_page=$matches[1]', 'top');

        // /json-api/types/
        add_rewrite_rule('json-api/([a-z]+)[/]?$', 'index.php?btis_api=$matches[1]', 'top');

        // /json-api/types/1
        add_rewrite_rule('json-api/([a-z]+)/([0-9]+)[/]?$', 'index.php?btis_api=$matches[1]&btis_api_id=$matches[2]', 'top');

        // /json-api/types/1/relationships/parent
        add_rewrite_rule('json-api/([a-z]+)/([0-9]+)/relationships/([a-z]+)[/]?$', 'index.php?btis_api=$matches[1]&btis_api_id=$matches[2]&btis_api_relationship=$matches[3]', 'top');

        // /json-api/types/1/parent
        add_rewrite_rule('json-api/([a-z]+)/([0-9]+)/([a-z]+)[/]?$', 'index.php?btis_api=$matches[1]&btis_api_id=$matches[2]&btis_api_relationship=$matches[3]', 'top');

        add_filter('query_vars', [$this, 'filter_query_vars']);
        add_filter('template_include', [$this, 'filter_template_include']);
    }

    public function filter_query_vars($query_vars) {
        $query_vars[] = 'btis_api';
        $query_vars[] = 'btis_api_page';
        $query_vars[] = 'btis_api_id';
        $query_vars[] = 'btis_api_relationship';
        $query_vars[] = 'btis_api_search';

        return $query_vars;
    }

    public function filter_template_include($template) {
        $controller = get_query_var('btis_api');
        $is_search = get_query_var('btis_api_search');

        if ($is_search || (isset($controller) && in_array($controller, self::api_controllers))) {
            return imageshare_php_file("classes/controllers/json_api/dispatch.php");
        }

        return $template;
    }
}

