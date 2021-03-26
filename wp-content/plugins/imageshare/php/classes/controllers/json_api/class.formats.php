<?php

namespace Imageshare\Controllers\JSONAPI;

require_once imageshare_php_file('classes/controllers/json_api/class.taxonomy.php');

class Formats extends Taxonomy {
    const taxonomy = 'file_formats';
    const name = 'format';
    const plural_name = 'format';

    public static function transform($term, $data) {
        $thumbnail = get_field('thumbnail', 'category_' . $term->term_id);
        $data['attributes']['thumbnail'] = $thumbnail;
        return $data;
    }

}
