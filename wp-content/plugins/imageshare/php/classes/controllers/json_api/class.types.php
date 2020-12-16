<?php

namespace Imageshare\Controllers\JSONAPI;

require_once imageshare_php_file('classes/controllers/json_api/class.taxonomy.php');

class Types extends Taxonomy {
    const taxonomy = 'file_types';
    const name = 'type';
    const plural_name = 'types';

    public static function transform($term, $data) {
        $thumbnail = get_field('thumbnail', 'category_' . $term->term_id);
        $data['attributes']['thumbnail'] = $thumbnail;
        return $data;
    }

}
