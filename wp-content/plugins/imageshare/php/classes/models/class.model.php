<?php

namespace Imageshare\Models;

class Model {
    public static function get_hierarchical_terms($taxonomy) {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'orderby' => 'parent',
            'hide_empty' => false,
        ]);

        $id_lookup = array_reduce($terms, function ($carry, $item) {
            $carry[$item->term_id] = $item;
            return $carry;
        }, []);

        $results = [];

        foreach ($terms as $term) {
            if ($parent_id = $term->parent) {
                $parent = $id_lookup[$parent_id];
                $results[$term->term_id] = [$parent->name, $term->name];
                continue;
            }

            $results[$term->term_id] = [$term->name];
        }

        return $results;
    }

    public static function get_terms($taxonomy) {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'orderby' => 'name',
            'hide_empty' => false
        ]);

        return array_reduce($terms, function ($carry, $item) {
            $carry[$item->term_id] = $item->name;
            return $carry;
        }, []);
    }
}

