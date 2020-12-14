<?php

namespace Imageshare\Controllers\JSONAPI;

require_once imageshare_php_file('classes/controllers/json_api/class.base.php');

class Accommodations extends Base {
    public static function render() {
        $terms = get_terms([
            'taxonomy' => 'a11y_accs',
            'orderby' => 'name',
            'hide_empty' => false
        ]);

        $types = array_reduce($terms, function ($list, $term) {

            $list[] = [
                'type' => 'resource_accommodation',
                'id' => (string) $term->term_id,
                'attributes' => [
                    'name' => $term->name,
                ]
            ];

            return $list;
        }, []);

        return parent::render_response($types);
    }
}
