<?php

namespace Imageshare\Controllers\JSONAPI;

require_once imageshare_php_file('classes/controllers/json_api/class.base.php');

class Types extends Base {
    public static function render() {
        $terms = get_terms([
            'taxonomy' => 'file_types',
            'orderby' => 'name',
            'hide_empty' => false
        ]);

        $types = array_reduce($terms, function ($list, $term) {
            $thumbnail = get_field('thumbnail', 'category_' . $term->term_id);

            $list[] = [
                'type' => 'resource_file_type',
                'id' => (string) $term->term_id,
                'attributes' => [
                    'name' => $term->name,
                    'thumbnail' => $thumbnail
                ]
            ];

            return $list;
        }, []);

        return parent::render_response($types);
    }
}
