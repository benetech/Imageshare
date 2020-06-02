<?php

namespace Imageshare\Models;

class Model {
    public static function get_meta_term_name(string $post_id, string $meta_key, string $taxonomy, bool $reverse = false) {
        $term_id = get_post_meta($post_id, $meta_key, true);

        $term = get_term($term_id, $taxonomy);

        if (is_wp_error($term)) {
            error_log(sprintf('Unable to get term %s with term_id %d in taxonomy %s', $meta_key, $term_id, $taxonomy));
            return '';
        }

        if ($parent_id = $term->parent) {
            $parent_term = get_term($parent_id);
            return join(' - ', $reverse ? [$term->name, $parent_term->name] : [$parent_term->name, $term->name]);
        }

        return $term->name;
    }

    public static function get_hierarchical_terms($taxonomy) {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'orderby' => 'term_group',
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
                $results[$term->term_id] = [$term->name, $parent->name];
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

    public static function get_taxonomy_term_id($taxonomy, $term_name) {
        $term = get_term_by('name', $term_name, $taxonomy);

        if ($term === false) {
            throw new \Exception(sprintf(__('Term %s was not found in taxonomy %s', 'imageshare'), $term_name, $taxonomy));
        }

        return $term->term_id;
    }

    public static function mark_created($post_id) {
        // mark post as created
        // this is done so the search indexing hook doesn't cause problems
        add_post_meta($post_id, 'created', true, true);
    }

    public static function is_created($post_id) {
        return get_post_meta($post_id, 'created', true);
    }
}

