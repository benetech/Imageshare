<?php

namespace Imageshare\Models;

use Imageshare\Logger;

class Term {
    public static function update_meta($term_id, $field, $value) {
        if ($field['name'] === 'term_aliases') {
            self::set_term_aliases($term_id, $value);
        }

        return $value;
    }

    public static function set_term_aliases($term_id, $value) {
        $aliases = array_map(function($a) {
            return trim($a);
        }, explode(',', $value));

        delete_metadata('term', $term_id, 'term_alias');

        foreach ($aliases as $alias) {
            add_term_meta((int) $term_id, 'term_alias', $alias);
        }
    }
}

